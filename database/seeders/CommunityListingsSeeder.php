<?php

namespace Database\Seeders;

use App\Enums\EventKind;
use App\Enums\EventLevel;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Enums\ListingStatus;
use App\Enums\OrganisationType;
use App\Enums\Province;
use App\Enums\VerificationState;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use Illuminate\Database\Seeder;

/**
 * Unclaimed clubs, series and the dates supplied for them.
 * Idempotent on slug. Unknown fields stay null.
 */
class CommunityListingsSeeder extends Seeder
{
    public function run(): void
    {
        $gong = Discipline::query()->where('slug', 'gong-shooting')->firstOrFail();
        $elr = Discipline::query()->where('slug', 'elr')->firstOrFail();
        $fullbore = Discipline::query()->where('slug', 'bisley-fullbore')->firstOrFail();
        $action = Discipline::query()->where('slug', 'action-defensive')->firstOrFail();

        $sabu = $this->organisation(
            slug: 'sabu',
            name: 'South African Bisley Union',
            shortName: 'SABU',
            type: OrganisationType::Federation,
            province: null,
            town: null,
            website: 'https://sabisley.com/',
            description: null,
        );
        $sabu->syncDisciplines([$fullbore->id], $fullbore->id);

        $wpba = Organisation::query()->where('slug', 'wpba')->first();
        if ($wpba instanceof Organisation && $wpba->parent_id === null) {
            $wpba->forceFill(['parent_id' => $sabu->id])->save();
        }

        $natal = $this->organisation(
            slug: 'natal-gong-series',
            name: 'Natal Gong Series',
            shortName: null,
            type: OrganisationType::Series,
            province: Province::KwaZuluNatal,
            town: null,
            website: null,
            description: null,
        );
        $natal->syncDisciplines([$gong->id], $gong->id);

        $peregrine = $this->organisation(
            slug: 'peregrine-bullets',
            name: 'Peregrine Bullets',
            shortName: null,
            type: OrganisationType::Association,
            province: null,
            town: null,
            website: null,
            description: 'Hosts the Forster 2-Mile Challenge and the Peregrine ELR Challenge. 2027 dates to be confirmed.',
            privateContact: 'JD Els, jd@peramco.co.za',
        );
        $peregrine->syncDisciplines([$elr->id], $elr->id);

        $sweetspot = $this->organisation(
            slug: 'sweetspot-reloading',
            name: 'Sweetspot Reloading',
            shortName: null,
            type: OrganisationType::Association,
            province: null,
            town: null,
            website: null,
            description: 'ELR events, linked to Peregrine Bullets.',
            parentId: $peregrine->id,
        );
        $sweetspot->syncDisciplines([$elr->id], $elr->id);

        $alrha = $this->organisation(
            slug: 'alrha',
            name: 'African Long Range Hunters Association',
            shortName: 'ALRHA',
            type: OrganisationType::Association,
            province: null,
            town: null,
            website: 'https://alrha.co.za/shoots',
            description: null,
        );
        $alrha->syncDisciplines([$elr->id], $elr->id);

        $this->venue('noodhulp-farm', 'Noodhulp Farm', Province::WesternCape, 'Paternoster', null);
        $this->venue('darling-steel-valley', 'Darling Steel Valley', Province::WesternCape, 'Darling', null);
        $this->venue('loeriesfontein', 'Loeriesfontein', null, null, null);
        $penbi = $this->venue(
            'penbi-game-ranch',
            'Penbi Game Ranch',
            Province::KwaZuluNatal,
            'Paulpietersburg',
            'Farm Mooihoek, near Paulpietersburg',
        );

        $weskus = $this->organisation(
            slug: 'weskus-sa-jagters',
            name: 'Weskus SA Jagters',
            shortName: null,
            type: OrganisationType::Club,
            province: Province::WesternCape,
            town: null,
            website: null,
            description: 'Hosts shoots at Noodhulp Farm, Paternoster.',
            privateContact: 'Leon Avenant',
        );

        $this->organisation(
            slug: 'winelands-precision',
            name: 'Winelands Precision',
            shortName: null,
            type: OrganisationType::Association,
            province: Province::WesternCape,
            town: null,
            website: null,
            description: 'Associated with Kleindoppie Skiet at Loeriesfontein.',
            privateContact: 'Lambert Brand and Perry de Jager',
        );

        $kleindoppie = $this->organisation(
            slug: 'kleindoppie-skiet',
            name: 'Kleindoppie Skiet',
            shortName: null,
            type: OrganisationType::Club,
            province: Province::WesternCape,
            town: null,
            website: null,
            description: 'Venues: Darling Steel Valley, Loeriesfontein and Noodhulp Farm, Paternoster.',
        );
        $kleindoppie->syncDisciplines([$gong->id], $gong->id);

        $montague = $this->organisation(
            slug: 'montague-pistol-club',
            name: 'Montague Pistol Club',
            shortName: null,
            type: OrganisationType::Club,
            province: Province::WesternCape,
            town: null,
            website: null,
            description: 'Shoots IDPA and gong shoot.',
        );
        $montague->syncDisciplines([$action->id, $gong->id], $gong->id);

        $this->organisation(
            slug: 'normark-africa',
            name: 'Normark Africa',
            shortName: null,
            type: OrganisationType::Association,
            province: null,
            town: null,
            website: null,
            description: 'Supplier. Also hosts matches.',
        );

        $whylo = $this->organisation(
            slug: 'whylo-distributors',
            name: 'Whylo Distributors',
            shortName: null,
            type: OrganisationType::Association,
            province: null,
            town: null,
            website: null,
            description: 'Supplier. Hosts the Swarovski Gong Challenge.',
        );

        $swarovski = $this->organisation(
            slug: 'swarovski-gong-challenge',
            name: 'Swarovski Gong Challenge',
            shortName: null,
            type: OrganisationType::Series,
            province: null,
            town: null,
            website: null,
            description: 'Hosted by Whylo Distributors.',
            parentId: $whylo->id,
        );
        $swarovski->syncDisciplines([$gong->id], $gong->id);

        $organisers = 'Nico Harris, +27 82 305 0743. Erhard Breet.';

        $this->event(
            slug: 'natal-gong-series-midlands-2027',
            title: 'Natal Gong Series, Midlands leg',
            host: $natal,
            venue: null,
            starts: '2027-02-18',
            ends: '2027-02-20',
            level: EventLevel::Series,
            discipline: $gong,
            description: 'KwaZulu-Natal Midlands. Venue not confirmed. Entries fill quickly.',
            privateContact: $organisers,
            importNotes: null,
        );

        $this->event(
            slug: 'natal-gong-series-penbi-2027',
            title: 'Natal Gong Series, Penbi leg',
            host: $natal,
            venue: $penbi,
            starts: '2027-11-18',
            ends: '2027-11-20',
            level: EventLevel::Series,
            discipline: $gong,
            description: 'Penbi Game Ranch, Farm Mooihoek, near Paulpietersburg, KwaZulu-Natal. Entries fill quickly.',
            privateContact: $organisers,
            importNotes: null,
        );

        $this->event(
            slug: 'koning-van-die-myl-2026',
            title: 'Koning van die Myl',
            host: $weskus,
            venue: null,
            starts: '2026-11-28',
            ends: null,
            level: EventLevel::Club,
            discipline: $elr,
            description: 'Hosted by Weskus SA Jagters. Venue not confirmed.',
            privateContact: null,
            importNotes: 'Listed as long range. The closest existing event type is ELR.',
        );
    }

