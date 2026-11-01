<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('whatsapp_url')->nullable()->after('instagram_url');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropColumn(['instagram_url', 'whatsapp_url']);
        });
    }
};
