<?php

namespace Database\Seeders;

use App\Enums\EventKind;
use App\Enums\EventLevel;
use App\Enums\EventPublication;
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

/**
 * Western Province Bisley Association 2027 programme.
 *
 * Typed from the one-page programme Louise Taylor sent in November 2026
 * ("2027 PROGRAM - Updated November 2026"). Chairman on that note:
 * Rick Jaffe. The association's own site is http://www.wpbisley.co.za/
 * and it names Good Hope Shooting Range, Atlantis.
 *
 * Range-closed days and the 18 September target-maintenance day are
 * not matches, so they are not events. The programme prints 27-Feb-26
 * for the 303 Club short-range first leg; that Saturday sits in the
 * 2027 list, so the stored date is 27 February 2027. It also prints
 * "700mm" for the 6 March league; that is stored as 700 m.
 *
 * Idempotent. Organisations and venues are firstOrCreate so a later
 * desk edit is kept. Events are updateOrCreate on the slug so a
 * corrected programme can be re-seeded.
 */
class WpbaProgramme2027Seeder extends Seeder
{
    public function run(): void
    {
        $fullbore = Discipline::query()->where('slug', 'bisley-fullbore')->firstOrFail();
        $targetRifle = Discipline::query()->where('slug', 'target-rifle')->firstOrFail();

        $hosts = $this->hosts($fullbore, $targetRifle);
        $venues = $this->venues();
        $disciplines = [
            'bisley-fullbore' => $fullbore,
            'target-rifle' => $targetRifle,
        ];

        foreach ($this->matches() as $match) {
            $host = $hosts[$match['host'] ?? 'wpba'];
            $venue = $venues[$match['venue'] ?? 'good-hope'];
            $discipline = $disciplines[$match['discipline'] ?? 'bisley-fullbore'];

            $event = Event::query()->updateOrCreate(
                ['slug' => $match['slug']],
                [
                    'title' => $match['title'],
                    'host_organisation_id' => $host->id,
                    'venue_id' => $venue->id,
                    'starts_at' => $match['starts_at'].' 00:00:00',
                    'ends_at' => isset($match['ends_at']) ? $match['ends_at'].' 00:00:00' : null,
                    'all_day' => true,
                    'level' => $match['level'],
                    'kind' => EventKind::Competition,
                    'status' => EventStatus::Confirmed,
                    'publication' => EventPublication::Published,
                    'confirmed_at' => now(),
                    'entry_url' => $match['entry_url'] ?? null,
                    'website_url' => $host->website_url,
                    'description' => $this->describe($match),
                    'source' => ListingSource::Staff,
                    'last_verified_at' => now(),
                    'import_notes' => $match['notes'] ?? null,
                    'needs_review' => $match['review'] ?? false,
                ],
            );

            $event->syncDisciplines([$discipline->id], $discipline->id);
        }
    }

