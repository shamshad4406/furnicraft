<?php

namespace Tests\Feature\Front;

use App\Mail\EnquiryReceived;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Enquiry;
use App\Models\Product;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The public enquiry form.
 *
 * Open to everyone, which is the point — most people asking about a sofa
 * have no account and should not need one — and therefore the thing that
 * most needs guarding against abuse.
 */
class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $category = Category::create(['name' => 'Almirah', 'slug' => 'almirah', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Steel Almirah', 'slug' => 'steel-almirah', 'sku' => Str::upper(Str::random(8)),
            'price' => 11800, 'stock_quantity' => 5, 'gst_rate' => 18, 'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'general',
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'phone' => '9811122233',
            'message' => 'Do you make this in teak as well?',
        ], $overrides);
    }

    /* ----------------------------------------------------------- the form */

    public function test_the_form_is_open_to_everyone(): void
    {
        $this->get(route('enquiry.create'))
            ->assertOk()
            ->assertSee('Send an enquiry');
    }

    public function test_the_form_shows_the_product_it_was_opened_from(): void
    {
        $this->get(route('enquiry.create', ['product' => $this->product->slug]))
            ->assertOk()
            ->assertSee('Steel Almirah');
    }

    public function test_a_signed_in_customer_gets_their_details_filled_in(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->get(route('enquiry.create'))
            ->assertOk()
            ->assertSee('value="ravi@example.com"', false);
    }

    /* -------------------------------------------------------- submitting */

    public function test_an_enquiry_is_recorded_and_acknowledged(): void
    {
        Mail::fake();

        $this->post(route('enquiry.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $enquiry = Enquiry::firstOrFail();

        $this->assertSame('new', $enquiry->status);
        $this->assertSame('web', $enquiry->source);
        $this->assertStringStartsWith('ENQ/', $enquiry->reference);
        $this->assertNull($enquiry->responded_at);

        Mail::assertSent(EnquiryReceived::class, 1);
    }

    public function test_a_product_enquiry_is_tied_to_the_product(): void
    {
        Mail::fake();

        $this->post(route('enquiry.store'), $this->payload([
            'type' => 'product',
            'product_slug' => $this->product->slug,
        ]));

        $this->assertSame($this->product->id, Enquiry::firstOrFail()->product_id);
    }

    public function test_a_signed_in_customers_enquiry_is_tied_to_their_account(): void
    {
        Mail::fake();

        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer')->post(route('enquiry.store'), $this->payload());

        $this->assertSame($customer->id, Enquiry::firstOrFail()->customer_id);
    }

    public function test_the_thank_you_page_shows_the_reference(): void
    {
        Mail::fake();

        $this->post(route('enquiry.store'), $this->payload());

        $enquiry = Enquiry::firstOrFail();

        $this->get(route('enquiry.sent', $enquiry->public_token))
            ->assertOk()
            ->assertSee($enquiry->reference);
    }

    /**
     * The enquiry is saved before the email is attempted, so a mail failure
     * must not lose somebody's question.
     */
    public function test_a_failed_acknowledgement_does_not_lose_the_enquiry(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('SMTP down'));

        $this->post(route('enquiry.store'), $this->payload())->assertRedirect();

        $this->assertDatabaseCount('enquiries', 1);
    }

    /* -------------------------------------------------------- validation */

    public function test_the_required_fields_are_required(): void
    {
        $this->post(route('enquiry.store'), [])
            ->assertSessionHasErrors(['type', 'name', 'email', 'phone', 'message']);
    }

    public function test_a_one_word_message_is_refused(): void
    {
        $this->post(route('enquiry.store'), $this->payload(['message' => 'hi']))
            ->assertSessionHasErrors('message');
    }

    public function test_a_date_in_the_past_is_refused(): void
    {
        $this->post(route('enquiry.store'), $this->payload([
            'required_by' => now()->subWeek()->toDateString(),
        ]))->assertSessionHasErrors('required_by');
    }

    public function test_an_invalid_pincode_is_refused(): void
    {
        $this->post(route('enquiry.store'), $this->payload(['pincode' => '0123']))
            ->assertSessionHasErrors('pincode');
    }

    /* ------------------------------------------------------------- abuse */

    /**
     * The honeypot is hidden from people and irresistible to bots.
     */
    public function test_a_filled_honeypot_is_refused(): void
    {
        $this->post(route('enquiry.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertDatabaseCount('enquiries', 0);
    }

    /**
     * A second identical submission is almost always an impatient second
     * click, not a second question.
     */
    public function test_an_immediate_resubmission_does_not_create_a_duplicate(): void
    {
        Mail::fake();

        $this->post(route('enquiry.store'), $this->payload());
        $this->post(route('enquiry.store'), $this->payload())
            ->assertRedirect(route('enquiry.create'))
            ->assertSessionHas('success');

        $this->assertDatabaseCount('enquiries', 1);
    }

    /* ------------------------------------------------------ attachments */

    public function test_photos_can_be_attached(): void
    {
        Mail::fake();
        Storage::fake('public');

        $this->post(route('enquiry.store'), $this->payload([
            'type' => 'custom',
            'attachments' => [
                UploadedFile::fake()->image('room.jpg', 1200, 900),
                UploadedFile::fake()->image('sketch.png', 800, 600),
            ],
        ]))->assertSessionHasNoErrors();

        $this->assertCount(2, Enquiry::firstOrFail()->attachments);
    }

    public function test_more_than_three_attachments_are_refused(): void
    {
        Storage::fake('public');

        $files = collect(range(1, 4))
            ->map(fn (int $n) => UploadedFile::fake()->image("photo{$n}.jpg"))
            ->all();

        $this->post(route('enquiry.store'), $this->payload(['attachments' => $files]))
            ->assertSessionHasErrors('attachments');
    }

    /**
     * The same rule as catalogue uploads: a renamed script is not an image.
     */
    public function test_a_disguised_file_is_refused(): void
    {
        Storage::fake('public');

        $fake = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "hi"; ?>');

        $this->post(route('enquiry.store'), $this->payload(['attachments' => [$fake]]))
            ->assertSessionHasErrors('attachments');

        $this->assertDatabaseCount('enquiries', 0);
    }

    /* ----------------------------------------------------- the entry points */

    public function test_the_product_pages_link_to_the_form(): void
    {
        $this->get(route('product.show', $this->product->slug))
            ->assertOk()
            ->assertSee(route('enquiry.create', ['product' => $this->product->slug]), false);
    }
}
