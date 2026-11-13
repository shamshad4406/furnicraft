<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recently_viewed', function (Blueprint $table) {
            $table->id();
            // Guests are tracked by session; once a customer signs in their id
            // is filled so the list follows them across devices (Phase 4).
            $table->string('session_id', 100)->index();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['session_id', 'product_id']);
            $table->index(['customer_id', 'viewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recently_viewed');
    }
};
