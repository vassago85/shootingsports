<?php

use App\Enums\EventKind;
use App\Enums\EventLevel;
use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\FalseBaySportsShootingClubSeeder;

it('lists False Bay Sports Shooting Club, its quarry range, and the published calendar', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(FalseBaySportsShootingClubSeeder::class);
    $this->seed(FalseBaySportsShootingClubSeeder::class);

    $club = Organisation::query()->where('slug', 'false-bay-sports-shooting-club')->first();

    expect($club)->not->toBeNull()
        ->and($club->name)->toBe('False Bay Sports Shooting Club')
        ->and($club->short_name)->toBe('FBSSC')
        ->and($club->type)->toBe(OrganisationType::Club)
        ->and($club->province)->toBe(Province::WesternCape)
        ->and($club->town)->toBe('Glencairn')
        ->and($club->email)->toBe('secretary@fbssc.co')
        ->and($club->phone)->toBe('063 393 7767')
        ->and($club->visitors_welcome)->toBeTrue()
        ->and($club->accredited)->toBeFalse()
        ->and($club->website_url)->toBe('https://fbssc.co/')
        ->and($club->facebook_url)->toBe('https://www.facebook.com/FalseBaySSC/')
        ->and($club->disciplines->pluck('slug')->sort()->values()->all())
        ->toBe([
            '3-gun',
            'action-defensive',
            'ipsc-practical',
            'multigun',
            'sporting-clays',
            'steel-challenge',
            'trap',
        ])
        ->and(Organisation::query()->where('slug', 'false-bay-sports-shooting-club')->count())->toBe(1);

    $range = Venue::query()->where('slug', 'false-bay-sports-shooting-range')->first();

    expect($range)->not->toBeNull()
        ->and($range->name)->toBe('False Bay Sports Shooting Range')
        ->and($range->town)->toBe('Glencairn')
        ->and($range->address)->toBe('Tercentenary Way, Glencairn, Cape Town, 7975')
        ->and($range->max_distance_m)->toBe(120)
        ->and($range->day_fee_cents)->toBe(11000)
        ->and($range->access)->toBe(VenueAccess::GuestByArrangement)
        ->and($range->facilities)->toContain('Rifle Range, visitors and members, rifle only, 120 m')
        ->and($range->facilities)->toContain('Range 1, visitors and members, handgun only, 20 m')
        ->and($range->image_paths)->toBe([
            'range-images/fbssc-news-open-day-2026-11-03.jpg',
            'range-images/fbssc-news-fundraiser-2026-06-16.jpg',
        ])
        ->and(Venue::query()->where('slug', 'false-bay-sports-shooting-range')->count())->toBe(1);

    $hosted = Event::query()->where('host_organisation_id', $club->id);

    expect($hosted->count())->toBe(89)
        ->and((clone $hosted)->whereDate('starts_at', '<', '2026-11-20')->count())->toBe(0)
        ->and((clone $hosted)->whereDate('starts_at', '>', '2027-03-31')->count())->toBe(0)
        ->and(Event::query()->where('slug', 'fbssc-clay-busters-2026-11-21')->count())->toBe(1);

    $boland = Event::query()->where('slug', 'fbssc-ipsc-boland-handgun-level-2-2026-11-28')->first();

    expect($boland)->not->toBeNull()
        ->and($boland->title)->toBe('IPSC Boland Handgun Level 2')
        ->and($boland->level)->toBe(EventLevel::Provincial)
        ->and($boland->kind)->toBe(EventKind::Competition)
        ->and($boland->status)->toBe(EventStatus::Confirmed)
        ->and($boland->publication)->toBe(EventPublication::Published)
        ->and($boland->all_day)->toBeTrue()
        ->and($boland->venue_id)->toBe($range->id)
        ->and($boland->starts_at->toDateString())->toBe('2026-11-28')
        ->and($boland->disciplines->pluck('slug')->all())->toBe(['ipsc-practical']);

    expect(Event::query()->where('slug', 'fbssc-sadpa-2026-11-28')->exists())->toBeFalse()
        ->and(Event::query()->where('slug', 'fbssc-mpds-fourth-saturday-2026-11-28')->exists())->toBeFalse()
        ->and(Event::query()->where('slug', 'fbssc-ipsc-club-shoot-2026-12-05')->exists())->toBeFalse();

    $openDay = Event::query()->where('slug', 'fbssc-open-day-2026-12-05')->first();

    expect($openDay)->not->toBeNull()
        ->and($openDay->title)->toBe('Operation: Adrenaline Open Day')
        ->and($openDay->kind)->toBe(EventKind::Training)
        ->and($openDay->all_day)->toBeFalse()
        ->and($openDay->starts_at->format('Y-m-d H:i'))->toBe('2026-12-05 09:00')
        ->and($openDay->ends_at?->format('Y-m-d H:i'))->toBe('2026-12-05 17:00')
        ->and($openDay->entry_fee_cents)->toBeNull()
        ->and($openDay->banner_path)->toBe('event-banners/fbssc-open-day-2026-12-05.png')
        ->and($openDay->description)->toContain('clay pigeon shooting')
        ->and($openDay->description)->toContain('@FalseBaySC');

    expect(Event::query()->where('slug', 'fbssc-steel-challenge-2026-12-13')->value('title'))->toBe('Steel Challenge')
        ->and(Event::query()->where('slug', 'fbssc-ipsc-end-of-year-fun-shoot-2026-12-16')->value('title'))->toBe('IPSC End of Year Fun Shoot')
        ->and(Event::query()->where('slug', 'fbssc-wtr-cowboys-2026-11-21')->value('title'))->toBe('WTR Cowboys');

    $this->get(route('clubs.show', 'false-bay-sports-shooting-club'))
        ->assertOk()
        ->assertSee('False Bay Sports Shooting Club')
        ->assertSee('R830')
        ->assertSee('fbfta.co.za')
        ->assertSee('064 286 0173')
        ->assertSee('R1 600')
        ->assertSee('fbssc.co')
        ->assertSee('False Bay Sports Shooting Range')
        ->assertSee('IPSC Boland Handgun Level 2')
        ->assertSee('Operation: Adrenaline Open Day')
        ->assertSee('page-hero-poster', false)
        ->assertSee('range-images/fbssc-news-open-day-2026-11-03.jpg', false)
        ->assertDontSee('exco@fbssc.co')
        ->assertDontSee('gm@fbssc.co')
        ->assertDontSee('Alwyn Marais');

    $this->get(route('ranges.show', 'false-bay-sports-shooting-range'))
        ->assertOk()
        ->assertSee('False Bay Sports Shooting Range')
        ->assertSee('Tercentenary Way, Glencairn, Cape Town, 7975')
        ->assertSee('120 m')
        ->assertSee('R110')
        ->assertSee('Ronin Tac House 1, vetted only, all calibre')
        ->assertSee('40 km/h')
        ->assertSee('page-hero-poster', false)
        ->assertSee('range-images/fbssc-news-open-day-2026-11-03.jpg', false)
        ->assertSee('range-images/fbssc-news-fundraiser-2026-06-16.jpg', false);

    $this->get(route('events.show', 'fbssc-open-day-2026-12-05'))
        ->assertOk()
        ->assertSee('Operation: Adrenaline Open Day')
        ->assertSee('Saturday 5 December 2026')
        ->assertSee('09:00–17:00')
        ->assertSee('clay pigeon shooting')
        ->assertSee('bloodpack T-shirt target is back')
        ->assertSee('axe throwing')
        ->assertSee('page-hero-poster', false)
        ->assertSee('/media/event-banners/fbssc-open-day-2026-12-05.png', false);
});
