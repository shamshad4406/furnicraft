<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Orders are addressed in URLs by a random token, not by their number.
 *
 * The order number is an admin-configurable display format — the default
 * "FC/2611/00001" contains slashes, which cannot be a URL segment. A token
 * also means order pages cannot be found by counting upwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('public_token', 32)->nullable()->unique()->after('idempotency_key');
        });

        foreach (DB::table('orders')->whereNull('public_token')->pluck('id') as $id) {
            DB::table('orders')->where('id', $id)->update(['public_token' => Str::lower(Str::random(32))]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });
    }
};
