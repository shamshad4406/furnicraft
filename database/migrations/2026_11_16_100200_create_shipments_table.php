<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A consignment sent out against an order.
 *
 * One order can ship in several parts — a bed today, the matching almirah when
 * it is back in stock — so this is a separate table rather than columns on the
 * order. The order's own status reflects the whole, these record the parts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('consignment_number', 40)->unique()->comment('Ours, shown to the customer');

            $table->string('courier', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('tracking_url', 500)->nullable();

            $table->enum('status', ['pending', 'dispatched', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned'])
                ->default('pending')
                ->index();

            $table->unsignedSmallInteger('packages')->default(1);
            $table->decimal('weight', 8, 2)->nullable();

            // Who is carrying it, when own staff deliver rather than a courier.
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->date('expected_delivery_on')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->string('received_by', 120)->nullable()->comment('Who signed for it');
            $table->string('notes', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
        });

        Schema::create('shipment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            $table->timestamps();

            // One line of an order appears at most once per shipment.
            $table->unique(['shipment_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_items');
        Schema::dropIfExists('shipments');
    }
};