    /**
     * @return array<string, Organisation>
     */
    private function hosts(Discipline $fullbore, Discipline $targetRifle): array
    {
        $sabu = $this->organisation(
            slug: 'sabu',
            name: 'South African Bisley Union',
            shortName: 'SABU',
            type: OrganisationType::Federation,
            province: null,
            town: null,
            website: 'https://sabisley.com/',
            description: 'National governing body for Bisley target rifle in South Africa. The 2027 SA Open page names Genl De Wet Shooting Range, Bloemfontein.',
            accredited: true,
        );

        $wpba = $this->organisation(
            slug: 'wpba',
            name: 'Western Province Bisley Association',
            shortName: 'WPBA',
            type: OrganisationType::ProvincialBody,
            province: Province::WesternCape,
            town: 'Atlantis',
            website: 'http://www.wpbisley.co.za/',
            description: 'Provincial Bisley association for the Western Cape, affiliated to the South African Bisley Union. The 2027 programme is shot at Good Hope Shooting Range, Atlantis, and is subject to change. Chairman: Rick Jaffe.',
            accredited: true,
            parent: $sabu,
            logo: true,
        );

        $prc = $this->organisation(
            slug: 'peninsula-rifle-club',
            name: 'Peninsula Rifle Club',
            shortName: 'PRC',
            type: OrganisationType::Club,
            province: Province::WesternCape,
            town: 'Atlantis',
            website: 'http://www.wpbisley.co.za/',
            description: 'Peninsula Rifle Club, listed by the Western Province Bisley Association. The 2027 .22 and club Bisley shoots are at Good Hope Shooting Range, Atlantis.',
            accredited: false,
            parent: $wpba,
        );

        $club303 = $this->organisation(
            slug: 'cli-303-club-of-cape-town',
            name: 'CLI 303 Club of Cape Town',
            shortName: '303 Club',
            type: OrganisationType::Club,
            province: Province::WesternCape,
            town: 'Atlantis',
            website: 'http://www.wpbisley.co.za/',
            description: 'CLI 303 Club of Cape Town, listed by the Western Province Bisley Association. The 2027 programme places their shoots at Good Hope Shooting Range, Atlantis.',
            accredited: false,
            parent: $wpba,
        );

        $stellenbosch = $this->organisation(
            slug: 'stellenbosch-bisley-club',
            name: 'Stellenbosch Bisley Club',
            shortName: 'Stellenbosch',
            type: OrganisationType::Club,
            province: Province::WesternCape,
            town: 'Stellenbosch',
            website: 'http://www.wpbisley.co.za/',
            description: 'Stellenbosch Bisley Club, listed by the Western Province Bisley Association. The 2027 club Bisley is at Good Hope Shooting Range, Atlantis.',
            accredited: false,
            parent: $wpba,
        );

        $winelands = $this->organisation(
            slug: 'winelands-bisley-club',
            name: 'Winelands Bisley Club',
            shortName: 'Winelands',
            type: OrganisationType::Club,
            province: Province::WesternCape,
            town: null,
            website: 'http://www.wpbisley.co.za/',
            description: 'Winelands Bisley Club, listed by the Western Province Bisley Association. The 2027 club Bisley is at Good Hope Shooting Range, Atlantis.',
            accredited: false,
            parent: $wpba,
        );

        $this->syncDisciplinesIfEmpty($sabu, [$fullbore->id], $fullbore->id);
        $this->syncDisciplinesIfEmpty($wpba, [$fullbore->id, $targetRifle->id], $fullbore->id);
        $this->syncDisciplinesIfEmpty($prc, [$targetRifle->id, $fullbore->id], $targetRifle->id);
        $this->syncDisciplinesIfEmpty($club303, [$fullbore->id], $fullbore->id);
        $this->syncDisciplinesIfEmpty($stellenbosch, [$fullbore->id], $fullbore->id);
        $this->syncDisciplinesIfEmpty($winelands, [$fullbore->id], $fullbore->id);

        return [
            'sabu' => $sabu,
            'wpba' => $wpba,
            'prc' => $prc,
            'club303' => $club303,
            'stellenbosch' => $stellenbosch,
            'winelands' => $winelands,
        ];
    }

