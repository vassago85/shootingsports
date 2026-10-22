<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table): void {
            $table->string('reader_access', 16)->default('public')->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table): void {
            $table->dropColumn('reader_access');
        });
    }
};
