<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pincodes', function (Blueprint $table) {
            $table->id();
            $table->string('pincode', 6)->unique();
            $table->string('city');
            $table->string('state');
            $table->foreignId('delivery_zone_id')->nullable()->constrained('delivery_zones')->nullOnDelete();

            // Overrides the zone default when set.
            $table->unsignedSmallInteger('delivery_days')->nullable();
            $table->boolean('is_serviceable')->default(true)->index();
            $table->boolean('cod_available')->default(true);
            $table->string('note')->nullable()->comment('Shown to the customer, e.g. "Delivery on weekdays only"');

            $table->timestamps();

            $table->index(['state', 'city']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pincodes');
    }
};
