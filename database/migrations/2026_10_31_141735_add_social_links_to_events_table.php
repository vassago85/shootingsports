<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            // Shown on the public match page. When any of these are
            // null, `Event::socialLinks()` falls back to the host
            // organisation so a Royal Flush match without its own
            // Facebook page still links to the series page.
            $table->string('website_url')->nullable()->after('results_url');
            $table->string('facebook_url')->nullable()->after('website_url');
            $table->string('instagram_url')->nullable()->after('facebook_url');
            $table->string('whatsapp_url')->nullable()->after('instagram_url');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn(['website_url', 'facebook_url', 'instagram_url', 'whatsapp_url']);
        });
    }
};
