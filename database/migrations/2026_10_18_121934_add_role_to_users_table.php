<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase B — introduces `users.role` as the single source of truth for
 * the admin / staff / shooter capability split. The old `is_staff`
 * boolean stays in place for one deploy so every call site keeps
 * working during the transition; Phase C flips the shim to read
 * `role` first and Phase D drops the column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Default to `user` so new rows written during the deploy
            // window never land as NULL. Backfill below bumps existing
            // staff rows to `staff`.
            $table->string('role', 16)->default('user')->after('is_staff');
            $table->index('role');
        });

        // Backfill: any existing is_staff=true user becomes `staff`.
        // `admin` is only ever set manually; nobody is auto-promoted.
        DB::table('users')->where('is_staff', true)->update(['role' => 'staff']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};
