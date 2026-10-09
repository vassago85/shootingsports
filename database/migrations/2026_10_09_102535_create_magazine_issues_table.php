<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magazine_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('publication')->default('Targets');
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('issue_label')->nullable();
            $table->text('description')->nullable();
            $table->string('language', 10)->default('en');
            $table->string('pdf_path')->nullable();
            $table->string('cover_path')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->json('pages_text')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['publication', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_issues');
    }
};
