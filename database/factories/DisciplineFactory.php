<?php

namespace Database\Factories;

use App\Enums\DisciplineFamily;
use App\Models\Discipline;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Discipline>
 */
class DisciplineFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('##'),
            'name' => Str::title($name),
            'family' => DisciplineFamily::Rifle,
            'short_blurb' => fake()->text(160),
            'sort_order' => 0,
            'is_published' => true,
        ];
    }
}
