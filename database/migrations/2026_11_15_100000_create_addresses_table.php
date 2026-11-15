<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->enum('type', ['home', 'office', 'other'])->default('home');
            $table->string('label')->nullable()->comment('What the customer calls it');

            $table->string('name');
            $table->string('phone', 20);
            $table->string('alternate_phone', 20)->nullable();

            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('landmark')->nullable();
            $table->string('city');
            $table->string('state');
            $table->string('pincode', 6)->index();

            $table->boolean('is_default_shipping')->default(false);
            $table->boolean('is_default_billing')->default(false);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['customer_id', 'is_default_shipping']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
