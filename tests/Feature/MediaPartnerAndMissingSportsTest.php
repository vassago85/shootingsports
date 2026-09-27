<?php

use App\Models\Event;
use Database\Seeders\DisciplineSeeder;
use Database\Seeders\XsssaEventsSeeder;

beforeEach(function () {
    $this->withoutVite();
    $this->seed(DisciplineSeeder::class);
    $this->seed(XsssaEventsSeeder::class);
});

it('lists Xtreme Steel under bolt-action rifle and keeps it off handgun', function () {
    $this->get(route('divisions.show', 'bolt-action-rifle'))
        ->assertOk()
        ->assertSee('Xtreme Steel')
        ->assertSee('XSSSA');

    $this->get(route('disciplines.about', 'xtreme-steel'))
        ->assertOk()
        ->assertSee('https://www.xsssa.co.za/', false)
        ->assertSee('XSSSA');

    $this->get(route('divisions.show', 'handgun'))
        ->assertOk()
        ->assertDontSee('Xtreme Steel')
        ->assertSee('>Steel<', false);
});

it('lists IPSC Mini Rifle under self-loading rifle beside IPSC Rifle and PCC', function () {
    $this->get(route('divisions.show', 'self-loading-rifle'))
        ->assertOk()
        ->assertSee('IPSC Mini Rifle')
        ->assertSee('IPSC Rifle')
        ->assertSee('>PCC<', false);
});

it('lists the 7 November Loch Lynne match and skips 22 August', function () {
    expect(Event::query()->whereDate('starts_at', '2026-08-22')->exists())->toBeFalse();

    $this->get(route('events.show', 'xtreme-steel-loch-lynne-2026-11-07'))
        ->assertOk()
        ->assertSee('Saturday 7 November 2026')
        ->assertSee('Loch Lynne')
        ->assertSee('https://www.xsssa.co.za/calender/calender.html', false)
        ->assertDontSee('22 August');
});

it('credits On Target Africa in the public footer and on the coming-soon page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Official media partner')
        ->assertSee('https://www.ontargetafrica.co.za/', false)
        ->assertSee('rel="noopener noreferrer"', false);

    $this->get(route('coming-soon'))
        ->assertOk()
        ->assertSee('Official media partner')
        ->assertSee('https://www.ontargetafrica.co.za/', false);
});
