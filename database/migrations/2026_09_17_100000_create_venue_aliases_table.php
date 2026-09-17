<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_aliases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();

            // Human-readable alternate name of the range as it appears
            // in imported calendars, seed data, or old slugs after a
            // merge. Not unique on its own — the same string can point
            // at different canonical venues in different provinces.
            $table->string('name');

            // Slug form used to preserve link equity when a venue is
            // merged into another one. Unique across the table so a
            // public route lookup on `/ranges/{slug}` can resolve
            // deterministically to the canonical venue and 301.
            $table->string('slug')->nullable()->unique();

            // Source of the alias: `merge` for post-merge redirects,
            // `import` for names harvested from third-party calendars,
            // `staff` for manual entries in Filament. Kept as a plain
            // string (no enum yet) so seeders / imports can add new
            // origins without a migration.
            $table->string('source')->default('staff');

            $table->timestamps();

            // Same alias name can only appear once against a given
            // canonical venue.
            $table->unique(['venue_id', 'name']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_aliases');
    }
};
