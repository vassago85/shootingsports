<?php

use App\Enums\OrganisationUserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Splits the signup paths — see docs/superpowers/specs 2026-09-15.
 *
 * `is_match_director` marks accounts that should see the /desk panel.
 * `is_staff` still trumps everything (staff can do anything, always).
 *
 * Back-fill rule: any existing user who is already a member of at
 * least one organisation with an event-managing role becomes an MD.
 * That covers everyone who has been quietly using /desk under the
 * old "any authenticated user can access desk" rule without giving
 * shooter accounts a promotion they didn't ask for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_match_director')->default(false)->after('is_staff');
        });

        // Back-fill: any user with an event-managing membership becomes an MD.
        // Kept as a raw update rather than an Eloquent loop so the migration
        // does not depend on model state that may change over time.
        $roles = array_map(
            fn (OrganisationUserRole $role): string => $role->value,
            OrganisationUserRole::canManageEvents(),
        );

        $memberUserIds = DB::table('organisation_user')
            ->whereIn('role', $roles)
            ->pluck('user_id')
            ->unique()
            ->all();

        if ($memberUserIds !== []) {
            DB::table('users')
                ->whereIn('id', $memberUserIds)
                ->update(['is_match_director' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_match_director');
        });
    }
};
