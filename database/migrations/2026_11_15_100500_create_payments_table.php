<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();

            $table->enum('gateway', ['razorpay', 'cod', 'manual'])->default('razorpay');

            // Razorpay identifiers. The payment id is unique so a replayed
            // callback or a duplicated webhook cannot be recorded twice.
            $table->string('gateway_order_id')->nullable()->index();
            $table->string('gateway_payment_id')->nullable()->unique();
            $table->string('gateway_signature')->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('INR');

            $table->enum('status', ['created', 'authorized', 'captured', 'failed', 'refunded'])
                ->default('created')->index();

            $table->string('method', 40)->nullable()->comment('card, upi, netbanking…');
            $table->string('failure_reason', 500)->nullable();

            // Whole gateway response, for reconciliation and disputes.
            $table->json('payload')->nullable();

            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
        });

        // Every webhook Razorpay sends, stored before it is acted on, so a
        // replay is detected and nothing is processed twice.
        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('event', 80)->index();
            $table->string('gateway_event_id')->nullable()->unique();
            $table->string('gateway_payment_id')->nullable()->index();
            $table->json('payload');
            $table->boolean('processed')->default(false)->index();
            $table->string('result', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payments');
    }
};
