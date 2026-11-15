<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Someone asking a question before they buy.
 *
 * Four kinds share this table because the handling is the same — a question
 * arrives, staff answer it, and sometimes it becomes a quotation. What
 * differs is only which fields are filled in: a product enquiry names a
 * product, a custom request describes furniture that does not exist yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();

            $table->string('reference', 40)->unique();
            $table->string('public_token', 64)->unique();

            $table->enum('type', ['general', 'product', 'custom', 'bulk'])->default('general')->index();

            // Both nullable: a walk-in has no account, and a custom request
            // is for something not in the catalogue.
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();

            $table->string('name', 120);
            $table->string('email', 255)->index();
            $table->string('phone', 20)->index();
            $table->string('city', 120)->nullable();
            $table->string('pincode', 6)->nullable();

            $table->string('subject', 180)->nullable();
            $table->text('message');

            // What they are after, when they need it, what they can spend.
            $table->unsignedInteger('quantity')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->date('required_by')->nullable();

            $table->enum('status', ['new', 'in_progress', 'quoted', 'converted', 'closed', 'spam'])
                ->default('new')
                ->index();

            $table->enum('source', ['web', 'phone', 'whatsapp', 'walk_in', 'email'])->default('web');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();

            $table->string('admin_note', 1000)->nullable()->comment('Internal; never shown to the customer');
            $table->string('close_reason', 300)->nullable();

            // Kept for the spam check and for answering "who sent this".
            $table->string('ip_address', 45)->nullable();

            $table->timestamp('responded_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('enquiry_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();

            $table->string('path', 500);
            $table->string('original_name', 255)->nullable();
            $table->unsignedInteger('size')->nullable();

            $table->timestamps();
        });

        /**
         * The conversation. Staff replies that were emailed, and notes of
         * phone calls — so the next person to pick the enquiry up can see
         * what has already been said.
         */
        Schema::create('enquiry_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('enquiries')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_name', 120)->nullable()->comment('Kept for when the staff account is deleted');

            $table->enum('channel', ['email', 'phone', 'whatsapp', 'note'])->default('email');

            $table->text('body');

            $table->timestamp('emailed_at')->nullable();

            $table->timestamps();

            $table->index(['enquiry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiry_replies');
        Schema::dropIfExists('enquiry_attachments');
        Schema::dropIfExists('enquiries');
    }
};
