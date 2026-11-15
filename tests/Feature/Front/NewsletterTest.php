<?php

namespace Tests\Feature\Front;

use App\Mail\NewsletterConfirm;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Services\NewsletterService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The mailing list.
 *
 * The footer box is open to the world, so the thing being protected is not
 * the shop — it is the stranger whose address somebody else might type in.
 * Hence double opt-in by default, and one click to leave.
 */
class NewsletterTest extends TestCase
{
    use RefreshDatabase;

    protected NewsletterService $newsletter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        Mail::fake();

        $this->newsletter = app(NewsletterService::class);
    }

    /* ---------------------------------------------------- double opt-in */

    public function test_a_sign_up_is_not_subscribed_until_the_link_is_clicked(): void
    {
        $this->post(route('newsletter.subscribe'), [
            'email' => 'Ravi@Example.COM',
            'source' => 'footer',
            'website' => '',
        ])->assertRedirect();

        $subscriber = NewsletterSubscriber::sole();

        // Stored lower-case, so the same person cannot end up on the list twice.
        $this->assertSame('ravi@example.com', $subscriber->email);
        $this->assertSame('pending', $subscriber->status);
        $this->assertNotNull($subscriber->confirm_token);
        $this->assertNull($subscriber->confirmed_at);
        $this->assertSame(0, NewsletterSubscriber::mailable()->count());

        Mail::assertSent(NewsletterConfirm::class);
    }

    public function test_clicking_the_link_subscribes_them(): void
    {
        $subscriber = $this->newsletter->subscribe('ravi@example.com')['subscriber'];

        $this->get(route('newsletter.confirm', $subscriber->confirm_token))
            ->assertOk()
            ->assertSee('You are on the list');

        $subscriber = $subscriber->fresh();

        $this->assertSame('subscribed', $subscriber->status);
        $this->assertNotNull($subscriber->confirmed_at);
        // Spent, so the same link cannot be replayed.
        $this->assertNull($subscriber->confirm_token);
        $this->assertSame(1, NewsletterSubscriber::mailable()->count());
    }

    public function test_a_used_or_made_up_confirmation_link_says_so_without_failing(): void
    {
        $this->get(route('newsletter.confirm', str_repeat('a', 40)))
            ->assertOk()
            ->assertSee('expired');
    }

    public function test_with_confirmation_switched_off_they_are_on_the_list_at_once(): void
    {
        settings()->set('newsletter_double_optin', '0');

        $this->post(route('newsletter.subscribe'), ['email' => 'ravi@example.com', 'website' => '']);

        $this->assertSame('subscribed', NewsletterSubscriber::sole()->status);
        Mail::assertNotSent(NewsletterConfirm::class);
    }

    /* -------------------------------------------------- signing up twice */

    public function test_somebody_already_on_the_list_is_not_emailed_again(): void
    {
        $this->newsletter->subscribe('ravi@example.com');
        $subscriber = NewsletterSubscriber::sole();
        $this->newsletter->confirm($subscriber->confirm_token);

        Mail::fake();

        $this->post(route('newsletter.subscribe'), ['email' => 'ravi@example.com', 'website' => ''])
            ->assertSessionHas('success');

        $this->assertSame(1, NewsletterSubscriber::count());
        Mail::assertNothingSent();
    }

    public function test_coming_back_after_unsubscribing_is_allowed_but_only_by_asking_again(): void
    {
        $subscriber = $this->newsletter->subscribe('ravi@example.com')['subscriber'];
        $this->newsletter->confirm($subscriber->confirm_token);
        $this->newsletter->unsubscribe($subscriber->fresh(), 'Too many emails');

        $this->assertSame('unsubscribed', $subscriber->fresh()->status);

        // Filling the form in again is them asking.
        $result = $this->newsletter->subscribe('ravi@example.com');

        $this->assertSame('pending', $result['subscriber']->status);
        $this->assertNull($result['subscriber']->unsubscribed_at);
        $this->assertSame(1, NewsletterSubscriber::count());
    }

    /* ------------------------------------------------------ unsubscribing */

    public function test_the_unsubscribe_link_works_in_one_click_with_no_sign_in(): void
    {
        $subscriber = $this->newsletter->subscribe('ravi@example.com')['subscriber'];
        $this->newsletter->confirm($subscriber->confirm_token);

        $this->get(route('newsletter.unsubscribe', $subscriber->unsubscribe_token))
            ->assertOk()
            ->assertSee('unsubscribed');

        $subscriber = $subscriber->fresh();

        $this->assertSame('unsubscribed', $subscriber->status);
        $this->assertNotNull($subscriber->unsubscribed_at);
        $this->assertSame(0, NewsletterSubscriber::mailable()->count());
    }

    public function test_the_unsubscribe_token_is_the_address_not_the_row_id(): void
    {
        $subscriber = $this->newsletter->subscribe('ravi@example.com')['subscriber'];

        // The id alone must not reach anybody's subscription.
        $this->get('/newsletter/unsubscribe/'.$subscriber->id)->assertNotFound();

        $this->assertSame('pending', $subscriber->fresh()->status);
        $this->assertSame(40, strlen($subscriber->unsubscribe_token));
    }

    public function test_an_unsubscribed_address_is_left_out_of_the_export(): void
    {
        $staying = $this->newsletter->subscribe('asha@example.com')['subscriber'];
        $this->newsletter->confirm($staying->confirm_token);

        $leaving = $this->newsletter->subscribe('ravi@example.com')['subscriber'];
        $this->newsletter->confirm($leaving->confirm_token);
        $this->newsletter->unsubscribe($leaving->fresh());

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $csv = $this->actingAs($admin, 'admin')
            ->get(route('admin.newsletter.export'))
            ->streamedContent();

        $this->assertStringContainsString('asha@example.com', $csv);
        $this->assertStringNotContainsString('ravi@example.com', $csv);
        // Every row carries a way out, for whatever sends the mail.
        $this->assertStringContainsString($staying->unsubscribe_token, $csv);
    }

    /* ------------------------------------------------------------- bots */

    public function test_the_honeypot_turns_a_bot_away(): void
    {
        $this->post(route('newsletter.subscribe'), [
            'email' => 'bot@example.com',
            'website' => 'http://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, NewsletterSubscriber::count());
    }

    public function test_a_malformed_address_is_rejected(): void
    {
        $this->post(route('newsletter.subscribe'), ['email' => 'not-an-address', 'website' => ''])
            ->assertSessionHasErrors('email');

        $this->assertSame(0, NewsletterSubscriber::count());
    }

    /**
     * A failed send must not lose the sign-up: they are recorded as pending
     * either way and can ask for the link again.
     */
    public function test_a_failed_confirmation_email_still_records_the_sign_up(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('mail server down'));

        $this->post(route('newsletter.subscribe'), ['email' => 'ravi@example.com', 'website' => ''])
            ->assertRedirect();

        $this->assertSame('pending', NewsletterSubscriber::sole()->status);
    }

    /* ---------------------------------------------------------- the shop */

    public function test_the_footer_form_can_be_switched_off(): void
    {
        $this->get(route('home'))->assertSee('Subscribe for Updates');

        settings()->set('newsletter_enabled', '0');

        $this->get(route('home'))->assertDontSee('Subscribe for Updates');
    }

    public function test_the_admin_list_shows_who_is_on_it(): void
    {
        $subscriber = $this->newsletter->subscribe('ravi@example.com')['subscriber'];
        $this->newsletter->confirm($subscriber->confirm_token);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')
            ->get(route('admin.newsletter.index'))
            ->assertOk()
            ->assertSee('ravi@example.com');
    }

    /**
     * The list is personal data. Being allowed to change a homepage slide is
     * not being allowed to export every subscriber's email address.
     */
    public function test_banner_access_alone_does_not_open_the_mailing_list(): void
    {
        $role = \Spatie\Permission\Models\Role::findOrCreate('Banner Editor', 'admin');
        $role->syncPermissions(['banners.view', 'banners.edit']);

        $staff = User::factory()->create();
        $staff->assignRole($role);

        $this->actingAs($staff, 'admin')->get(route('admin.banners.index'))->assertOk();
        $this->actingAs($staff, 'admin')->get(route('admin.newsletter.index'))->assertForbidden();
        $this->actingAs($staff, 'admin')->get(route('admin.newsletter.export'))->assertForbidden();
    }

    public function test_staff_can_take_somebody_off_who_asked_by_phone(): void
    {
        $subscriber = $this->newsletter->subscribe('ravi@example.com')['subscriber'];
        $this->newsletter->confirm($subscriber->confirm_token);

        $admin = User::factory()->create();
        $admin->assignRole('Admin');

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.newsletter.unsubscribe', $subscriber), ['reason' => 'Asked on the phone'])
            ->assertRedirect();

        $this->assertSame('unsubscribed', $subscriber->fresh()->status);
        $this->assertSame('Asked on the phone', $subscriber->fresh()->unsubscribe_reason);
    }
}
