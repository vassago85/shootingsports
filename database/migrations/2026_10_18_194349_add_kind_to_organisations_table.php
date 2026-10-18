<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->string('kind')->default('club')->after('id');
            $table->index('kind');
        });

        DB::table('organisations')->whereNull('kind')->update(['kind' => 'club']);
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropColumn('kind');
        });
    }
};
