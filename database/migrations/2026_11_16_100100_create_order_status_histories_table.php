<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every status an order has been through, who moved it and why.
 *
 * The activity log records that a column changed; this records the decision —
 * the note the staff member typed, and whether the customer was told. When a
 * customer asks why their order sat for a week, this is the answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->string('from_status', 20)->nullable()->comment('Null for the first entry');
            $table->string('to_status', 20);

            $table->string('note', 500)->nullable();

            // Null when the system moved it, e.g. a payment webhook.
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('changed_by_name', 120)->nullable()->comment('Kept for when the staff account is deleted');

            $table->boolean('customer_notified')->default(false);

            $table->timestamps();

            $table->index(['order_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
