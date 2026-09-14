<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplines', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('family');
            $table->foreignId('parent_id')->nullable()->constrained('disciplines')->nullOnDelete();
            $table->foreignId('federation_organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('short_blurb', 320);
            $table->text('body')->nullable();
            $table->string('typical_distances')->nullable();
            $table->text('equipment_rules')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();

            $table->index('family');
            $table->index('parent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplines');
    }
};
