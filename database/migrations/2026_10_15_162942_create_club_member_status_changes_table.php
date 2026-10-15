<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_member_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_member_id')->constrained()->cascadeOnDelete();
            // Nullable because imports and the Verified-linking listener
            // write changes with no responsible user, and the initial
            // "nothing -> applied" transition has no previous state.
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->index(['club_member_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_member_status_changes');
    }
};
