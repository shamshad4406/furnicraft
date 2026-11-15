<?php

namespace Tests\Feature\Front;

use App\Mail\OrderConfirmation;
use App\Models\Customer;
use App\Models\Order;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Checking on an order after the fact.
 *
 * A signed-in customer has My Orders. A guest has only this: the link in
 * their confirmation email, or the order number plus the email they ordered
 * with. Without it, a guest's order disappears the moment their session does.
 */
class OrderTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        RateLimiter::clear('track-order|127.0.0.1');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function order(array $overrides = []): Order
    {
        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park', 'city' => 'New Delhi',
            'state' => 'Delhi', 'pincode' => '110001',
        ];

        $order = Order::create(array_merge([
            'order_number' => 'FC/2611/'.str_pad((string) (Order::count() + 1), 5, '0', STR_PAD_LEFT),
            'customer_name' => 'Ravi Kumar',
            'customer_email' => 'guest@example.com',
            'customer_phone' => '9811122233',
            'shipping_address' => $address,
            'billing_address' => $address,
            'shipping_pincode' => '110001',
            'subtotal' => 12500, 'grand_total' => 12500, 'amount_due' => 12500,
            'payment_method' => 'cod', 'status' => 'confirmed',
            'delivery_days' => 3, 'placed_at' => now(),
        ], $overrides));

        $order->items()->create([
            'name' => 'Steel Almirah', 'sku' => 'SA-1', 'quantity' => 1,
            'unit_price' => 12500, 'line_subtotal' => 12500,
            'gst_rate' => 18, 'tax_amount' => 1907, 'line_total' => 12500,
        ]);

        return $order->fresh('items');
    }

    /* ----------------------------------------------------- the lookup */

    public function test_the_lookup_form_is_open_to_everyone(): void
    {
        $this->get(route('track.form'))
            ->assertOk()
            ->assertSee('Track your order');
    }

    public function test_the_right_order_number_and_email_find_the_order(): void
    {
        $order = $this->order();

        $this->post(route('track.find'), [
            'order_number' => $order->order_number,
            'email' => 'guest@example.com',
        ])->assertRedirect(route('track.status', $order->public_token));
    }

    public function test_the_order_number_is_matched_regardless_of_case_and_spacing(): void
    {
        $order = $this->order();

        $this->post(route('track.find'), [
            'order_number' => '  '.strtolower($order->order_number).'  ',
            'email' => '  GUEST@Example.COM ',
        ])->assertRedirect(route('track.status', $order->public_token));
    }

    public function test_the_wrong_email_does_not_find_the_order(): void
    {
        $order = $this->order();

        $this->post(route('track.find'), [
            'order_number' => $order->order_number,
            'email' => 'someone.else@example.com',
        ])->assertSessionHasErrors('order_number');
    }

    /**
     * One message for both failures. Separate ones would confirm which order
     * numbers exist, or which email address belongs to a known order.
     */
    public function test_a_wrong_email_and_an_unknown_order_give_the_same_answer(): void
    {
        $order = $this->order();

        $wrongEmail = $this->post(route('track.find'), [
            'order_number' => $order->order_number,
            'email' => 'someone.else@example.com',
        ])->getSession()->get('errors')->first('order_number');

        $this->post(route('track.find'), [
            'order_number' => 'FC/9999/99999',
            'email' => 'guest@example.com',
        ])->assertSessionHasErrors(['order_number' => $wrongEmail]);
    }

    public function test_both_fields_are_required(): void
    {
        $this->post(route('track.find'), [])
            ->assertSessionHasErrors(['order_number', 'email']);
    }

    public function test_guessing_is_rate_limited(): void
    {
        $this->order();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('track.find'), [
                'order_number' => 'FC/2611/0000'.$attempt,
                'email' => 'guess@example.com',
            ]);
        }

        $this->post(route('track.find'), [
            'order_number' => 'FC/2611/00007',
            'email' => 'guess@example.com',
        ])->assertSessionHasErrorsIn('default', ['order_number']);

        $errors = session('errors')->first('order_number');
        $this->assertStringContainsString('Too many attempts', $errors);
    }

    public function test_a_successful_lookup_clears_the_rate_limit(): void
    {
        $order = $this->order();

        foreach (range(1, 3) as $ignored) {
            $this->post(route('track.find'), ['order_number' => 'FC/0000/00000', 'email' => 'x@example.com']);
        }

        $this->post(route('track.find'), [
            'order_number' => $order->order_number,
            'email' => 'guest@example.com',
        ])->assertRedirect();

        $this->assertSame(0, RateLimiter::attempts('track-order|127.0.0.1'));
    }

    /* ----------------------------------------------------- the status */

    public function test_the_status_page_opens_with_the_token_alone(): void
    {
        $order = $this->order();

        $this->get(route('track.status', $order->public_token))
            ->assertOk()
            ->assertSee($order->order_number)
            ->assertSee('Steel Almirah')
            ->assertSee('Cash on delivery');
    }

    public function test_an_unknown_token_is_a_404(): void
    {
        $this->get(route('track.status', str_repeat('a', 32)))->assertNotFound();
    }

    /**
     * A tracking link can be forwarded, or left in a shared browser. It shows
     * where the order is, not where the customer lives.
     */
    public function test_the_status_page_does_not_expose_the_street_address_or_phone(): void
    {
        $order = $this->order();

        $this->get(route('track.status', $order->public_token))
            ->assertOk()
            ->assertSee('New Delhi')
            ->assertSee('110001')
            ->assertDontSee('12 Green Park')
            ->assertDontSee('9811122233');
    }

    public function test_the_status_page_asks_not_to_be_indexed(): void
    {
        $order = $this->order();

        $this->get(route('track.status', $order->public_token))
            ->assertOk()
            ->assertSee('noindex, nofollow', false);
    }

    public function test_the_timeline_marks_how_far_the_order_has_got(): void
    {
        $order = $this->order(['status' => 'shipped']);

        $html = $this->get(route('track.status', $order->public_token))->assertOk()->getContent();

        // Everything up to "On its way" is done; it is the current step.
        $this->assertStringContainsString('is-current', $html);
        $this->assertStringContainsString('On its way', $html);
        $this->assertSame(3, substr_count($html, 'fc-timeline__step is-done'));
    }

    public function test_a_cancelled_order_gets_its_own_short_timeline(): void
    {
        $order = $this->order(['status' => 'cancelled']);

        $html = $this->get(route('track.status', $order->public_token))->assertOk()->getContent();

        $this->assertStringContainsString('is-stopped', $html);
        $this->assertStringContainsString('Cancelled', $html);
        $this->assertStringNotContainsString('On its way', $html);
    }

    public function test_the_payment_state_is_shown(): void
    {
        $order = $this->order([
            'payment_method' => 'razorpay',
            'payment_status' => 'paid',
            'amount_paid' => 12500,
            'amount_due' => 0,
        ]);

        $this->get(route('track.status', $order->public_token))
            ->assertOk()
            ->assertSee('Paid')
            ->assertSee('Online');
    }

    /* -------------------------------------------------------- the email */

    public function test_a_guests_confirmation_links_to_the_tracking_page(): void
    {
        $order = $this->order();

        $rendered = (new OrderConfirmation($order))->render();

        $this->assertStringContainsString(route('track.status', $order->public_token), $rendered);
        $this->assertStringContainsString('Track your order', $rendered);
    }

    public function test_a_customers_confirmation_still_links_to_their_account(): void
    {
        $customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);

        $order = $this->order(['customer_id' => $customer->id, 'customer_email' => $customer->email]);

        $rendered = (new OrderConfirmation($order))->render();

        $this->assertStringContainsString(route('account.order', $order->public_token), $rendered);
        $this->assertStringNotContainsString(route('track.status', $order->public_token), $rendered);
    }

    /* ------------------------------------------------------- signposting */

    public function test_the_footer_links_to_order_tracking(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('track.form'), false);
    }
}
