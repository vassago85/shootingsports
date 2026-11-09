<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magazine_link_clicks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('magazine_hotspot_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('magazine_issue_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('page');
            $table->foreignId('organisation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('advertiser')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['magazine_issue_id', 'created_at']);
            $table->index(['organisation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('magazine_link_clicks');
    }
};
