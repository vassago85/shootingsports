<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('profile_visibility')->default('private')->after('calendar_slug');
            $table->string('calendar_token', 64)->nullable()->unique()->after('profile_visibility');
        });

        // Every existing account gets a fresh secret. The old slug feed
        // is retired, so a token that was never issued is not enough:
        // rotate the whole table.
        DB::table('users')->orderBy('id')->chunkById(500, function ($users): void {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'calendar_token' => Str::random(48),
                    'profile_visibility' => 'private',
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['calendar_token']);
            $table->dropColumn(['profile_visibility', 'calendar_token']);
        });
    }
};
