<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('display_name');
            $table->foreignId('discipline_id')->nullable()->constrained()->nullOnDelete();
            $table->string('division')->nullable();
            $table->unsignedInteger('placing')->nullable();
            $table->unsignedInteger('field_size')->nullable();
            $table->string('score')->nullable();
            $table->timestamps();

            $table->index(['discipline_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_results');
    }
};
