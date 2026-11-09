<?php

namespace Database\Factories;

use App\Models\MagazineHotspot;
use App\Models\MagazineIssue;
use App\Models\MagazineLinkClick;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MagazineLinkClick>
 */
class MagazineLinkClickFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'magazine_hotspot_id' => MagazineHotspot::factory(),
            'magazine_issue_id' => MagazineIssue::factory(),
            'page' => 2,
            'organisation_id' => null,
            'advertiser' => null,
        ];
    }
}
