<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A priced offer, usually against an enquiry.
 *
 * Shaped deliberately like an order, because that is what it becomes: the
 * same totals, the same GST facts, the same address blocks. Converting is
 * then a copy rather than a re-interpretation, and a customer who accepts a
 * quotation gets exactly the figures they agreed to.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();

            $table->string('number', 40)->unique();
            $table->string('public_token', 64)->unique();

            $table->foreignId('enquiry_id')->nullable()->constrained('enquiries')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('customer_name', 120);
            $table->string('customer_email', 255);
            $table->string('customer_phone', 20);
            $table->string('gst_number', 20)->nullable();

            $table->json('shipping_address')->nullable();
            $table->json('billing_address')->nullable();

            $table->enum('status', ['draft', 'sent', 'accepted', 'declined', 'expired', 'converted'])
                ->default('draft')
                ->index();

            $table->date('valid_until')->nullable()->index();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('delivery_charge', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            // The same place-of-supply facts an invoice carries, frozen when
            // the quotation is sent so the price the customer saw is the
            // price they are held to.
            $table->boolean('is_interstate')->default(false);
            $table->string('place_of_supply', 60)->nullable();
            $table->string('place_of_supply_code', 2)->nullable();

            $table->unsignedSmallInteger('delivery_days')->nullable();

            $table->text('terms')->nullable();
            $table->string('notes', 1000)->nullable()->comment('Internal');
            $table->string('customer_note', 1000)->nullable()->comment('Shown on the quotation');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->string('decline_reason', 300)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'valid_until']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained('quotations')->cascadeOnDelete();

            // Null for a custom piece that is not in the catalogue — the
            // whole point of quoting rather than selling off the shelf.
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('name', 255);
            $table->string('description', 1000)->nullable();
            $table->string('sku', 64)->nullable();
            $table->string('hsn_code', 20)->nullable();

            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();

            $table->index(['quotation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
