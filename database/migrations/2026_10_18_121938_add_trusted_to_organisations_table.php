<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — a staff-only "we trust this club" flag. Trusted clubs
 * skip `pending_review` on new events their owners create: the event
 * goes straight to `published`. Default false so the mockup's
 * "director requests publishing, staff approves" is the baseline
 * until staff promote individual clubs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->boolean('trusted')->default(false)->after('verification_state');
            $table->index('trusted');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropIndex(['trusted']);
            $table->dropColumn('trusted');
        });
    }
};
