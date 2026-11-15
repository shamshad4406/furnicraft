<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What customers say about what they bought.
 *
 * Moderated by default: a review goes live only when somebody has read it.
 * An open review box on a furniture site fills with spam within a week, and
 * a shop that publishes it loses more trust than the reviews win.
 *
 * `is_verified` marks a review written by somebody who actually bought the
 * thing. That is the claim worth making, so it is derived from the order
 * rather than ticked by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            // Which purchase it is about, so "verified" can be proved.
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();

            $table->string('name', 120);
            $table->string('email', 255)->index();
            $table->string('city', 120)->nullable();

            $table->unsignedTinyInteger('rating');
            $table->string('title', 160)->nullable();
            $table->text('body');

            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])
                ->default('pending')
                ->index();

            $table->boolean('is_verified')->default(false);
            $table->boolean('is_featured')->default(false)
                ->comment('Shown in the testimonials strip on the homepage');

            $table->string('reply', 1000)->nullable()->comment('The shop answering, shown under the review');
            $table->timestamp('replied_at')->nullable();

            $table->string('moderation_note', 300)->nullable()->comment('Internal');
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();

            $table->unsignedInteger('helpful_count')->default(0);

            $table->string('ip_address', 45)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
            $table->index(['status', 'created_at']);
            // One review per person per product; a second is an edit, not a
            // second opinion.
            $table->unique(['product_id', 'email'], 'reviews_product_email_unique');
        });

        Schema::table('products', function (Blueprint $table) {
            // Kept on the product so listings can sort and filter by rating
            // without a subquery per row.
            $table->decimal('rating_average', 3, 2)->default(0)->index();
            $table->unsignedInteger('rating_count')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['rating_average', 'rating_count']);
        });

        Schema::dropIfExists('reviews');
    }
};
