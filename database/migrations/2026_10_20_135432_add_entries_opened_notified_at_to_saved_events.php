<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entry Alerts: idempotency flag on the saved_events pivot. Set by
 * EventObserver the first time an event's status transitions to
 * EntriesOpen so that if staff flip the status back and forward
 * (planned → entries_open → planned → entries_open) the shooter is
 * only ever paged once per event.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_events', function (Blueprint $table): void {
            $table->timestamp('entries_opened_notified_at')->nullable()->after('event_id');
        });
    }

    public function down(): void
    {
        Schema::table('saved_events', function (Blueprint $table): void {
            $table->dropColumn('entries_opened_notified_at');
        });
    }
};
