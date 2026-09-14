<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('home_province')->nullable()->after('remember_token');
            $table->unsignedSmallInteger('travel_radius_km')->nullable()->after('home_province');
            $table->string('digest_frequency')->default('none')->after('travel_radius_km');
            $table->boolean('is_staff')->default(false)->after('digest_frequency');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'home_province',
                'travel_radius_km',
                'digest_frequency',
                'is_staff',
            ]);
        });
    }
};
