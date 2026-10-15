<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            // Two independent switches: a club can keep its register
            // without opening it up to new applicants (membership_enabled
            // on, accepts_applications off).
            $table->boolean('membership_enabled')->default(false)->after('visitors_welcome');
            $table->boolean('accepts_applications')->default(false)->after('membership_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn(['membership_enabled', 'accepts_applications']);
        });
    }
};
