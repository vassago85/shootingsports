<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds two related feature seams to the users table:
 *
 *   1. Email preferences + one-click unsubscribe (POPIA).
 *      Every user gets a permanent, unguessable unsubscribe_token that
 *      the unsubscribe route resolves without requiring auth. Category
 *      flags let a user silence marketing without losing transactional
 *      mail. Transactional emails (MD approval, enquiry notifications,
 *      etc.) ignore these flags — only marketing categories check.
 *
 *   2. No-CC 30-day Pro trial.
 *      When a user starts a trial we set plan = Pro and
 *      plan_expires_at = now + 30 days. HasPlan::isPro() naturally
 *      degrades to Free once the window passes, so no cron is needed
 *      for the actual downgrade. pro_trial_started_at is the sentinel
 *      that a user has "used their one trial" — checked by the
 *      StartProTrial component to block re-triggering.
 *
 * Both feature groups are unrelated in behaviour but land in one
 * migration because they share the same table and we're shipping them
 * in the same PR.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // === Email preferences ===
            // Master marketing flag. When false, EmailPreferences::canSend
            // returns false for any category other than 'transactional'.
            $table->boolean('emails_marketing_enabled')->default(true)->after('digest_frequency');

            // Per-category flags. Each false skips just that category —
            // the user can silence weekly digest without losing new-match
            // alerts, and vice versa.
            $table->boolean('emails_match_alerts_enabled')->default(true)->after('emails_marketing_enabled');
            $table->boolean('emails_weekly_digest_enabled')->default(false)->after('emails_match_alerts_enabled');
            $table->boolean('emails_product_updates_enabled')->default(true)->after('emails_weekly_digest_enabled');
            $table->boolean('emails_trial_nudges_enabled')->default(true)->after('emails_product_updates_enabled');

            // Audit trail for the master opt-out. Kept alongside a
            // human-readable source string ('email_link' | 'settings_page'
            // | 'admin') so POPIA subject-access-request replies can
            // reconstruct why we stopped emailing this user.
            $table->timestamp('unsubscribed_at')->nullable()->after('emails_trial_nudges_enabled');
            $table->string('unsubscribe_source', 32)->nullable()->after('unsubscribed_at');

            // One-click unsubscribe token. Random 40-char string,
            // permanent (never rotated) so old email footers keep
            // working forever. Unique so the route can look users up
            // in one query without any user_id disclosure.
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('unsubscribe_source');

            // === Trial ===
            // Sentinel: null = never trialed, timestamp = trial was started
            // on this date. Used to prevent re-triggering (a user only gets
            // one 30-day trial per account, ever).
            $table->timestamp('pro_trial_started_at')->nullable()->after('plan_cancelled_at');

            // Idempotency stamps for the two trial-nudge mailables so
            // the daily scheduler can safely re-run without spamming.
            $table->timestamp('pro_trial_ending_notified_at')->nullable()->after('pro_trial_started_at');
            $table->timestamp('pro_trial_ended_notified_at')->nullable()->after('pro_trial_ending_notified_at');
        });

        // Back-fill unsubscribe_token for every existing user. Done in
        // chunks so a large users table doesn't blow memory. Raw update
        // (not Eloquent) so this migration keeps working even if the
        // model changes later.
        DB::table('users')->whereNull('unsubscribe_token')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'unsubscribe_token' => Str::random(48),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['unsubscribe_token']);
            $table->dropColumn([
                'emails_marketing_enabled',
                'emails_match_alerts_enabled',
                'emails_weekly_digest_enabled',
                'emails_product_updates_enabled',
                'emails_trial_nudges_enabled',
                'unsubscribed_at',
                'unsubscribe_source',
                'unsubscribe_token',
                'pro_trial_started_at',
                'pro_trial_ending_notified_at',
                'pro_trial_ended_notified_at',
            ]);
        });
    }
};
