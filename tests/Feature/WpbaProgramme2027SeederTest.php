<?php

use App\Enums\EventLevel;
use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\WpbaProgramme2027Seeder;

it('publishes the Western Province Bisley Association 2027 programme', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(WpbaProgramme2027Seeder::class);

    $wpba = Organisation::query()->where('slug', 'wpba')->first();

    expect($wpba)->not->toBeNull()
        ->and($wpba->name)->toBe('Western Province Bisley Association')
        ->and($wpba->type)->toBe(OrganisationType::ProvincialBody)
        ->and($wpba->province)->toBe(Province::WesternCape)
        ->and($wpba->town)->toBe('Atlantis')
        ->and($wpba->website_url)->toBe('http://www.wpbisley.co.za/')
        ->and($wpba->description)->toContain('Rick Jaffe')
        ->and($wpba->status)->toBe(ListingStatus::Published)
        ->and($wpba->parent?->slug)->toBe('sabu')
        ->and($wpba->disciplines->pluck('slug')->sort()->values()->all())
        ->toBe(['bisley-fullbore', 'target-rifle']);

    $goodHope = Venue::query()->where('slug', 'good-hope-shooting-range')->first();

    expect($goodHope)->not->toBeNull()
        ->and($goodHope->town)->toBe('Atlantis')
        ->and($goodHope->province)->toBe(Province::WesternCape)
        ->and($goodHope->description)->toContain('SAPS')
        ->and($goodHope->max_distance_m)->toBe(800);

    expect(Event::query()->where('slug', 'like', 'wpba-2027-%')->count())->toBe(43)
        ->and(Event::query()->where('slug', 'like', 'wpba-2027-%')->where('title', 'like', '%Range Closed%')->exists())->toBeFalse()
        ->and(Event::query()->where('slug', 'like', 'wpba-2027-%')->whereDate('starts_at', '2027-01-02')->exists())->toBeFalse();

    $league = Event::query()->where('slug', 'wpba-2027-league-individual-1')->first();

    expect($league)->not->toBeNull()
        ->and($league->starts_at->toDateTimeString())->toBe('2027-01-16 00:00:00')
        ->and($league->ends_at)->toBeNull()
        ->and($league->all_day)->toBeTrue()
        ->and($league->status)->toBe(EventStatus::Confirmed)
        ->and($league->publication)->toBe(EventPublication::Published)
        ->and($league->level)->toBe(EventLevel::Series)
        ->and($league->hostOrganisation?->slug)->toBe('wpba')
        ->and($league->venue?->slug)->toBe('good-hope-shooting-range')
        ->and($league->disciplines->pluck('slug')->all())->toBe(['bisley-fullbore'])
        ->and($league->description)->toContain('300 m, 500 m, 600 m');

    $smallbore = Event::query()->where('slug', 'wpba-2027-prc-22-2027-02-13')->first();

    expect($smallbore->hostOrganisation?->slug)->toBe('peninsula-rifle-club')
        ->and($smallbore->disciplines->pluck('slug')->all())->toBe(['target-rifle'])
        ->and($smallbore->description)->toContain('7, 7, 7');

    $shortRange = Event::query()->where('slug', 'wpba-2027-303-short-range-1')->first();

    expect($shortRange->starts_at->toDateString())->toBe('2027-02-27')
        ->and($shortRange->hostOrganisation?->slug)->toBe('cli-303-club-of-cape-town')
        ->and($shortRange->needs_review)->toBeTrue()
        ->and($shortRange->import_notes)->toContain('27-Feb-26');

    $nationals = Event::query()->where('slug', 'wpba-2027-sa-championships')->first();

    expect($nationals->starts_at->toDateString())->toBe('2027-04-10')
        ->and($nationals->ends_at?->toDateString())->toBe('2027-04-17')
        ->and($nationals->level)->toBe(EventLevel::National)
        ->and($nationals->hostOrganisation?->slug)->toBe('sabu')
        ->and($nationals->venue?->slug)->toBe('genl-de-wet-shooting-range')
        ->and($nationals->venue?->town)->toBe('Bloemfontein')
        ->and($nationals->entry_url)->toBe('https://sabisley.com/sa-open-2027/')
        ->and($nationals->needs_review)->toBeTrue();

    $wpChamps = Event::query()->where('slug', 'wpba-2027-wp-championships')->first();

    expect($wpChamps->starts_at->toDateString())->toBe('2027-09-29')
        ->and($wpChamps->ends_at?->toDateString())->toBe('2027-10-02')
        ->and($wpChamps->level)->toBe(EventLevel::Provincial)
        ->and($wpChamps->needs_review)->toBeFalse();
});

it('is idempotent across repeated runs', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(WpbaProgramme2027Seeder::class);
    $this->seed(WpbaProgramme2027Seeder::class);

    expect(Organisation::query()->where('slug', 'wpba')->count())->toBe(1)
        ->and(Venue::query()->where('slug', 'good-hope-shooting-range')->count())->toBe(1)
        ->and(Event::query()->where('slug', 'like', 'wpba-2027-%')->count())->toBe(43);
});

it('leaves a staff-edited Good Hope town untouched', function () {
    Venue::factory()->create([
        'slug' => 'good-hope-shooting-range',
        'name' => 'Good Hope Shooting Range',
        'province' => Province::WesternCape,
        'town' => 'Cape Town',
        'status' => ListingStatus::Published,
    ]);

    $this->seed(DisciplineSeeder::class);
    $this->seed(WpbaProgramme2027Seeder::class);

    $venue = Venue::query()->where('slug', 'good-hope-shooting-range')->first();
    $league = Event::query()->where('slug', 'wpba-2027-league-individual-1')->first();

    expect($venue->town)->toBe('Cape Town')
        ->and($league->venue_id)->toBe($venue->id);
});
