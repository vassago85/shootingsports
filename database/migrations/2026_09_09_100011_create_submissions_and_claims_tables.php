<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->jsonb('payload');
            $table->string('submitter_name');
            $table->string('submitter_email');
            $table->string('status')->default('pending');
            $table->nullableMorphs('merged_into');
            $table->foreignId('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('type');
        });

        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->morphs('claimable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('evidence');
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
        Schema::dropIfExists('submissions');
    }
};
