<?php

namespace Database\Seeders;

use App\Enums\EntryCollection;
use App\Enums\EventKind;
use App\Enums\EventStatus;
use App\Models\Article;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fixture data for the Playwright browser suite only.
 * Refuses to run unless the connection is the dedicated sqlite file.
 */
class PlaywrightSeeder extends Seeder
{
    public function run(): void
    {
        $database = (string) config('database.connections.sqlite.database');

        if (config('database.default') !== 'sqlite' || ! str_contains($database, 'playwright.sqlite')) {
            throw new RuntimeException('PlaywrightSeeder only runs against database/playwright.sqlite.');
        }

        $this->call(DisciplineSeeder::class);

        User::factory()->create([
            'name' => 'Playwright Shooter',
            'email' => 'playwright@shootingsports.test',
            'password' => 'password',
        ]);

        Event::factory()->confirmed()->create([
            'slug' => 'playwright-training-day',
            'title' => 'Playwright Training Day',
            'kind' => EventKind::Training,
            'starts_at' => now()->addDays(3),
        ]);

        Event::factory()->create([
            'slug' => 'playwright-club-match',
            'title' => 'Playwright Club Match',
            'kind' => EventKind::Competition,
            'status' => EventStatus::EntriesOpen,
            'confirmed_at' => now(),
            'accepts_platform_entries' => true,
            'entry_collection' => EntryCollection::External,
            'starts_at' => now()->addDays(10),
        ]);

        $author = User::factory()->create([
            'name' => 'Playwright Media',
            'email' => 'playwright-media@shootingsports.test',
            'is_media_partner' => true,
        ]);

        Article::query()->create([
            'user_id' => $author->id,
            'title' => 'Playwright range report',
            'slug' => 'playwright-range-report',
            'excerpt' => 'A Saturday on steel.',
            'body' => 'The squad finished in the wind.',
            'event_kinds' => [EventKind::Competition->value],
            'published_at' => now(),
        ]);
    }
}
