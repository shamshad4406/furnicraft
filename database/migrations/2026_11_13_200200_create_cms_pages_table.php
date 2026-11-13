<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content')->nullable();

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 500)->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->boolean('show_in_footer')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);

            // Seeded pages the storefront links to directly and must not lose.
            $table->boolean('is_system')->default(false);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
