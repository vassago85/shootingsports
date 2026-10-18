<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            // Schema definition for the club: a list of
            // [{key,label,type,required,options?}] shapes. Rendered
            // on the public apply form, the Desk edit form and
            // the self-service edit form.
            $table->json('membership_custom_fields')->nullable();
        });

        Schema::table('club_members', function (Blueprint $table) {
            // Captured values for the club's custom fields,
            // keyed by field `key`.
            $table->json('custom_attributes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropColumn('membership_custom_fields');
        });

        Schema::table('club_members', function (Blueprint $table) {
            $table->dropColumn('custom_attributes');
        });
    }
};
