<?php

use App\Enums\EntryCollection;
use App\Enums\EventStatus;
use App\Enums\ListingStatus;
use App\Models\ClubGuestVisit;
use App\Models\ClubMember;
use App\Models\Event;
use App\Models\MembershipInvoice;
use App\Models\Organisation;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->withoutVite();
});

it('lets an unpaid club publish a match that links to the organiser', function () {
    $club = Organisation::factory()->create();

    $event = Event::factory()->create([
        'host_organisation_id' => $club->id,
        'status' => EventStatus::Confirmed,
        'entry_url' => 'https://example.com/enter',
        'entry_collection' => EntryCollection::External,
        'accepts_platform_entries' => false,
        'accepts_team_entries' => false,
    ]);

    $event->update(['status' => EventStatus::EntriesOpen]);

    expect($event->fresh()->status)->toBe(EventStatus::EntriesOpen)
        ->and($event->fresh()->entry_url)->toBe('https://example.com/enter');

    $this->get(route('clubs.show', $club->slug))->assertOk();
});

it('rejects on-site entries, members, invoices and guest days without the club plan', function () {
    $club = Organisation::factory()->create([
        'membership_enabled' => true,
        'accepts_applications' => true,
    ]);

    expect($club->acceptsMembershipApplications())->toBeFalse();

    expect(fn () => Event::factory()->create([
        'host_organisation_id' => $club->id,
        'accepts_platform_entries' => true,
    ]))->toThrow(ValidationException::class);

    expect(fn () => Event::factory()->create([
        'host_organisation_id' => $club->id,
        'accepts_team_entries' => true,
        'team_size' => 4,
    ]))->toThrow(ValidationException::class);

    expect(fn () => Event::factory()->create([
        'host_organisation_id' => $club->id,
        'entry_collection' => EntryCollection::Paystack,
    ]))->toThrow(ValidationException::class);

    expect(fn () => ClubMember::factory()->for($club, 'organisation')->create())
        ->toThrow(ValidationException::class);

    expect(fn () => MembershipInvoice::factory()->create(['organisation_id' => $club->id]))
        ->toThrow(ValidationException::class);

    expect(fn () => ClubGuestVisit::factory()->create(['organisation_id' => $club->id]))
        ->toThrow(ValidationException::class);
});

it('serves a club embed only while that club is paid', function () {
    $club = Organisation::factory()->create([
        'slug' => 'unpaid-embed-club',
        'status' => ListingStatus::Published,
    ]);
    Event::factory()->confirmed()->create([
        'title' => 'Unpaid Club Match',
        'host_organisation_id' => $club->id,
        'starts_at' => now()->addWeek(),
    ]);

    $this->get(route('embed.calendar', ['club' => $club->slug]))->assertNotFound();
    $this->get(route('embed.calendar'))->assertOk();

    $club->forceFill([
        'plan' => 'club',
        'plan_expires_at' => now()->addYear(),
    ])->save();

    $this->get(route('embed.calendar', ['club' => $club->slug]))
        ->assertOk()
        ->assertSee('Unpaid Club Match');
});
