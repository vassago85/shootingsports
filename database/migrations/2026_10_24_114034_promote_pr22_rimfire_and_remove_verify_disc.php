<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two surgical data fixes for the Discipline filter list surfaced
 * by the mobile Calendar sheet.
 *
 * 1. PR22 Rimfire currently nests under Precision Rifle (parent_id
 *    = 2), so it never shows in the top-level filter sheet even
 *    though several SAPRF matches are tagged with it. Promote it
 *    to a top-level rifle discipline so the sheet lists it next
 *    to Precision Rifle and users can filter directly on it.
 *
 * 2. "Verify Disc" (slug `verify-%`) is a leaked test fixture that
 *    should never have reached production. Delete the row along
 *    with any lingering disciplinable pivots. `DisciplineController`
 *    also hides any future `verify-%` rows via
 *    `scopeExcludingVerificationFixtures()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('disciplines')
            ->where('slug', 'pr22-rimfire')
            ->update(['parent_id' => null]);

        $verifyIds = DB::table('disciplines')
            ->where('slug', 'like', 'verify-%')
            ->pluck('id')
            ->all();

        if ($verifyIds !== []) {
            DB::table('disciplinables')
                ->whereIn('discipline_id', $verifyIds)
                ->delete();

            DB::table('disciplines')
                ->whereIn('id', $verifyIds)
                ->delete();
        }
    }

    public function down(): void
    {
        // Irreversible: cannot resurrect a deleted test fixture or
        // reliably reparent a discipline that may have been edited
        // in Filament since this migration ran.
    }
};
