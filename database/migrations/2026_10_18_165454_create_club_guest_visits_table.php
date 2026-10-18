<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('club_guest_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('visit_date');
            // Hosting member if any — a lot of ranges require a
            // member sponsor on the sign-in sheet.
            $table->foreignId('host_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->text('notes')->nullable();
            // The staff/user who logged the visit on Desk. Nullable
            // because the record survives the author being deleted.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // When "Convert to application" fires we stamp the
            // ClubMember id here so re-opening the guest page
            // shows the conversion.
            $table->foreignId('converted_club_member_id')->nullable()->constrained('club_members')->nullOnDelete();
            $table->timestamps();

            $table->index(['organisation_id', 'visit_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('club_guest_visits');
    }
};
