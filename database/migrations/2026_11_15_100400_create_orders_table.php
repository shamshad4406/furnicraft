<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 40)->unique();

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            // Captured at order time so a later profile edit cannot rewrite history.
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 20);
            $table->string('gst_number', 20)->nullable();

            $table->json('shipping_address');
            $table->json('billing_address');
            $table->string('shipping_pincode', 6)->index();

            // Money. Every figure is stored, never recomputed from the cart.
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);
            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('amount_due', 12, 2)->default(0);

            $table->foreignId('coupon_id')->nullable()->constrained('coupons')->nullOnDelete();
            $table->string('coupon_code', 60)->nullable();

            $table->enum('payment_method', ['razorpay', 'cod'])->default('razorpay');
            $table->enum('payment_status', ['pending', 'paid', 'partly_paid', 'failed', 'refunded'])
                ->default('pending')->index();

            // The order lifecycle is built out in Phase 5; Phase 4 only ever
            // sets pending / confirmed / cancelled.
            $table->enum('status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'returned'])
                ->default('pending')->index();

            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->string('notes', 500)->nullable();

            $table->timestamp('placed_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'created_at']);
            $table->index(['payment_status', 'status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            // Snapshot: the invoice must still read correctly if the catalog changes.
            $table->string('name');
            $table->string('sku', 64);
            $table->string('variant_label')->nullable();
            $table->string('hsn_code', 20)->nullable();

            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->decimal('weight', 8, 2)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