    /**
     * @return array<string, Venue>
     */
    private function venues(): array
    {
        $goodHope = Venue::query()->firstOrCreate(
            ['slug' => 'good-hope-shooting-range'],
            [
                'name' => 'Good Hope Shooting Range',
                'province' => Province::WesternCape,
                'town' => 'Atlantis',
                'max_distance_m' => 800,
                'access' => VenueAccess::GuestByArrangement,
                'description' => 'SANDF range at Atlantis (De Goede Hoop Skietbaan), used by the Western Province Bisley Association. The 2027 programme goes out to 800 m. Anyone shooting here must send WPBA a SAPS clearance certificate, or the receipt, and a copy of their ID at least two weeks before the event.',
                'notes' => 'WPBA public calendars and the association site name this range Good Hope Shooting Range, Atlantis.',
                'website_url' => 'http://www.wpbisley.co.za/',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        $genlDeWet = Venue::query()->firstOrCreate(
            ['slug' => 'genl-de-wet-shooting-range'],
            [
                'name' => 'Genl De Wet Shooting Range',
                'province' => Province::FreeState,
                'town' => 'Bloemfontein',
                'access' => VenueAccess::GuestByArrangement,
                'description' => 'Bloemfontein range named by the South African Bisley Union for the 2027 SA Open. The WPBA 2027 programme lists the SA Championships, the SA Long Range Championships and the Free State Championships at Bloem.',
                'notes' => 'SABU 2027 SA Open page names Genl De Wet Shooting Range, Bloemfontein. The WPBA programme says only Bloem.',
                'website_url' => 'https://sabisley.com/sa-open-2027/',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
            ],
        );

        return [
            'good-hope' => $goodHope,
            'genl-de-wet' => $genlDeWet,
        ];
    }

    private function organisation(
        string $slug,
        string $name,
        string $shortName,
        OrganisationType $type,
        ?Province $province,
        ?string $town,
        ?string $website,
        string $description,
        bool $accredited,
        ?Organisation $parent = null,
        bool $logo = false,
    ): Organisation {
        $organisation = Organisation::query()->firstOrCreate(
            ['slug' => $slug],
            [
                'name' => $name,
                'short_name' => $shortName,
                'type' => $type,
                'parent_id' => $parent?->id,
                'province' => $province,
                'town' => $town,
                'website_url' => $website,
                'description' => $description,
                'accredited' => $accredited,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );

        if ($logo && $organisation->logo_path === null) {
            $path = $this->storeLogo();

            if ($path !== null) {
                $organisation->logo_path = $path;
                $organisation->save();
            }
        }

        return $organisation;
    }

    /**
     * @param  list<int>  $ids
     */
    private function syncDisciplinesIfEmpty(Organisation $organisation, array $ids, int $primaryId): void
    {
        if ($organisation->disciplines()->exists()) {
            return;
        }

        $organisation->syncDisciplines($ids, $primaryId);
    }

    /**
     * @param  array<string, mixed>  $match
     */
    private function describe(array $match): string
    {
        $sentences = ['From the Western Province Bisley Association 2027 programme, updated November 2026.'];

        $distances = $match['distances'] ?? null;
        $rounds = $match['rounds'] ?? null;

        if (is_string($distances) && $distances !== '') {
            $sentences[] = 'Distances: '.$distances.'.';
        }

        if (is_string($rounds) && $rounds !== '') {
            $sentences[] = 'Round count on the programme: '.$rounds.'.';
        }

        $extra = $match['extra'] ?? null;

        if (is_string($extra) && $extra !== '') {
            $sentences[] = $extra;
        }

        if (($match['venue'] ?? 'good-hope') === 'good-hope') {
            $sentences[] = 'Good Hope is a SANDF range at Atlantis. WPBA requires a SAPS clearance certificate, or the receipt, and a copy of ID at least two weeks before the shoot.';
        }

        return implode(' ', $sentences);
    }

    /**
     * Shoots only. Range-closed and target-maintenance rows from the
     * programme are omitted.
     *
     * @return list<array<string, mixed>>
     */
    private function matches(): array
    {
        return [
            [
                'slug' => 'wpba-2027-league-individual-1',
                'title' => 'WPBA League Individual 1',
                'starts_at' => '2027-01-16',
                'level' => EventLevel::Series,
                'distances' => '300 m, 500 m, 600 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-303-colin-leon-memorial',
                'title' => '303 Club Colin Leon Memorial & AGM',
                'host' => 'club303',
                'starts_at' => '2027-01-30',
                'level' => EventLevel::Club,
                'distances' => '100 m',
                'rounds' => '2 x 3 + 10',
            ],
            [
                'slug' => 'wpba-2027-league-individual-2',
                'title' => 'WPBA Individual League 2 & AGM',
                'starts_at' => '2027-02-06',
                'level' => EventLevel::Series,
                'distances' => '300 m, 500 m, 600 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-prc-22-2027-02-13',
                'title' => 'PRC .22',
                'host' => 'prc',
                'starts_at' => '2027-02-13',
                'level' => EventLevel::Club,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 150 m, 200 m',
                'rounds' => '7, 7, 7',
            ],
            [
                'slug' => 'wpba-2027-open',
                'title' => 'Open',
                'starts_at' => '2027-02-20',
                'level' => EventLevel::Club,
                'extra' => 'The programme names this Saturday only as OPEN, with no distances or round count.',
                'notes' => 'Programme event name is OPEN. No club, distances or round count are printed.',
                'review' => true,
            ],
            [
                'slug' => 'wpba-2027-303-short-range-1',
                'title' => '303 Club Short Range 1st Leg',
                'host' => 'club303',
                'starts_at' => '2027-02-27',
                'level' => EventLevel::Club,
                'distances' => '200 m, 300 m',
                'rounds' => '2 x 3 + 10',
                'notes' => 'The programme prints 27-Feb-26. That Saturday sits in the 2027 list, between 20 February and 6 March, so the stored date is 27 February 2027.',
                'review' => true,
            ],
            [
                'slug' => 'wpba-2027-league-individual-3',
                'title' => 'WPBA Individual League 3',
                'starts_at' => '2027-03-06',
                'level' => EventLevel::Series,
                'distances' => '700 m, 800 m',
                'rounds' => '15, 15',
                'notes' => 'The programme prints 700mm, 800m. Stored as 700 m and 800 m.',
            ],
            [
                'slug' => 'wpba-2027-prc-22-2027-03-13',
                'title' => 'PRC .22',
                'host' => 'prc',
                'starts_at' => '2027-03-13',
                'level' => EventLevel::Club,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 200 m, 300 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-prc-club-bisley',
                'title' => 'PRC Club Bisley and AGM',
                'host' => 'prc',
                'starts_at' => '2027-03-20',
                'level' => EventLevel::Club,
                'distances' => '300 m, 500 m, 700 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-practice',
                'title' => 'WPBA Practice Shoot',
                'starts_at' => '2027-04-03',
                'level' => EventLevel::Club,
                'distances' => '700 m, 800 m',
                'rounds' => '15, 15',
            ],
            [
                'slug' => 'wpba-2027-free-state-championships',
                'title' => 'Free State Championships',
                'host' => 'sabu',
                'venue' => 'genl-de-wet',
                'starts_at' => '2027-04-09',
                'level' => EventLevel::Provincial,
                'distances' => 'As per the program',
                'extra' => 'The programme lists the place as Bloem and does not name the host association. This row uses the South African Bisley Union and Genl De Wet Shooting Range because SABU names that Bloemfontein range for the 2027 SA Open.',
                'notes' => 'Host association is not on the WPBA programme. Venue is inferred from the SABU 2027 SA Open page, which names Genl De Wet and still says its own programme is coming soon.',
                'review' => true,
            ],
            [
                'slug' => 'wpba-2027-prc-22-open-championships',
                'title' => 'PRC .22 Open Championships',
                'host' => 'prc',
                'starts_at' => '2027-04-10',
                'level' => EventLevel::Club,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 200 m, 300 m',
                'rounds' => '10, 10, 15',
            ],
            [
                'slug' => 'wpba-2027-sa-championships',
                'title' => 'SA Championships',
                'host' => 'sabu',
                'venue' => 'genl-de-wet',
                'starts_at' => '2027-04-10',
                'ends_at' => '2027-04-17',
                'level' => EventLevel::National,
                'distances' => 'As per the program',
                'entry_url' => 'https://sabisley.com/sa-open-2027/',
                'extra' => 'Printed as 10–17 April at Bloem. SABU calls the 2027 meeting the SA Open, names Genl De Wet Shooting Range, Bloemfontein, and still says the programme and entry forms are coming soon.',
                'notes' => 'Dates come from the WPBA programme. SABU has not published the 2027 programme yet.',
                'review' => true,
            ],
            [
                'slug' => 'wpba-2027-303-short-range-2',
                'title' => '303 Club Short Range 2nd Leg',
                'host' => 'club303',
                'starts_at' => '2027-04-24',
                'level' => EventLevel::Club,
                'distances' => '300 m, 400 m',
                'rounds' => '2 x 3 + 10',
            ],
            [
                'slug' => 'wpba-2027-prc-22-autumn-2027-05-08',
                'title' => 'PRC .22 Autumn League',
                'host' => 'prc',
                'starts_at' => '2027-05-08',
                'level' => EventLevel::Series,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 150 m, 200 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-stellenbosch-club-bisley',
                'title' => 'Stellenbosch Club Bisley',
                'host' => 'stellenbosch',
                'starts_at' => '2027-05-15',
                'level' => EventLevel::Club,
                'distances' => 'TBC',
                'rounds' => 'TBC',
            ],
            [
                'slug' => 'wpba-2027-303-mid-range-1',
                'title' => '303 Club Mid Range 1st Leg',
                'host' => 'club303',
                'starts_at' => '2027-05-29',
                'level' => EventLevel::Club,
                'distances' => '400 m, 500 m',
                'rounds' => '2 x 3 + 10',
            ],
            [
                'slug' => 'wpba-2027-george-bramley-1',
                'title' => 'WPBA George Bramley 1',
                'starts_at' => '2027-06-05',
                'level' => EventLevel::Series,
                'distances' => '700 m, 800 m',
                'rounds' => '15, 15',
            ],
            [
                'slug' => 'wpba-2027-prc-22-autumn-2027-06-12',
                'title' => 'PRC .22 Autumn League',
                'host' => 'prc',
                'starts_at' => '2027-06-12',
                'level' => EventLevel::Series,
                'discipline' => 'target-rifle',
                'distances' => '150 m, 200 m',
                'rounds' => '15, 15',
            ],
            [
                'slug' => 'wpba-2027-teams-league-1',
                'title' => 'WPBA Teams League 1',
                'starts_at' => '2027-06-19',
                'level' => EventLevel::Series,
                'distances' => '300 m, 500 m, 600 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-303-mid-range-2',
                'title' => '303 Club Mid Range 2nd Leg',
                'host' => 'club303',
                'starts_at' => '2027-06-26',
                'level' => EventLevel::Club,
                'distances' => '500 m, 600 m',
                'rounds' => '2 x 3 + 10',
            ],
            [
                'slug' => 'wpba-2027-george-bramley-2',
                'title' => 'WPBA George Bramley 2',
                'starts_at' => '2027-07-03',
                'level' => EventLevel::Series,
                'distances' => '700 m, 800 m',
                'rounds' => '15, 15',
            ],
            [
                'slug' => 'wpba-2027-prc-22-autumn-2027-07-10',
                'title' => 'PRC .22 Autumn League',
                'host' => 'prc',
                'starts_at' => '2027-07-10',
                'level' => EventLevel::Series,
                'discipline' => 'target-rifle',
                'distances' => '150 m, 200 m, 300 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-team-league-2',
                'title' => 'WPBA Team League 2',
                'starts_at' => '2027-07-17',
                'level' => EventLevel::Series,
                'distances' => '500 m, 600 m, 700 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-303-long-range-1',
                'title' => '303 Club Long Range 1st Leg',
                'host' => 'club303',
                'starts_at' => '2027-07-31',
                'level' => EventLevel::Club,
                'distances' => '700 m, 800 m',
                'rounds' => '2 x 3 + 10',
            ],
            [
                'slug' => 'wpba-2027-george-bramley-3',
                'title' => 'WPBA George Bramley 3',
                'starts_at' => '2027-08-07',
                'level' => EventLevel::Series,
                'distances' => '700 m, 800 m',
                'rounds' => '15, 15',
            ],
            [
                'slug' => 'wpba-2027-sa-long-range',
                'title' => 'SA Long Range Championships',
                'host' => 'sabu',
                'venue' => 'genl-de-wet',
                'starts_at' => '2027-08-12',
                'ends_at' => '2027-08-14',
                'level' => EventLevel::National,
                'distances' => 'As per the program',
                'extra' => 'Printed as 12–14 August at Bloem. The range is Genl De Wet because that is the Bloemfontein range SABU names for 2027.',
                'notes' => 'The WPBA programme says Bloem and does not name the range. SABU had not published a 2027 long-range page when this was entered.',
                'review' => true,
            ],
            [
                'slug' => 'wpba-2027-prc-22-2027-08-14',
                'title' => 'PRC .22',
                'host' => 'prc',
                'starts_at' => '2027-08-14',
                'level' => EventLevel::Club,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 150 m',
                'rounds' => '15, 15',
            ],
            [
                'slug' => 'wpba-2027-winelands-club-bisley',
                'title' => 'Winelands Club Bisley',
                'host' => 'winelands',
                'starts_at' => '2027-08-21',
                'level' => EventLevel::Club,
                'distances' => 'TBC',
                'rounds' => 'TBC',
            ],
            [
                'slug' => 'wpba-2027-303-second-leg',
                'title' => '303 Club 2nd Leg',
                'host' => 'club303',
                'starts_at' => '2027-08-28',
                'level' => EventLevel::Club,
                'distances' => '700 m, 800 m',
                'rounds' => '2 x 3 + 10',
            ],
            [
                'slug' => 'wpba-2027-league-teams-3',
                'title' => 'WPBA League Teams 3',
                'starts_at' => '2027-09-04',
                'level' => EventLevel::Series,
                'distances' => '700 m, 800 m',
                'rounds' => '15, 15',
            ],
            [
                'slug' => 'wpba-2027-wp-open-22',
                'title' => 'WP Open .22 Championships (PRC)',
                'host' => 'prc',
                'starts_at' => '2027-09-11',
                'level' => EventLevel::Provincial,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 150 m, 200 m, 300 m',
                'rounds' => '10, 10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-303-practice',
                'title' => '303 Club Practice Day',
                'host' => 'club303',
                'starts_at' => '2027-09-25',
                'level' => EventLevel::Club,
                'distances' => 'TBC',
                'rounds' => 'TBC',
            ],
            [
                'slug' => 'wpba-2027-wp-championships',
                'title' => 'Western Province Championships',
                'starts_at' => '2027-09-29',
                'ends_at' => '2027-10-02',
                'level' => EventLevel::Provincial,
                'distances' => 'As per the program',
            ],
            [
                'slug' => 'wpba-2027-prc-22-2027-10-09',
                'title' => 'PRC .22',
                'host' => 'prc',
                'starts_at' => '2027-10-09',
                'level' => EventLevel::Club,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 200 m',
                'rounds' => '10, 10',
            ],
            [
                'slug' => 'wpba-2027-league-team-4',
                'title' => 'WPBA League Team 4',
                'starts_at' => '2027-10-16',
                'level' => EventLevel::Series,
                'distances' => '300 m, 600 m, 800 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-303-pairs-prizegiving',
                'title' => '303 Club Pairs & Prize-giving',
                'host' => 'club303',
                'starts_at' => '2027-10-30',
                'level' => EventLevel::Club,
                'distances' => '500 m',
                'rounds' => '3 + 15',
            ],
            [
                'slug' => 'wpba-2027-spoon-shoot',
                'title' => 'WPBA Spoon Shoot',
                'starts_at' => '2027-11-06',
                'level' => EventLevel::Club,
                'distances' => '300 m, 600 m, 800 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-prc-22-2027-11-13',
                'title' => 'PRC .22',
                'host' => 'prc',
                'starts_at' => '2027-11-13',
                'level' => EventLevel::Club,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 150 m, 200 m',
                'rounds' => '10, 10, 10',
            ],
            [
                'slug' => 'wpba-2027-pairs-shoot',
                'title' => 'WPBA Pairs Shoot (Annual Prize giving)',
                'starts_at' => '2027-11-20',
                'level' => EventLevel::Club,
                'distances' => '500 m, 600 m',
                'rounds' => '10, 10',
            ],
            [
                'slug' => 'wpba-2027-303-pairs-away',
                'title' => '303 Club Pairs Away Shoot',
                'host' => 'club303',
                'starts_at' => '2027-11-27',
                'level' => EventLevel::Club,
                'distances' => '200 m, 300 m, 500 m and further',
                'rounds' => '3 x 3 + 10',
                'extra' => 'The title says away shoot. The place column on the programme still says Good Hope.',
            ],
            [
                'slug' => 'wpba-2027-wineshoot',
                'title' => 'WPBA Wineshoot',
                'starts_at' => '2027-12-04',
                'level' => EventLevel::Club,
                'distances' => '500 m, 600 m',
                'rounds' => '10, 11',
            ],
            [
                'slug' => 'wpba-2027-prc-22-three-ships',
                'title' => 'PRC .22 Three Ships',
                'host' => 'prc',
                'starts_at' => '2027-12-11',
                'level' => EventLevel::Club,
                'discipline' => 'target-rifle',
                'distances' => '100 m, 200 m',
                'rounds' => '10, 11',
            ],
        ];
    }

    private function storeLogo(): ?string
    {
        $source = database_path('seeders/data/wpba-logo.png');

        if (! is_file($source)) {
            return Organisation::query()->where('slug', 'wpba')->value('logo_path');
        }

        $path = 'organisation-logos/wpba.png';
        Storage::disk('media')->put($path, file_get_contents($source));

        return $path;
    }
}
