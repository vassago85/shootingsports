<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->foreignId('ad_slot_id')->nullable()->after('provider_id')->constrained('ad_slots')->nullOnDelete();
            $table->string('headline')->nullable()->after('slot');
            $table->text('body')->nullable()->after('headline');
            $table->string('image_path')->nullable()->after('body');
            $table->string('click_url')->nullable()->after('image_path');
        });
    }

    public function down(): void
    {
        Schema::table('placements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ad_slot_id');
            $table->dropColumn(['headline', 'body', 'image_path', 'click_url']);
        });
    }
};
