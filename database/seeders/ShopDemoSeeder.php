<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderNumberService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Phase 4 demo data: a customer with saved addresses, a set of coupons, and
 * one completed order, so the storefront account area and the admin coupon
 * and order screens have something real to show on a fresh install.
 *
 * Idempotent — safe to re-run; it never touches an existing password.
 */
class ShopDemoSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $coupons = [
        [
            'code' => 'WELCOME500', 'description' => 'Flat Rs.500 off your first order',
            'type' => 'fixed', 'value' => 500, 'min_order_value' => 5000,
            'usage_limit' => 500, 'usage_limit_per_customer' => 1,
        ],
        [
            'code' => 'FESTIVE10', 'description' => '10% off, up to Rs.3,000',
            'type' => 'percentage', 'value' => 10, 'max_discount' => 3000,
            'min_order_value' => 10000, 'usage_limit' => null, 'usage_limit_per_customer' => 2,
        ],
        [
            'code' => 'FREEDELIVERY', 'description' => 'Free delivery on orders above Rs.15,000',
            'type' => 'free_delivery', 'value' => 0, 'min_order_value' => 15000,
            'usage_limit' => null, 'usage_limit_per_customer' => null,
        ],
    ];

    public function run(): void
    {
        $customer = $this->customer();
        $this->addresses($customer);
        $this->couponSet();
        $this->order($customer);
    }

    protected function customer(): Customer
    {
        $customer = Customer::withTrashed()->firstOrNew(['email' => 'customer@furnicraft.in']);

        $customer->fill([
            'name' => 'Ravi Kumar',
            'phone' => '9811122233',
            'is_active' => true,
        ]);

        if (! $customer->exists) {
            $customer->password = $this->password();
            $customer->email_verified_at = now();
        }

        $customer->deleted_at = null;
        $customer->save();

        return $customer;
    }

    /**
     * Mirrors AdminUserSeeder: well-known locally, random anywhere else.
     */
    protected function password(): string
    {
        $configured = env('SEED_CUSTOMER_PASSWORD');

        if (filled($configured)) {
            return $configured;
        }

        if (app()->environment('local', 'testing')) {
            return 'Furni@2026';
        }

        $generated = Str::password(20);
        $this->command?->warn('Generated password for customer@furnicraft.in: '.$generated);

        return $generated;
    }

    protected function addresses(Customer $customer): void
    {
        $rows = [
            [
                'type' => 'home', 'label' => 'Home', 'name' => 'Ravi Kumar',
                'phone' => '9811122233', 'line1' => '12 Green Park Extension',
                'line2' => 'Near Hauz Khas Metro', 'landmark' => 'Opposite the park',
                'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110016',
                'is_default_shipping' => true, 'is_default_billing' => true,
            ],
            [
                'type' => 'office', 'label' => 'Office', 'name' => 'Ravi Kumar',
                'phone' => '9811122233', 'line1' => '4th Floor, Nehru Place Tower',
                'line2' => null, 'landmark' => 'Above the bank',
                'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110019',
                'is_default_shipping' => false, 'is_default_billing' => false,
            ],
        ];

        foreach ($rows as $row) {
            Address::firstOrCreate(
                ['customer_id' => $customer->id, 'line1' => $row['line1']],
                $row + ['customer_id' => $customer->id]
            );
        }
    }

    protected function couponSet(): void
    {
        foreach ($this->coupons as $row) {
            Coupon::firstOrCreate(
                ['code' => $row['code']],
                $row + ['is_active' => true, 'expires_at' => now()->addMonths(6)]
            );
        }
    }

    /**
     * One delivered order so "My orders" is not empty on a fresh install.
     */
    protected function order(Customer $customer): void
    {
        if ($customer->orders()->exists()) {
            return;
        }

        $products = Product::where('status', 'active')->limit(2)->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No products yet — skipping the demo order. Run CatalogSeeder first.');

            return;
        }

        $address = [
            'name' => 'Ravi Kumar', 'phone' => '9811122233',
            'line1' => '12 Green Park Extension', 'line2' => 'Near Hauz Khas Metro',
            'landmark' => 'Opposite the park', 'city' => 'New Delhi',
            'state' => 'Delhi', 'pincode' => '110016',
        ];

        $lines = $products->map(function (Product $product) {
            $quantity = 1;
            $price = (float) $product->price;
            $subtotal = $price * $quantity;
            $rate = (float) ($product->gst_rate ?: 18);

            return [
                'product_id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'hsn_code' => $product->hsn_code,
                'quantity' => $quantity,
                'unit_price' => $price,
                'line_subtotal' => $subtotal,
                'gst_rate' => $rate,
                'tax_amount' => round($subtotal - ($subtotal / (1 + $rate / 100)), 2),
                'line_total' => $subtotal,
                'weight' => $product->weight,
            ];
        });

        $subtotal = (float) $lines->sum('line_subtotal');
        $tax = (float) $lines->sum('tax_amount');

        $order = Order::create([
            'order_number' => app(OrderNumberService::class)->next(),
            'idempotency_key' => 'demo-'.Str::lower(Str::random(16)),
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'shipping_address' => $address,
            'billing_address' => $address,
            'shipping_pincode' => $address['pincode'],
            'subtotal' => $subtotal,
            'discount' => 0,
            'delivery_charge' => 0,
            'tax_total' => $tax,
            'grand_total' => $subtotal,
            'amount_paid' => $subtotal,
            'amount_due' => 0,
            'payment_method' => 'razorpay',
            'payment_status' => 'paid',
            'status' => 'delivered',
            'delivery_days' => 4,
            'placed_at' => now()->subDays(6),
            'confirmed_at' => now()->subDays(6),
            'confirmation_sent_at' => now()->subDays(6),
            // Without this the order says "Delivered" and yet has no delivery
            // date and no return window — isReturnable() needs the timestamp,
            // not the status, so the demo order's "request a return" link
            // simply 404s. Dated inside the return window on purpose, so the
            // Phase 5 return flow is actually demonstrable.
            'delivered_at' => now()->subDays(2),
        ]);

        foreach ($lines as $line) {
            $order->items()->create($line);
        }

        $order->payments()->create([
            'gateway' => 'razorpay',
            'gateway_order_id' => 'order_demo'.Str::random(8),
            'gateway_payment_id' => 'pay_demo'.Str::random(8),
            'amount' => $subtotal,
            'currency' => 'INR',
            'status' => 'captured',
            'method' => 'upi',
            'payload' => ['note' => 'Seeded demo payment'],
            'paid_at' => now()->subDays(12),
        ]);

        $this->command?->info('Seeded demo order '.$order->order_number.' for '.$customer->email.'.');
    }
}
