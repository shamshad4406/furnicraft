<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedSmallInteger('delivery_days')->default(5);

            // Charges are stored now and applied at checkout in Phase 4.
            $table->decimal('base_charge', 10, 2)->default(0);
            $table->decimal('per_kg_charge', 10, 2)->default(0);
            $table->decimal('free_above', 12, 2)->nullable()->comment('Order value above which delivery is free');

            $table->boolean('cod_available')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_zones');
    }
};
