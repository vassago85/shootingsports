<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            $table->string('confirmation_token', 64)->nullable()->unique()->after('user_agent');
            $table->timestampTz('confirmation_sent_at')->nullable()->after('confirmation_token');
            $table->timestampTz('confirmed_at')->nullable()->after('confirmation_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            $table->dropUnique(['confirmation_token']);
            $table->dropColumn(['confirmation_token', 'confirmation_sent_at', 'confirmed_at']);
        });
    }
};
