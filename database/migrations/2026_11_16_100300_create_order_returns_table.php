<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A customer asking to send something back, and what came of it.
 *
 * Returns and warranty claims share this table: both are "something is wrong
 * with what arrived", both are raised by the customer, approved by staff, and
 * end in a refund, a replacement or a refusal. The `type` column keeps them
 * apart where the handling differs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('reference', 40)->unique();
            $table->string('public_token', 64)->unique();

            $table->enum('type', ['return', 'replacement', 'warranty'])->default('return');

            $table->enum('status', [
                'requested', 'approved', 'rejected', 'picked_up', 'received', 'refunded', 'replaced', 'closed',
            ])->default('requested')->index();

            $table->string('reason', 60);
            $table->text('details')->nullable();

            // What the customer asked for, in money. Settled on approval.
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->enum('refund_method', ['original', 'bank', 'store_credit'])->nullable();
            $table->string('refund_reference', 120)->nullable();

            $table->string('staff_note', 500)->nullable()->comment('Internal; never shown to the customer');
            $table->string('customer_message', 500)->nullable()->comment('The reason given back to the customer');

            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('resolved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['order_id', 'status']);
        });

        Schema::create('order_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_return_id')->constrained('order_returns')->cascadeOnDelete();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();

            $table->unsignedInteger('quantity');
            $table->decimal('refund_amount', 12, 2)->default(0);
            $table->boolean('restock')->default(false)->comment('Put it back into inventory on receipt');

            $table->timestamps();

            $table->unique(['order_return_id', 'order_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_return_items');
        Schema::dropIfExists('order_returns');
    }
};
