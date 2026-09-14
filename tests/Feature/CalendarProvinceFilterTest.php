<?php

use App\Enums\ListingStatus;
use App\Enums\Province;
use App\Livewire\CalendarFilter;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use Livewire\Livewire;

it('filters the calendar query to one province by venue', function () {
    $gauteng = Venue::factory()->create(['province' => Province::Gauteng]);
    $cape = Venue::factory()->create(['province' => Province::WesternCape]);

    Event::factory()->confirmed()->create([
        'title' => 'Highveld Steel',
        'venue_id' => $gauteng->id,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Boland Gong',
        'venue_id' => $cape->id,
    ]);

    $titles = (new PublicEventQuery(provinceSlug: 'gauteng'))->get()->pluck('title');

    expect($titles)->toContain('Highveld Steel')
        ->not->toContain('Boland Gong');
});

it('filters the calendar query to several provinces at once', function () {
    $gauteng = Venue::factory()->create(['province' => Province::Gauteng]);
    $cape = Venue::factory()->create(['province' => Province::WesternCape]);
    $kzn = Venue::factory()->create(['province' => Province::KwaZuluNatal]);

    Event::factory()->confirmed()->create([
        'title' => 'Highveld Steel',
        'venue_id' => $gauteng->id,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Boland Gong',
        'venue_id' => $cape->id,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Coastal PRS',
        'venue_id' => $kzn->id,
    ]);

    $titles = (new PublicEventQuery(provinceSlug: 'gauteng,western-cape'))->get()->pluck('title');

    expect($titles)->toContain('Highveld Steel')
        ->toContain('Boland Gong')
        ->not->toContain('Coastal PRS');
});

it('uses the host organisation province when a match has no venue', function () {
    $host = Organisation::factory()->create([
        'province' => Province::Mpumalanga,
        'status' => ListingStatus::Published,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Lowveld Club Day',
        'host_organisation_id' => $host->id,
        'venue_id' => null,
    ]);

    $titles = (new PublicEventQuery(provinceSlug: 'mpumalanga,limpopo'))->get()->pluck('title');

    expect($titles)->toContain('Lowveld Club Day');
});

it('lets the calendar toggle multiple province chips', function () {
    $gauteng = Venue::factory()->create(['province' => Province::Gauteng]);
    $cape = Venue::factory()->create(['province' => Province::WesternCape]);

    Event::factory()->confirmed()->create([
        'title' => 'Highveld Steel',
        'venue_id' => $gauteng->id,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Boland Gong',
        'venue_id' => $cape->id,
    ]);

    Livewire::test(CalendarFilter::class)
        ->assertSee('Highveld Steel')
        ->assertSee('Boland Gong')
        ->call('toggleProvince', 'gauteng')
        ->assertSet('province', 'gauteng')
        ->assertSee('Highveld Steel')
        ->assertDontSee('Boland Gong')
        ->call('toggleProvince', 'western-cape')
        ->assertSet('province', 'gauteng,western-cape')
        ->assertSee('Highveld Steel')
        ->assertSee('Boland Gong')
        ->call('clearProvinces')
        ->assertSet('province', null);
});

it('renders province filter chips on the calendar page', function () {
    $this->get(route('calendar'))
        ->assertOk()
        ->assertSee('Filter matches by province')
        ->assertSee('Gauteng')
        ->assertSee('Western Cape')
        ->assertSee('KwaZulu-Natal');
});
