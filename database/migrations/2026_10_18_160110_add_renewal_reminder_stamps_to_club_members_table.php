<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One stamp per reminder threshold so the daily sweep is idempotent.
 * Reset to null on renew so the next cycle's reminders go out again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_members', function (Blueprint $table): void {
            $table->timestampTz('reminder_30_sent_at')->nullable()->after('popia_consented_at');
            $table->timestampTz('reminder_14_sent_at')->nullable()->after('reminder_30_sent_at');
            $table->timestampTz('reminder_7_sent_at')->nullable()->after('reminder_14_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('club_members', function (Blueprint $table): void {
            $table->dropColumn([
                'reminder_30_sent_at',
                'reminder_14_sent_at',
                'reminder_7_sent_at',
            ]);
        });
    }
};
