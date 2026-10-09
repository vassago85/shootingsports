<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magazine_reads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('magazine_issue_id')->constrained()->cascadeOnDelete();
            // Opaque reader hash: sha256(app_key + ip + user_agent + issue_id).
            // No raw IP or user agent is stored, so this stays POPIA-friendly
            // while still de-duplicating "same person, many page turns".
            $table->char('visitor_hash', 64);
            $table->unsignedInteger('max_page')->default(1);
            $table->unsignedInteger('pages_viewed')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['magazine_issue_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_reads');
    }
};
