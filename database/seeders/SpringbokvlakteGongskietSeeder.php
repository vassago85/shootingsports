<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VenueAccess;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class SpringbokvlakteGongskietSeeder extends Seeder
{
    public function run(): void
    {
        $discipline = Discipline::query()->where('slug', 'gong-shooting')->firstOrFail();

        $host = Organisation::query()->updateOrCreate(
            ['slug' => 'springbokvlakte-gong-skiet'],
            [
                'name' => 'Springbokvlakte Gong Skiet',
                'short_name' => 'Springbokvlakte',
                'type' => OrganisationType::Series,
                'province' => Province::Limpopo,
                'town' => 'Mookgophong',
                'phone' => '082 828 9734',
                'description' => 'Gongskiet on the Springbok Flats. Mik, skiet, geniet.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $host->syncDisciplines([$discipline->id], $discipline->id);

        $venue = Venue::query()->updateOrCreate(
            ['slug' => 'plaas-middeldoorn'],
            [
                'name' => 'Plaas Middeldoorn',
                'province' => Province::Limpopo,
                'town' => 'Mookgophong',
                'max_distance_m' => 450,
                'notes' => 'Springbokvlakte Gongskiet venue. Contact 082 828 9734. Snacks and cash bar on the poster.',
                'access' => VenueAccess::GuestByArrangement,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $event = Event::query()->updateOrCreate(
            ['slug' => 'springbokvlakte-gongskiet-2026'],
            [
                'title' => 'Gongskiet',
                'host_organisation_id' => $host->id,
                'venue_id' => $venue->id,
                'starts_at' => '2026-10-31 08:00:00',
                'ends_at' => null,
                'all_day' => true,
                'level' => EventLevel::Series,
                'status' => EventStatus::EntriesOpen,
                'confirmed_at' => now(),
                'entry_fee_cents' => 300000,
                'entry_url' => $this->storeEntryForm(),
                'accepts_team_entries' => true,
                'team_size' => 4,
                'entry_payment_instructions' => "Entry fee: R3 000 per team of 4.\nEFT to CVO Marmer, FNB savings account 62310133632.\nReference: your team name.\nIf you did not upload proof of payment with your entry, WhatsApp it to 082 828 9734.\nEntries close 21 October 2026. Enquiries: Ian Grobler, 082 828 9734.",
                'description' => "Saturday 31 October 2026 at Plaas Middeldoorn, Morolong, Limpopo. R3 000 per team of 4, including coffee and rusks in the morning, a light lunch and dinner. Extra dinner tickets R150 per person. Six gong lanes with five gongs each, maximum about 450 m, maximum calibre .30 (300). Check-in from 06:00, opening 07:30, shooting from 08:00. Distances are sent out a week before the match. Prize money on the poster: R15 000 / R10 000 / R5 000 / R3 000 / R2 000 plus many other prizes.\n\nBring your licence and rifles in rifle bags. Muzzle brakes are allowed. No bipods, monopods or other aids; a small sandbag may be used. Every shooter must sign an indemnity form on the day before shooting. No alcohol during the shoot; the cash bar opens afterwards.\n\nTo enter, pay by EFT to CVO Marmer, FNB savings account 62310133632, reference your team name. Then enter your team below and upload your proof of payment, or WhatsApp the paper entry form and proof of payment to 082 828 9734. Entries close 21 October 2026. Questions: use Ask the organiser on this page, or phone Ian Grobler on 082 828 9734.",
                'banner_path' => $this->storeBanner(),
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }

    private function storeBanner(): ?string
    {
        $filename = 'springbokvlakte-gongskiet-2026.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'springbokvlakte-gongskiet-2026')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }

    /**
     * The organiser takes entries on a printable form emailed back with
     * proof of payment, so "Enter here" opens the form itself.
     */
    private function storeEntryForm(): ?string
    {
        $filename = 'springbokvlakte-gongskiet-2026-entry-form.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'springbokvlakte-gongskiet-2026')->value('entry_url');
        }

        $path = 'event-entry-forms/'.$filename;
        Storage::disk('media')->put($path, file_get_contents($source));

        return Storage::disk('media')->url($path);
    }
}
