<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Review;
use App\Models\User;
use App\Services\ReviewNotPublishable;
use App\Services\ReviewService;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reviews, from arrival to publication.
 *
 * The rule that holds everything together: nothing a stranger typed reaches
 * a product page until somebody at the shop has read it, and the rating
 * shown is the average of exactly what is published — never of what is
 * waiting, rejected, or deleted.
 */
class ReviewModerationTest extends TestCase
{
    use RefreshDatabase;

    protected ReviewService $reviews;

    protected User $admin;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->reviews = app(ReviewService::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        $category = Category::create(['name' => 'Sofas', 'slug' => 'sofas', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Oakwood Three Seater', 'slug' => 'oakwood-three-seater',
            'sku' => Str::upper(Str::random(8)),
            'price' => 35400, 'stock_quantity' => 4, 'gst_rate' => 18, 'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function submit(array $overrides = []): Review
    {
        return $this->reviews->submit($this->product, array_merge([
            'name' => 'Ravi Kumar',
            'email' => 'ravi@example.com',
            'city' => 'New Delhi',
            'rating' => 5,
            'title' => 'Solid build',
            'body' => 'Arrived on time and the finish is exactly as shown.',
        ], $overrides));
    }

    protected function deliveredOrder(string $email): Order
    {
        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'city' => 'New Delhi',
            'state' => 'Delhi', 'pincode' => '110001',
        ];

        $order = Order::create([
            'order_number' => 'FC/2611/'.str_pad((string) (Order::count() + 1), 5, '0', STR_PAD_LEFT),
            'customer_name' => 'Ravi Kumar',
            'customer_email' => $email,
            'customer_phone' => '9811122233',
            'shipping_address' => $address,
            'billing_address' => $address,
            'shipping_pincode' => '110001',
            'subtotal' => 35400, 'tax_total' => 5400, 'grand_total' => 35400,
            'amount_paid' => 35400, 'amount_due' => 0,
            'payment_method' => 'razorpay', 'payment_status' => 'paid',
            'status' => 'delivered', 'placed_at' => now(),
        ]);

        $order->items()->create([
            'product_id' => $this->product->id,
            'name' => $this->product->name, 'sku' => $this->product->sku, 'quantity' => 1,
            'unit_price' => 35400, 'line_subtotal' => 35400,
            'gst_rate' => 18, 'tax_amount' => 5400, 'line_total' => 35400,
        ]);

        return $order;
    }

    /* ------------------------------------------------------- the queue */

    public function test_a_new_review_waits_and_changes_nothing_on_the_product(): void
    {
        $review = $this->submit();

        $this->assertSame('pending', $review->status);
        $this->assertSame(0, (int) $this->product->fresh()->rating_count);
        $this->assertEqualsWithDelta(0.0, (float) $this->product->fresh()->rating_average, 0.001);
    }

    public function test_publishing_rolls_the_rating_onto_the_product(): void
    {
        $this->reviews->approve($this->submit(['rating' => 4]), $this->admin);
        $this->reviews->approve($this->submit(['email' => 'asha@example.com', 'name' => 'Asha Verma', 'rating' => 5]), $this->admin);

        // Left waiting, so it must not count.
        $this->submit(['email' => 'mohan@example.com', 'name' => 'Mohan Lal', 'rating' => 1]);

        $product = $this->product->fresh();

        $this->assertSame(2, (int) $product->rating_count);
        $this->assertEqualsWithDelta(4.5, (float) $product->rating_average, 0.001);
    }

    public function test_rejecting_a_published_review_takes_it_back_off(): void
    {
        $review = $this->reviews->approve($this->submit(['rating' => 5]), $this->admin);
        $this->assertSame(1, (int) $this->product->fresh()->rating_count);

        $this->reviews->reject($review, $this->admin, 'rejected', 'Mentions a competitor by name');

        $product = $this->product->fresh();

        $this->assertSame(0, (int) $product->rating_count);
        $this->assertEqualsWithDelta(0.0, (float) $product->rating_average, 0.001);
        $this->assertSame('Mentions a competitor by name', $review->fresh()->moderation_note);
    }

    public function test_deleting_a_review_stops_it_counting_immediately(): void
    {
        $review = $this->reviews->approve($this->submit(), $this->admin);

        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.reviews.destroy', $review))
            ->assertRedirect();

        $this->assertSame(0, (int) $this->product->fresh()->rating_count);
    }

    /**
     * Somebody changing their mind is an edit of one opinion, not a second
     * one — and the words have to be read again before they go back up.
     */
    public function test_a_second_review_of_the_same_product_replaces_the_first_and_waits_again(): void
    {
        $first = $this->reviews->approve($this->submit(['rating' => 5]), $this->admin);

        $second = $this->submit(['rating' => 2, 'body' => 'A leg came loose after a month.']);

        $this->assertSame($first->id, $second->id);
        $this->assertSame('pending', $second->status);
        $this->assertSame(1, Review::count());
        $this->assertSame(0, (int) $this->product->fresh()->rating_count);
    }

    /* -------------------------------------------------- verified buyers */

    public function test_a_delivered_order_marks_the_review_as_a_verified_purchase(): void
    {
        $this->deliveredOrder('ravi@example.com');

        $review = $this->submit();

        $this->assertTrue($review->is_verified);
        $this->assertNotNull($review->order_id);
    }

    public function test_an_order_that_has_not_arrived_yet_is_not_a_verified_purchase(): void
    {
        $this->deliveredOrder('ravi@example.com')->update(['status' => 'shipped']);

        $this->assertFalse($this->submit()->is_verified);
    }

    public function test_the_shop_can_insist_on_a_delivered_order(): void
    {
        settings()->set('reviews_require_purchase', '1');

        $blocked = $this->reviews->eligibility($this->product, 'stranger@example.com');
        $this->assertFalse($blocked['allowed']);

        $this->deliveredOrder('ravi@example.com');

        $allowed = $this->reviews->eligibility($this->product, 'ravi@example.com');
        $this->assertTrue($allowed['allowed']);
        $this->assertNotNull($allowed['order']);
    }

    /* ----------------------------------------------------- the homepage */

    public function test_only_a_published_review_can_go_on_the_homepage(): void
    {
        $waiting = $this->submit();

        $this->expectException(ReviewNotPublishable::class);
        $this->reviews->feature($waiting);
    }

    public function test_a_featured_review_is_dropped_when_it_is_rejected(): void
    {
        $review = $this->reviews->approve($this->submit(), $this->admin);
        $this->reviews->feature($review);

        $this->assertCount(1, $this->reviews->featured());

        $this->reviews->reject($review->fresh(), $this->admin, 'spam');

        $this->assertFalse($review->fresh()->is_featured);
        $this->assertCount(0, $this->reviews->featured());
    }

    /* ------------------------------------------------- what the public sees */

    public function test_a_waiting_review_is_not_on_the_product_page(): void
    {
        $this->submit(['body' => 'This sentence must not be published yet.']);

        $this->get(route('product.show', $this->product->slug))
            ->assertOk()
            ->assertDontSee('This sentence must not be published yet.');
    }

    public function test_a_published_review_and_the_shops_reply_are_both_shown(): void
    {
        $review = $this->reviews->approve($this->submit(['body' => 'Exactly as described.']), $this->admin);
        $this->reviews->reply($review, 'Thank you — glad it suits the room.', $this->admin);

        $this->get(route('product.show', $this->product->slug))
            ->assertOk()
            ->assertSee('Exactly as described.')
            ->assertSee('glad it suits the room', false);
    }

    /**
     * A review is public. Somebody leaving one did not ask to be searchable
     * by their full name.
     */
    public function test_the_surname_is_shortened_to_an_initial(): void
    {
        $review = $this->reviews->approve($this->submit(['name' => 'Ravi Kumar Sharma']), $this->admin);

        $this->assertSame('Ravi S.', $review->display_name);

        $this->get(route('product.show', $this->product->slug))
            ->assertSee('Ravi S.')
            ->assertDontSee('Ravi Kumar Sharma');
    }

    public function test_the_email_address_is_never_on_the_product_page(): void
    {
        $this->reviews->approve($this->submit(['email' => 'ravi.private@example.com']), $this->admin);

        $this->get(route('product.show', $this->product->slug))
            ->assertDontSee('ravi.private@example.com');
    }

    /* ------------------------------------------------ the rating spread */

    public function test_the_breakdown_counts_only_published_reviews(): void
    {
        $this->reviews->approve($this->submit(['rating' => 5]), $this->admin);
        $this->reviews->approve($this->submit(['email' => 'asha@example.com', 'rating' => 3]), $this->admin);
        $this->submit(['email' => 'mohan@example.com', 'rating' => 1]);

        $breakdown = $this->reviews->breakdown($this->product)->keyBy('star');

        $this->assertSame(1, $breakdown[5]['count']);
        $this->assertSame(1, $breakdown[3]['count']);
        $this->assertSame(0, $breakdown[1]['count']);
        $this->assertEqualsWithDelta(50.0, $breakdown[5]['percent'], 0.1);
    }

    /* ---------------------------------------------------- the moderator */

    public function test_the_queue_is_what_the_screen_opens_on(): void
    {
        $this->submit(['body' => 'Waiting to be read.']);
        $this->reviews->approve($this->submit(['email' => 'asha@example.com', 'body' => 'Already published.']), $this->admin);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reviews.index'))
            ->assertOk()
            ->assertSee('Waiting to be read.')
            ->assertDontSee('Already published.');
    }

    public function test_approving_records_who_read_it(): void
    {
        $review = $this->submit();

        $this->actingAs($this->admin, 'admin')
            ->patch(route('admin.reviews.approve', $review))
            ->assertRedirect();

        $review = $review->fresh();

        $this->assertSame('approved', $review->status);
        $this->assertSame($this->admin->id, $review->moderated_by);
        $this->assertNotNull($review->moderated_at);
    }

    public function test_a_sales_user_cannot_moderate_reviews(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('Sales');

        $this->actingAs($sales, 'admin')
            ->get(route('admin.reviews.index'))
            ->assertForbidden();
    }

    /* ------------------------------------------------ the customer's side */

    public function test_a_customer_can_leave_a_review_and_it_waits(): void
    {
        $customer = Customer::create([
            'name' => 'Asha Verma', 'email' => 'asha@example.com',
            'phone' => '9811100011', 'password' => bcrypt('Furni@2026'), 'is_active' => true,
        ]);

        $this->actingAs($customer, 'customer')
            ->post(route('review.store', $this->product->slug), [
                'name' => 'Asha Verma',
                'email' => 'asha@example.com',
                'city' => 'Pune',
                'rating' => 4,
                'title' => 'Good value',
                'body' => 'Comfortable and well finished for the price.',
                'website' => '',
            ])
            ->assertRedirect();

        $review = Review::sole();

        $this->assertSame('pending', $review->status);
        $this->assertSame($customer->id, $review->customer_id);
    }

    public function test_the_honeypot_turns_a_bot_submission_away(): void
    {
        $this->post(route('review.store', $this->product->slug), [
            'name' => 'Bot', 'email' => 'bot@example.com',
            'rating' => 5, 'body' => 'Cheap pills here.',
            'website' => 'http://spam.example',
        ])->assertSessionHasErrors('website');

        $this->assertSame(0, Review::count());
    }
}
