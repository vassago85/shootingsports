<?php

namespace Database\Factories;

use App\Enums\ClubMemberStatus;
use App\Models\ClubMember;
use App\Models\ClubMemberStatusChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubMemberStatusChange>
 */
class ClubMemberStatusChangeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'club_member_id' => ClubMember::factory(),
            'from_status' => ClubMemberStatus::Applied,
            'to_status' => ClubMemberStatus::Active,
            'changed_by' => null,
            'reason' => null,
        ];
    }
}
