<?php

namespace Database\Factories;

use App\Models\MagazineIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MagazineIssue>
 */
class MagazineIssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Targets '.$this->faker->monthName().' '.$this->faker->year();

        return [
            'user_id' => User::factory()->state(['is_media_partner' => true]),
            'publication' => 'Targets',
            'title' => $title,
            'slug' => Str::slug($title.'-'.$this->faker->unique()->numberBetween(1, 99999)),
            'issue_label' => $this->faker->monthName().' '.$this->faker->year(),
            'description' => $this->faker->sentence(14),
            'language' => 'en',
            'pdf_path' => null,
            'pdf_download_enabled' => true,
            'cover_path' => null,
            'page_count' => $this->faker->numberBetween(24, 96),
            'pages_text' => null,
            'published_at' => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'published_at' => now()->subDay(),
        ]);
    }

    public function draft(): self
    {
        return $this->state(fn (): array => [
            'published_at' => null,
        ]);
    }
}
