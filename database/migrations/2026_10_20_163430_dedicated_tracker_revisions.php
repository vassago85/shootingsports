<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dedicated-status tracker goes from free-text to licence-ready.
 *
 *  - Drops `dedicated_renewal_on` (the cycle is now config-driven,
 *    ending 31 October by default — no per-user renewal date needed).
 *  - Adds `dedicated_membership_number`, `dedicated_status_number`
 *    and `dedicated_category` so the printable report has what SAPS
 *    DFO expects on a supporting document.
 *  - Adds `dedicated_last_reminded_cycle_end` so the daily reminder
 *    command is idempotent — one mail per cycle end, not seven.
 *  - Adds `emails_dedicated_reminders_enabled` so users can silence
 *    the cycle reminders without unsubscribing from everything.
 *  - Migrates existing free-text body values to the new code set
 *    (nrapa / natshoot / fosa). Exact matches map; anything else is
 *    nulled so the user re-selects from the dropdown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('dedicated_membership_number', 60)->nullable()->after('dedicated_body');
            $table->string('dedicated_status_number', 60)->nullable()->after('dedicated_membership_number');
            $table->string('dedicated_category', 20)->nullable()->after('dedicated_status_number');
            $table->date('dedicated_last_reminded_cycle_end')->nullable()->after('dedicated_required_count');
            $table->boolean('emails_dedicated_reminders_enabled')->default(true)->after('emails_trial_nudges_enabled');
        });

        // Normalise the existing free-text accrediting-body values to
        // the new code set. Match case-insensitively so "nrapa",
        // "NRAPA" and "Nrapa" all map; drop everything else to null
        // so the next save forces a selection from the dropdown.
        $map = [
            'nrapa' => 'nrapa',
            'natshoot' => 'natshoot',
            'fosa' => 'fosa',
        ];

        DB::table('users')
            ->whereNotNull('dedicated_body')
            ->get(['id', 'dedicated_body'])
            ->each(function (object $row) use ($map): void {
                $normalised = strtolower(trim((string) $row->dedicated_body));
                DB::table('users')
                    ->where('id', $row->id)
                    ->update(['dedicated_body' => $map[$normalised] ?? null]);
            });

        // Backfill required count to the regulation floor (2) for
        // anyone who had set the field below 2 or left it null —
        // the new form enforces min:2.
        DB::table('users')
            ->where(function ($q): void {
                $q->whereNull('dedicated_required_count')->orWhere('dedicated_required_count', '<', 2);
            })
            ->whereNotNull('dedicated_body')
            ->update(['dedicated_required_count' => 2]);

        // Drop the renewal-date column last — nothing above reads it,
        // and dropping it before the body normalisation above would
        // still work but is harder to roll back cleanly.
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('dedicated_renewal_on');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->date('dedicated_renewal_on')->nullable()->after('dedicated_body');
            $table->dropColumn([
                'dedicated_membership_number',
                'dedicated_status_number',
                'dedicated_category',
                'dedicated_last_reminded_cycle_end',
                'emails_dedicated_reminders_enabled',
            ]);
        });
    }
};