    private function organisation(
        string $slug,
        string $name,
        ?string $shortName,
        OrganisationType $type,
        ?Province $province,
        ?string $town,
        ?string $website,
        ?string $description,
        ?string $privateContact = null,
        ?int $parentId = null,
    ): Organisation {
        $attributes = [
            'name' => $name,
            'short_name' => $shortName,
            'type' => $type,
            'parent_id' => $parentId,
            'province' => $province,
            'town' => $town,
            'private_contact' => $privateContact,
            'website_url' => $website,
            'description' => $description,
        ];

        if (! Organisation::query()->where('slug', $slug)->exists()) {
            $attributes += [
                'email' => null,
                'phone' => null,
                'accredited' => false,
                'visitors_welcome' => false,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'claimed_by' => null,
                'source' => ListingSource::Staff,
            ];
        }

        return Organisation::query()->updateOrCreate(['slug' => $slug], $attributes);
    }

    private function venue(string $slug, string $name, ?Province $province, ?string $town, ?string $address): Venue
    {
        $attributes = [
            'name' => $name,
            'province' => $province,
            'town' => $town,
            'address' => $address,
        ];

        if (! Venue::query()->where('slug', $slug)->exists()) {
            $attributes += [
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'claimed_by' => null,
                'source' => ListingSource::Staff,
            ];
        }

        return Venue::query()->updateOrCreate(['slug' => $slug], $attributes);
    }

    private function event(
        string $slug,
        string $title,
        Organisation $host,
        ?Venue $venue,
        string $starts,
        ?string $ends,
        EventLevel $level,
        Discipline $discipline,
        string $description,
        ?string $privateContact,
        ?string $importNotes,
    ): void {
        $attributes = [
            'title' => $title,
            'host_organisation_id' => $host->id,
            'venue_id' => $venue?->id,
            'starts_at' => $starts.' 00:00:00',
            'ends_at' => $ends !== null ? $ends.' 00:00:00' : null,
            'all_day' => true,
            'level' => $level,
            'kind' => EventKind::Competition,
            'status' => EventStatus::Planned,
            'private_contact' => $privateContact,
            'description' => $description,
            'import_notes' => $importNotes,
        ];

        if (! Event::query()->where('slug', $slug)->exists()) {
            $attributes += [
                'confirmed_at' => null,
                'contact_email' => null,
                'source' => ListingSource::Staff,
            ];
        }

        $event = Event::query()->updateOrCreate(['slug' => $slug], $attributes);

        $event->syncDisciplines([$discipline->id], $discipline->id);
    }
}
