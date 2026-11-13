<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Second precision is not enough to order a visitor's history: several
 * products are easily opened within the same second, leaving the "most
 * recent first" order undefined. Milliseconds fix that.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recently_viewed', function (Blueprint $table) {
            $table->timestamp('viewed_at', 3)->useCurrent()->change();
        });
    }

    public function down(): void
    {
        Schema::table('recently_viewed', function (Blueprint $table) {
            $table->timestamp('viewed_at')->useCurrent()->change();
        });
    }
};
