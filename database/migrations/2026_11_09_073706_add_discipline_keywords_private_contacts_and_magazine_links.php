<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('disciplines', function (Blueprint $table) {
            $table->string('keywords')->nullable()->after('name');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->text('private_contact')->nullable()->after('phone');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->text('private_contact')->nullable()->after('contact_email');
        });

        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->unsignedSmallInteger('issue_number')->nullable()->after('issue_label');
            $table->date('issue_month')->nullable()->after('issue_number');
            $table->string('external_url')->nullable()->after('issue_month');
        });

        Schema::table('venues', function (Blueprint $table) {
            $table->string('province')->nullable()->change();
            $table->string('town')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('venues', function (Blueprint $table) {
            $table->string('province')->nullable(false)->change();
            $table->string('town')->nullable(false)->change();
        });

        Schema::table('magazine_issues', function (Blueprint $table) {
            $table->dropColumn(['issue_number', 'issue_month', 'external_url']);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('private_contact');
        });

        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('private_contact');
        });

        Schema::table('disciplines', function (Blueprint $table) {
            $table->dropColumn('keywords');
        });
    }
};
