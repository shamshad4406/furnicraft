<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The mailing list.
 *
 * Two things here are not decoration. `unsubscribe_token` means every mail
 * can carry a one-click way out, which is both the law in most places and
 * the difference between a list and a complaint. `confirmed_at` supports
 * double opt-in, so somebody typing a stranger's address into the footer
 * does not sign them up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();

            $table->string('email', 255)->unique();
            $table->string('name', 120)->nullable();

            $table->enum('status', ['pending', 'subscribed', 'unsubscribed', 'bounced'])
                ->default('pending')
                ->index();

            $table->string('unsubscribe_token', 64)->unique();
            $table->string('confirm_token', 64)->nullable()->unique();

            $table->string('source', 40)->default('footer')->comment('Where they signed up');

            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->string('unsubscribe_reason', 200)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
