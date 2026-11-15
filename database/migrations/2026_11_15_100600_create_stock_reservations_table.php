<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();

            $table->unsignedInteger('quantity');

            // Held while the customer pays; released automatically after this.
            $table->timestamp('expires_at')->index();
            $table->enum('status', ['held', 'committed', 'released'])->default('held')->index();
            $table->timestamp('released_at')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'product_variant_id', 'status'], 'stock_res_product_status_index');
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
