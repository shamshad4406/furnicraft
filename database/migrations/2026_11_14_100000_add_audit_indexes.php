<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the query patterns actually use.
 *
 * Every soft-deleting model adds "deleted_at is null" to each query, and the
 * recently-viewed list is always read as "this session, newest first".
 */
return new class extends Migration
{
    /** @var array<string> tables that soft delete */
    protected array $softDeleting = ['products', 'categories', 'brands', 'cms_pages', 'users', 'customers'];

    public function up(): void
    {
        foreach ($this->softDeleting as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->index('deleted_at', $table.'_deleted_at_index');
            });
        }

        Schema::table('recently_viewed', function (Blueprint $table) {
            $table->index(['session_id', 'viewed_at'], 'recently_viewed_session_viewed_index');
        });
    }

    public function down(): void
    {
        foreach ($this->softDeleting as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_deleted_at_index');
            });
        }

        Schema::table('recently_viewed', function (Blueprint $table) {
            $table->dropIndex('recently_viewed_session_viewed_index');
        });
    }
};
