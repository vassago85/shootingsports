<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->foreignId('host_organisation_id')->constrained('organisations')->restrictOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->string('level')->default('club');
            $table->string('status')->default('draft');
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('original_starts_at')->nullable();
            $table->unsignedInteger('entry_fee_cents')->nullable();
            $table->unsignedInteger('member_fee_cents')->nullable();
            $table->string('entry_url')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('entries_taken')->nullable();
            $table->unsignedInteger('round_count')->nullable();
            $table->unsignedInteger('target_count')->nullable();
            $table->unsignedInteger('stage_count')->nullable();
            $table->string('results_url')->nullable();
            $table->foreignId('banner_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source')->default('staff');
            $table->timestampTz('last_verified_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('starts_at');
            $table->index('status');
            $table->index('host_organisation_id');
            $table->index('venue_id');
            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
