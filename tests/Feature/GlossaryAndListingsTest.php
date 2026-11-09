<?php

use App\Models\Discipline;
use App\Models\Event;
use App\Models\MagazineIssue;
use App\Models\Organisation;
use App\Models\User;
use Database\Seeders\CommunityListingsSeeder;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\OnTargetAfricaSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->withoutVite();
});

it('lists every published event type in the glossary with defined-term structured data', function () {
    $this->seed(DisciplineSeeder::class);

    $response = $this->get(route('glossary'));

    $response->assertOk()
        ->assertSee('DefinedTermSet', false)
        ->assertSee('id="gong-shooting"', false)
        ->assertSee('Gong shoot / gong skiet')
        ->assertSee('known in Afrikaans as gong skiet', false)
        ->assertSee(route('disciplines.show', 'gong-shooting'), false);

    Discipline::query()->where('is_published', true)->pluck('name')->each(
        fn (string $name) => $response->assertSee($name, false),
    );
});

it('matches gong skiet and gongskiet to gong shoot events', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(CommunityListingsSeeder::class);

    $this->get(route('calendar', [
        'discipline' => 'gongskiet',
        'from' => '2027-02-01',
        'to' => '2027-02-28',
    ]))->assertOk()->assertSee('Natal Gong Series, Midlands leg');

    $this->get(route('calendar', [
        'discipline' => 'gong skiet',
        'from' => '2027-11-01',
        'to' => '2027-11-30',
    ]))->assertOk()->assertSee('Natal Gong Series, Penbi leg');
});

it('links On Target Africa issues out to the reader', function () {
    Storage::fake('media');
    User::factory()->staff()->create();
    $this->seed(OnTargetAfricaSeeder::class);

    $this->get(route('magazines.index'))
        ->assertOk()
        ->assertSee('https://www.yumpu.com/en/document/view/71271438/on-target-africa-87-august-2026-magazine-online', false)
        ->assertSee('target="_blank"', false);

    $this->get(route('magazines.show', 'on-target-africa-89'))
        ->assertOk()
        ->assertSee('https://www.yumpu.com/en/document/view/71316791/on-target-africa-89-oct-2026-magazine', false)
        ->assertSee('target="_blank"', false);
});

it('opens a stored On Target PDF in the on-site reader', function () {
    Storage::fake('media');
    Storage::disk('media')->put('magazines/pdf/on-target-africa-88.pdf', '%PDF-1.4');
    Storage::disk('media')->put('magazines/pdf/on-target-africa-89.pdf', '%PDF-1.4');
    User::factory()->staff()->create();
    $this->seed(OnTargetAfricaSeeder::class);

    $issue = MagazineIssue::query()->where('slug', 'on-target-africa-89')->first();

    expect($issue)->not->toBeNull()
        ->and($issue->pdf_path)->toBe('magazines/pdf/on-target-africa-89.pdf')
        ->and(MagazineIssue::query()->where('slug', 'on-target-africa-87')->value('pdf_path'))->toBeNull();

    $this->get(route('magazines.show', 'on-target-africa-89'))
        ->assertOk()
        ->assertSee('id="magazine-flipbook"', false)
        ->assertDontSee('yumpu.com/en/document/view/71316791', false);

    $this->get(route('magazines.index'))
        ->assertOk()
        ->assertSee(route('magazines.show', 'on-target-africa-89'), false)
        ->assertSee('https://www.yumpu.com/en/document/view/71271438/on-target-africa-87-august-2026-magazine-online', false);
});

it('seeds the community listings once and does not duplicate them', function () {
    User::factory()->staff()->create();
    $this->seed(DisciplineSeeder::class);
    $this->seed(CommunityListingsSeeder::class);
    $this->seed(OnTargetAfricaSeeder::class);

    $organisations = Organisation::query()->count();
    $events = Event::query()->count();
    $issues = MagazineIssue::query()->count();

    $this->seed(CommunityListingsSeeder::class);
    $this->seed(OnTargetAfricaSeeder::class);

    expect(Organisation::query()->count())->toBe($organisations)
        ->and(Event::query()->count())->toBe($events)
        ->and(MagazineIssue::query()->count())->toBe($issues)
        ->and(Event::query()->where('slug', 'natal-gong-series-midlands-2027')->count())->toBe(1);
});

it('keeps private organiser contacts off the public pages', function () {
    $this->seed(DisciplineSeeder::class);
    $this->seed(CommunityListingsSeeder::class);

    $this->get(route('clubs.show', 'natal-gong-series'))
        ->assertOk()
        ->assertDontSee('+27 82 305 0743')
        ->assertDontSee('Nico Harris')
        ->assertDontSee('Erhard Breet');

    $this->get(route('events.show', 'natal-gong-series-midlands-2027'))
        ->assertOk()
        ->assertDontSee('+27 82 305 0743')
        ->assertDontSee('Nico Harris')
        ->assertSee('Entries fill quickly');

    $this->get(route('clubs.show', 'peregrine-bullets'))
        ->assertOk()
        ->assertDontSee('jd@peramco.co.za')
        ->assertDontSee('JD Els');

    $this->get(route('clubs.show', 'weskus-sa-jagters'))
        ->assertOk()
        ->assertDontSee('Leon Avenant');

    $this->get(route('clubs.show', 'winelands-precision'))
        ->assertOk()
        ->assertDontSee('Lambert Brand')
        ->assertDontSee('Perry de Jager');
});
