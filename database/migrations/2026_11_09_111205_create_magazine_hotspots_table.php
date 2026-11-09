<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magazine_hotspots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('magazine_issue_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->unsignedInteger('pdf_page');
            $table->decimal('x', 6, 2);
            $table->decimal('y', 6, 2);
            $table->decimal('w', 6, 2);
            $table->decimal('h', 6, 2);
            $table->string('url', 2000);
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('advertiser')->nullable();
            $table->string('label')->nullable();
            $table->string('source', 20);
            $table->string('token', 32)->unique();
            $table->timestamps();

            $table->index(['magazine_issue_id', 'page']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_hotspots');
    }
};
