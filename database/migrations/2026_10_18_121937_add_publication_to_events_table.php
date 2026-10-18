<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — splits the "staff hasn't looked at this yet" state from
 * the "I'm still writing" state on events. Today `EventStatus::Draft`
 * carries both meanings, which is why the Fix-these dashboard flags
 * director drafts for staff and why the Inbox Events tab counts the
 * same rows a director still thinks of as "working on it".
 *
 * Publication states:
 *   draft           — director is writing, staff ignores
 *   pending_review  — director has asked staff to publish
 *   published       — on the public calendar
 *   rejected        — staff said no; director sees the reason
 *
 * Backfill: anything currently NOT Draft is `published`; Draft rows
 * created more than 24 h ago are `pending_review` (staff should have
 * seen them); fresher Drafts stay `draft`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('publication', 24)->default('draft')->after('status');
            $table->index('publication');
        });

        // Backfill. Postgres and SQLite both accept parameterised raw
        // updates, no dialect switching needed.
        DB::table('events')
            ->where('status', '!=', 'draft')
            ->update(['publication' => 'published']);

        DB::table('events')
            ->where('status', 'draft')
            ->where('created_at', '<', now()->subDay())
            ->update(['publication' => 'pending_review']);
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['publication']);
            $table->dropColumn('publication');
        });
    }
};
