<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a secondary-province preference to users. The mobile home
 * feed uses this to answer the question "show me matches in a
 * discipline I follow if they're in a province I care about" when
 * the primary home_province isn't the only region the shooter
 * travels to. Nullable because most users will stay with their
 * single home_province; the list is empty by default.
 *
 * Stored as JSON (not a pivot) because the values are a bounded
 * enum (eight provinces), ordering doesn't matter, and we never
 * query "which users have Gauteng in their extra list" — only ever
 * the reverse direction when scoring events for a specific user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('extra_provinces')->nullable()->after('home_province');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('extra_provinces');
        });
    }
};
