<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow `organisations.visitors_welcome` to be null so the mobile
 * app's Visitors badge only appears when the club has explicitly
 * confirmed newcomers are welcome. The old non-null `false` default
 * caused the field to collapse into a boolean every row carried — a
 * club that hadn't been asked yet looked identical to one that had
 * actively declined, so the badge couldn't carry a meaningful
 * "yes" signal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->boolean('visitors_welcome')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table): void {
            $table->boolean('visitors_welcome')->default(false)->change();
        });
    }
};
