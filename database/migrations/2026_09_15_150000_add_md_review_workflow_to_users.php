<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the match-director review workflow.
 *
 * State machine:
 *   - Never applied       -> md_requested_at = null, is_match_director = false
 *   - Pending review      -> md_requested_at set,  is_match_director = false, md_rejected_at null
 *   - Approved by staff   -> md_approved_at set,   is_match_director = true
 *   - Rejected by staff   -> md_rejected_at set,   is_match_director = false
 *
 * `is_match_director` remains the single source of truth for panel access
 * (see User::canAccessPanel). The three timestamps are audit + UI state.
 *
 * Grandfathering: every existing MD (is_match_director = true, created
 * under the previous open-signup flow) is auto-approved with both
 * timestamps set to the user's created_at, so their history looks
 * consistent instead of "approved on the day we shipped the migration".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('md_requested_at')->nullable()->after('is_match_director');
            $table->timestamp('md_approved_at')->nullable()->after('md_requested_at');
            $table->timestamp('md_rejected_at')->nullable()->after('md_approved_at');
            $table->text('md_rejection_reason')->nullable()->after('md_rejected_at');
        });

        // Back-fill grandfathered MDs. Raw update (not an Eloquent loop)
        // so schema state at migration time is what matters, not any
        // future changes to the model.
        DB::table('users')
            ->where('is_match_director', true)
            ->whereNull('md_requested_at')
            ->update([
                'md_requested_at' => DB::raw('created_at'),
                'md_approved_at' => DB::raw('created_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'md_requested_at',
                'md_approved_at',
                'md_rejected_at',
                'md_rejection_reason',
            ]);
        });
    }
};
