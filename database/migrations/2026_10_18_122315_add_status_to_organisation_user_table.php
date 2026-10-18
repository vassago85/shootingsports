<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — extend the existing `organisation_user` pivot (which
 * already carries `role` + `granted_by` + `granted_at`) with the
 * request / review flow that will replace `users.is_match_director`
 * + `md_status` + Claims against organisations.
 *
 * Every existing row was granted directly by staff (no request
 * flow), so backfill all of them to `status = approved` with
 * `approved_at = granted_at`. The Phase B backfill command then adds
 * one row per (match director, host organisation) pair.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisation_user', function (Blueprint $table): void {
            $table->string('status', 16)->default('approved')->after('role');
            $table->timestamp('requested_at')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('requested_at');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->string('rejection_reason', 500)->nullable()->after('rejected_at');

            $table->index(['user_id', 'status']);
            $table->index(['organisation_id', 'status']);
        });

        // Mirror granted_at onto approved_at for existing rows so
        // "when did this membership become live?" has one answer.
        DB::statement('UPDATE organisation_user SET approved_at = granted_at WHERE approved_at IS NULL');
    }

    public function down(): void
    {
        Schema::table('organisation_user', function (Blueprint $table): void {
            $table->dropIndex(['user_id', 'status']);
            $table->dropIndex(['organisation_id', 'status']);
            $table->dropColumn([
                'status', 'requested_at', 'approved_at',
                'rejected_at', 'rejection_reason',
            ]);
        });
    }
};
