<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();

            $table->string('name');
            $table->string('slug')->unique();
            $table->string('sku', 64)->unique();

            $table->string('short_description', 500)->nullable();
            $table->longText('description')->nullable();

            // Furniture attributes
            $table->string('material')->nullable();
            $table->string('color')->nullable();
            $table->string('dimensions')->nullable()->comment('e.g. 180 x 90 x 75 cm');
            $table->decimal('weight', 8, 2)->nullable()->comment('kg');
            $table->string('warranty')->nullable();

            // Tax
            $table->string('hsn_code', 20)->nullable();
            $table->decimal('gst_rate', 5, 2)->default(18);

            // Pricing — sale_price, when set, is the effective price
            $table->decimal('price', 12, 2);
            $table->decimal('sale_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0)
                ->comment('Simple products only; variant stock lives on product_variants. Managed properly in Phase 7.');

            // SEO
            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            // Flags
            $table->boolean('custom_order_allowed')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_best_seller')->default(false)->index();
            $table->boolean('is_new_arrival')->default(false)->index();

            $table->enum('status', ['draft', 'active', 'inactive'])->default('draft')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['category_id', 'status']);
            $table->index(['status', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
