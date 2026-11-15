<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Homepage slides and promotional strips, run from the admin panel.
 *
 * Until now these were hardcoded in ShowcaseCatalog, which meant a seasonal
 * sale banner needed a developer. The placements let one table serve the
 * hero slider and the smaller promo strips without three near-identical
 * tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();

            $table->enum('placement', ['hero', 'promo', 'strip'])->default('hero')->index();

            $table->string('title', 160);
            $table->string('highlight', 160)->nullable()->comment('The part shown in the accent colour');
            $table->string('subtitle', 255)->nullable();

            $table->string('cta_label', 60)->nullable();
            $table->string('cta_url', 500)->nullable();

            $table->string('image_path', 500)->nullable();
            $table->string('mobile_image_path', 500)->nullable()
                ->comment('Optional portrait crop; the main image is used when empty');
            $table->string('image_alt', 160)->nullable();

            // Scheduling, so a sale banner can be set up in advance and stop
            // on its own rather than needing somebody to remember.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable()->index();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedSmallInteger('sort_order')->default(0);

            // Worth knowing which banner earns its place.
            $table->unsignedInteger('click_count')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['placement', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};
