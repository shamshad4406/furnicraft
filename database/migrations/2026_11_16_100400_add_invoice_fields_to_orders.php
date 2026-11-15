<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Invoice identity, and the place-of-supply facts the GST breakdown depends on.
 *
 * These are frozen onto the order when the invoice is raised. A tax invoice is
 * a legal document: once issued, it must keep saying the same thing even if
 * the company's GST registration or state is changed in Settings later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('invoice_number', 40)->nullable()->unique()->after('order_number');
            $table->timestamp('invoiced_at')->nullable()->after('confirmed_at');

            // Intra-state is CGST + SGST, inter-state is IGST. Decided by
            // comparing the seller's state with the delivery state, and kept
            // so a reprint never changes the split.
            $table->boolean('is_interstate')->default(false)->after('tax_total');
            $table->string('place_of_supply', 60)->nullable()->after('is_interstate');
            $table->string('place_of_supply_code', 2)->nullable()->after('place_of_supply');

            $table->string('seller_gst_number', 20)->nullable()->after('place_of_supply_code');

            $table->timestamp('delivered_at')->nullable()->after('cancelled_at');
            $table->string('cancellation_reason', 300)->nullable()->after('delivered_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancellation_reason')
                ->constrained('users')->nullOnDelete()
                ->comment('Null when the customer cancelled it themselves');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'invoice_number', 'invoiced_at',
                'is_interstate', 'place_of_supply', 'place_of_supply_code', 'seller_gst_number',
                'delivered_at', 'cancellation_reason',
            ]);
        });
    }
};
