<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisation_membership_fees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            // The category string mirrors ClubMemberCategory values so
            // we can look up a fee by the member's category without a
            // separate join table. Stored as string (not enum) so new
            // category types rolled out in the enum do not need a
            // schema change.
            $table->string('category');
            $table->unsignedInteger('amount_cents');
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisation_membership_fees');
    }
};
