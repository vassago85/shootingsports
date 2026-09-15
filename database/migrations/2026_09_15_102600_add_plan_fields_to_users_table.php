<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Freemium foundation. Everything defaults to 'free' so existing
            // rows are already correct without a data migration. Indexed
            // because plan-scoped queries (e.g. the waitlist widget) look
            // it up frequently. plan_expires_at is nullable — a Pro user
            // without an expiry is "pro until further notice".
            $table->string('plan')->default('free')->after('is_staff');
            $table->timestamp('plan_expires_at')->nullable()->after('plan');

            $table->index('plan');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['plan']);
            $table->dropColumn(['plan', 'plan_expires_at']);
        });
    }
};
