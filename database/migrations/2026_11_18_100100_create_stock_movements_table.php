<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every change to stock, and why.
 *
 * Until now `products.stock_quantity` was a number that moved with nothing
 * to explain it: a sale, a return, somebody editing the field, a CSV import.
 * When the shelf and the screen disagree — and they always do eventually —
 * there was no way to find out where it went wrong.
 *
 * This is the ledger. `balance_after` is stored so it reads like a bank
 * statement: you can point at a row and say the count was this afterwards,
 * without re-adding everything above it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();

            $table->enum('type', [
                'opening',     // what was on the shelf when the system started
                'sale',        // an order was paid for
                'return',      // goods came back and were fit to sell
                'purchase',    // a delivery from a supplier
                'adjustment',  // a stock count found a difference
                'damage',      // written off
                'correction',  // a mistake in this ledger being put right
            ])->index();

            // Signed: negative leaves the shelf, positive arrives on it.
            $table->integer('quantity');
            $table->integer('balance_after');

            // What caused it, kept loosely so an order, a return or a
            // purchase order can all point here without three nullable keys.
            $table->string('reference_type', 40)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('reference_label', 60)->nullable()->comment('What to show: an order number, say');

            $table->decimal('unit_cost', 12, 2)->nullable()->comment('What it cost us, on a purchase');

            $table->string('reason', 200)->nullable();

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120)->nullable()->comment('Kept for when the staff account is deleted');

            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            // Below this, the product shows up on the low-stock list. Null
            // falls back to the shop-wide default in Settings.
            $table->unsignedInteger('low_stock_threshold')->nullable()->after('stock_quantity');
            $table->decimal('cost_price', 12, 2)->nullable()->after('low_stock_threshold')
                ->comment('What we pay for it, for stock valuation');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['low_stock_threshold', 'cost_price']);
        });

        Schema::dropIfExists('stock_movements');
    }
};
