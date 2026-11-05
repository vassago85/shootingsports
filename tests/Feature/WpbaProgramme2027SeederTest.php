<?php

use App\Enums\EventLevel;
use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\WpbaProgramme2027Seeder;

it('publishes the Western Cape 2027 WPBA programme', function () {
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
        ->and($wpba->parent_id)->toBeNull()
        ->and($wpba->disciplines->pluck('slug')->sort()->values()->all())
        ->toBe(['bisley-fullbore', 'target-rifle']);

    $goodHope = Venue::query()->where('slug', 'good-hope-shooting-range')->first();

    expect($goodHope)->not->toBeNull()
        ->and($goodHope->town)->toBe('Atlantis')
        ->and($goodHope->province)->toBe(Province::WesternCape)
        ->and($goodHope->description)->toContain('SAPS')
        ->and($goodHope->max_distance_m)->toBe(800);

    expect(Event::query()->where('slug', 'like', 'wpba-2027-%')->count())->toBe(40)
        ->and(Event::query()->where('slug', 'like', 'wpba-2027-%')->where('title', 'like', '%Range Closed%')->exists())->toBeFalse()
        ->and(Event::query()->where('slug', 'like', 'wpba-2027-%')->whereDate('starts_at', '2027-01-02')->exists())->toBeFalse();

    expect(Event::query()->where('slug', 'wpba-2027-sa-championships')->exists())->toBeFalse()
        ->and(Event::query()->where('slug', 'wpba-2027-sa-long-range')->exists())->toBeFalse()
        ->and(Event::query()->where('slug', 'wpba-2027-free-state-championships')->exists())->toBeFalse()
        ->and(Venue::query()->where('slug', 'genl-de-wet-shooting-range')->exists())->toBeFalse()
        ->and(Organisation::query()->where('slug', 'sabu')->exists())->toBeFalse();

    expect(
        Event::query()
            ->where('slug', 'like', 'wpba-2027-%')
            ->whereHas('venue', fn ($query) => $query->where('province', '!=', Province::WesternCape))
            ->exists()
    )->toBeFalse();

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
        ->and(Event::query()->where('slug', 'like', 'wpba-2027-%')->count())->toBe(40);
});

it('drops Bloemfontein rows left behind by the earlier seeder version', function () {
    $this->seed(DisciplineSeeder::class);

    $genlDeWet = Venue::factory()->create([
        'slug' => 'genl-de-wet-shooting-range',
        'name' => 'Genl De Wet Shooting Range',
        'province' => Province::FreeState,
        'town' => 'Bloemfontein',
        'status' => ListingStatus::Published,
        'source' => ListingSource::Staff,
    ]);

    $sabu = Organisation::factory()->create([
        'slug' => 'sabu',
        'name' => 'South African Bisley Union',
        'type' => OrganisationType::Federation,
    ]);

    $stale = Event::factory()->confirmed()->create([
        'slug' => 'wpba-2027-sa-championships',
        'title' => 'SA Championships',
        'host_organisation_id' => $sabu->id,
        'venue_id' => $genlDeWet->id,
        'starts_at' => '2027-04-10 00:00:00',
        'source' => ListingSource::Staff,
    ]);

    $this->seed(WpbaProgramme2027Seeder::class);

    expect(Event::query()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(Venue::query()->where('slug', 'genl-de-wet-shooting-range')->exists())->toBeFalse();
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
