<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Extra services (categories) that a supplier offers beyond the
 * primary `category` column. Nullable JSON array of ProviderCategory
 * enum values so a shop can advertise as e.g. dealer + optics +
 * reloading without needing three separate provider rows.
 *
 * The primary `category` still drives the canonical directory landing
 * page and remains required; `services` is additive and optional.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            $table->json('services')->nullable()->after('category');
        });
    }

    public function down(): void
    {
        Schema::table('providers', function (Blueprint $table): void {
            $table->dropColumn('services');
        });
    }
};
