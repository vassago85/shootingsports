<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('discipline_id')->constrained()->cascadeOnDelete();
            $table->morphs('disciplinable');
            $table->boolean('is_primary')->default(false);

            $table->unique(
                ['discipline_id', 'disciplinable_type', 'disciplinable_id'],
                'disciplinables_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinables');
    }
};
