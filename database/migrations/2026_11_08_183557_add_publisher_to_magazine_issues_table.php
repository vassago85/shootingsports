<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->foreignId('publisher_organisation_id')
                ->nullable()
                ->after('user_id')
                ->constrained('organisations')
                ->nullOnDelete();
            $table->string('publisher_name')->nullable()->after('publisher_organisation_id');
        });

        DB::table('magazine_issues')
            ->where(function ($query): void {
                $query->where('slug', 'issue-87')
                    ->orWhere('issue_label', 'Issue 87');
            })
            ->whereNull('publisher_name')
            ->whereNull('publisher_organisation_id')
            ->update(['publisher_name' => 'On Target Africa']);
    }

    public function down(): void
    {
        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('publisher_organisation_id');
            $table->dropColumn('publisher_name');
        });
    }
};
