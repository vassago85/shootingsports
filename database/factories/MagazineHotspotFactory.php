<?php

namespace Database\Factories;

use App\Models\MagazineHotspot;
use App\Models\MagazineIssue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MagazineHotspot>
 */
class MagazineHotspotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'magazine_issue_id' => MagazineIssue::factory(),
            'page' => 2,
            'pdf_page' => 2,
            'x' => 10,
            'y' => 70,
            'w' => 40,
            'h' => 8,
            'url' => 'https://example.com',
            'organisation_id' => null,
            'advertiser' => null,
            'label' => 'Example',
            'source' => 'manual',
            'token' => Str::lower(Str::random(16)),
        ];
    }
}
