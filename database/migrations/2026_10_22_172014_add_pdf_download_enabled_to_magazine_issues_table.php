<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table): void {
            $table->boolean('pdf_download_enabled')->default(true)->after('pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table): void {
            $table->dropColumn('pdf_download_enabled');
        });
    }
};
