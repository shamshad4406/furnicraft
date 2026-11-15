<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('description')->nullable();

            $table->enum('type', ['percentage', 'fixed', 'free_delivery'])->default('percentage');
            $table->decimal('value', 12, 2)->default(0)->comment('Percent for percentage, amount for fixed, ignored for free_delivery');
            $table->decimal('max_discount', 12, 2)->nullable()->comment('Caps a percentage discount');

            $table->decimal('min_order_value', 12, 2)->default(0);

            $table->unsignedInteger('usage_limit')->nullable()->comment('Total redemptions allowed; null = unlimited');
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('order_id')->nullable();
            $table->string('email')->nullable()->comment('Guest checkout');
            $table->decimal('discount', 12, 2);
            $table->timestamps();

            $table->index(['coupon_id', 'customer_id']);
            $table->index(['coupon_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
        Schema::dropIfExists('coupons');
    }
};
