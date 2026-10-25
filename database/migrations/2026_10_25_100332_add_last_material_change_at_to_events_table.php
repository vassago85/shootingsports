<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tracks the moment a match had a material change — a shift in
 * date, venue, or status — so a shooter who saved the match before
 * that moment can be shown a "Something changed" badge on their
 * home feed. Non-material edits (description tweaks, banner swap,
 * fee bump) deliberately don't touch this column so the badge is
 * meaningful rather than noisy.
 *
 * Nullable: for existing matches we start the clock at never,
 * which the client reads as "no changes since you saved". Set by
 * App\Observers\EventObserver on dirty writes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('last_material_change_at')->nullable()->after('needs_review');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn('last_material_change_at');
        });
    }
};
