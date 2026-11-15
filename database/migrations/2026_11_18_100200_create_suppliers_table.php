<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who we buy from, and what we have ordered from them.
 *
 * A purchase order is shaped like a quotation turned around: the same lines,
 * the same inclusive pricing, but we are the ones paying. Receiving against
 * one is what puts stock on the shelf, and it goes through the same ledger
 * as everything else.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            $table->string('name', 160);
            $table->string('code', 30)->unique()->comment('Short reference used on purchase orders');

            $table->string('contact_person', 120)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('alternate_phone', 20)->nullable();

            $table->string('address', 300)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('pincode', 6)->nullable();

            $table->string('gst_number', 20)->nullable();
            $table->string('pan_number', 20)->nullable();

            $table->unsignedSmallInteger('payment_terms_days')->nullable()->comment('Net days');
            $table->unsignedSmallInteger('lead_time_days')->nullable()->comment('How long they usually take');

            $table->string('notes', 1000)->nullable();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();

            $table->string('number', 40)->unique();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();

            $table->enum('status', ['draft', 'ordered', 'partial', 'received', 'cancelled'])
                ->default('draft')
                ->index();

            $table->date('expected_on')->nullable();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_total', 12, 2)->default(0);
            $table->decimal('other_charges', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2)->default(0);

            $table->string('supplier_invoice_number', 60)->nullable();
            $table->string('notes', 1000)->nullable();
            $table->string('cancel_reason', 300)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('ordered_at')->nullable();
            $table->timestamp('received_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->string('name', 255);
            $table->string('sku', 64)->nullable();

            $table->unsignedInteger('quantity');
            // Deliveries arrive in parts, so what has actually turned up is
            // counted separately from what was ordered.
            $table->unsignedInteger('received_quantity')->default(0);

            $table->decimal('unit_cost', 12, 2);
            $table->decimal('gst_rate', 5, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('line_total', 12, 2);

            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('suppliers');
    }
};
