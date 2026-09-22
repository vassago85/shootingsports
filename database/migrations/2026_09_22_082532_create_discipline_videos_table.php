<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discipline_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('url');
            $table->string('video_id', 11);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discipline_videos');
    }
};
