<?php

namespace Tests\Feature\Admin;

use App\Mail\EnquiryReplied;
use App\Models\Enquiry;
use App\Models\User;
use App\Services\EnquiryService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The enquiry inbox.
 *
 * `responded_at` is the figure that matters most here: it is the only honest
 * measure of whether anybody is actually answering people, so the tests are
 * careful about exactly what sets it.
 */
class EnquiryInboxTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected EnquiryService $enquiries;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->enquiries = app(EnquiryService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function enquiry(array $overrides = []): Enquiry
    {
        return $this->enquiries->create(array_merge([
            'type' => 'general',
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'phone' => '9811122233',
            'message' => 'Do you make this in teak as well?',
            'source' => 'web',
        ], $overrides));
    }

    /* ------------------------------------------------------- the inbox */

    public function test_the_inbox_lists_enquiries_with_its_counts(): void
    {
        $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.enquiries.index'))
            ->assertOk()
            ->assertViewHas('summary', fn (array $s) => $s['unanswered'] === 1 && $s['open'] === 1);
    }

    /**
     * Spam in the list is how an inbox stops being used.
     */
    public function test_spam_is_hidden_unless_asked_for(): void
    {
        $normal = $this->enquiry();
        $spam = $this->enquiry(['email' => 'bot@example.com']);
        $this->enquiries->close($spam, 'spam');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.enquiries.index'))
            ->assertOk()
            ->assertSee($normal->reference)
            ->assertDontSee($spam->reference);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.enquiries.index', ['status' => 'spam']))
            ->assertOk()
            ->assertSee($spam->reference);
    }

    public function test_the_inbox_can_be_searched_by_what_they_wrote(): void
    {
        $teak = $this->enquiry(['message' => 'Do you make this in teak?']);
        $other = $this->enquiry(['email' => 'other@example.com', 'message' => 'Where is your showroom?']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.enquiries.index', ['q' => 'teak']))
            ->assertOk()
            ->assertSee($teak->reference)
            ->assertDontSee($other->reference);
    }

    public function test_the_detail_page_shows_what_they_asked(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.enquiries.show', $enquiry))
            ->assertOk()
            ->assertSee($enquiry->reference)
            ->assertSee('Do you make this in teak as well?');
    }

    /* --------------------------------------------------------- replying */

    public function test_an_emailed_reply_reaches_the_customer_and_counts_as_answered(): void
    {
        Mail::fake();

        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.enquiries.reply', $enquiry), [
                'body' => 'Yes, we can do teak. It adds about two weeks.',
                'channel' => 'email',
            ])
            ->assertRedirect();

        $enquiry->refresh();

        $this->assertNotNull($enquiry->responded_at);
        $this->assertSame('in_progress', $enquiry->status);
        $this->assertNotNull($enquiry->replies->first()->emailed_at);

        Mail::assertSent(EnquiryReplied::class, 1);
    }

    /**
     * A note is staff talking to each other. Counting it as a response would
     * make the "unanswered" figure a lie.
     */
    public function test_an_internal_note_does_not_count_as_answering(): void
    {
        Mail::fake();

        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.enquiries.reply', $enquiry), [
                'body' => 'Checking with the workshop on timings.',
                'channel' => 'note',
            ])
            ->assertRedirect();

        $enquiry->refresh();

        $this->assertNull($enquiry->responded_at);
        $this->assertSame('new', $enquiry->status);

        Mail::assertNothingSent();
    }

    public function test_a_phone_call_counts_as_answering_but_sends_nothing(): void
    {
        Mail::fake();

        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.enquiries.reply', $enquiry), [
                'body' => 'Called and talked it through.',
                'channel' => 'phone',
            ]);

        $this->assertNotNull($enquiry->fresh()->responded_at);
        Mail::assertNothingSent();
    }

    /**
     * The reply is already written; a mail failure must not throw it away.
     */
    public function test_a_failed_email_still_keeps_the_reply(): void
    {
        $enquiry = $this->enquiry();

        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.enquiries.reply', $enquiry), [
                'body' => 'Yes, we can do teak.',
                'channel' => 'email',
            ])
            ->assertSessionHas('error');

        $this->assertCount(1, $enquiry->fresh()->replies);
        $this->assertNull($enquiry->fresh()->replies->first()->emailed_at);
    }

    public function test_an_empty_reply_is_refused(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.enquiries.reply', $enquiry), ['body' => '', 'channel' => 'note'])
            ->assertSessionHasErrors('body');
    }

    /* ------------------------------------------------- assigning, closing */

    public function test_assigning_moves_a_new_enquiry_to_being_handled(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.enquiries.assign', $enquiry), ['assigned_to' => $this->admin->id])
            ->assertRedirect();

        $enquiry->refresh();

        $this->assertSame($this->admin->id, $enquiry->assigned_to);
        $this->assertSame('in_progress', $enquiry->status);
    }

    public function test_closing_and_reopening(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.enquiries.close', $enquiry), [
                'status' => 'closed',
                'close_reason' => 'Answered on the phone.',
            ]);

        $enquiry->refresh();

        $this->assertSame('closed', $enquiry->status);
        $this->assertFalse($enquiry->isOpen());
        $this->assertNotNull($enquiry->closed_at);

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.enquiries.reopen', $enquiry));

        $this->assertSame('in_progress', $enquiry->fresh()->status);
        $this->assertNull($enquiry->fresh()->closed_at);
    }

    public function test_an_enquiry_cannot_be_closed_to_an_arbitrary_status(): void
    {
        $enquiry = $this->enquiry();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.enquiries.close', $enquiry), ['status' => 'converted'])
            ->assertSessionHasErrors('status');
    }

    /* -------------------------------------------------- logging by hand */

    public function test_staff_can_log_an_enquiry_that_came_by_phone(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.enquiries.store'), [
                'type' => 'custom',
                'source' => 'phone',
                'name' => 'Walk-in customer',
                'email' => 'walkin@example.com',
                'phone' => '9800000000',
                'message' => 'Wants a wardrobe built into an alcove.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $enquiry = Enquiry::firstOrFail();

        $this->assertSame('phone', $enquiry->source);
        // Whoever logged it is handling it.
        $this->assertSame($this->admin->id, $enquiry->assigned_to);
    }

    /* ------------------------------------------------------ safeguards */

    /**
     * An enquiry with money attached to it is a record.
     */
    public function test_an_enquiry_with_a_quotation_cannot_be_deleted(): void
    {
        $enquiry = $this->enquiry();

        app(\App\Services\QuotationService::class)->create([
            'customer_name' => 'Ravi Kumar',
            'customer_email' => 'ravi@example.com',
            'customer_phone' => '9811122233',
        ], $enquiry, $this->admin);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.enquiries.destroy', $enquiry))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('enquiries', ['id' => $enquiry->id, 'deleted_at' => null]);
    }

    public function test_the_inbox_needs_the_enquiry_permission(): void
    {
        $outsider = User::factory()->create();
        $outsider->givePermissionTo('dashboard.view');

        $this->actingAs($outsider, 'admin')
            ->get(route('admin.enquiries.index'))
            ->assertForbidden();
    }

    public function test_a_viewer_cannot_reply(): void
    {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo(['dashboard.view', 'enquiries.view']);

        $enquiry = $this->enquiry();

        $this->actingAs($viewer, 'admin')
            ->post(route('admin.enquiries.reply', $enquiry), ['body' => 'Hello', 'channel' => 'note'])
            ->assertForbidden();

        $this->assertCount(0, $enquiry->fresh()->replies);
    }
}
