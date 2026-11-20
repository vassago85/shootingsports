<?php

namespace Database\Seeders;

use App\Enums\EventKind;
use App\Enums\EventLevel;
use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\GeocodeSource;
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
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * False Bay Sports Shooting Club, from fbssc.co on 20 November 2026.
 *
 * The street address uses the contact page ("OUR ADDRESS", 7975).
 * The site footer also prints 7995. Opening hours use the contact
 * page. The homepage lists Saturday until 17:00 and Sunday until 13:00.
 * Bay facts are from FBSSC-Ranges_2024.pdf, the file linked on
 * /our-ranges/ (uploaded April 2026). Coordinates are the pin in
 * the Google Maps embed on the contact page.
 *
 * The club calendar is a public Google calendar. Open-ended rules
 * are written out through 31 March 2027, skipping the EXDATE days
 * on those rules. One-off events in that window are included.
 * The 5 December 2026 open day is taken from the club posters
 * (Operation: Adrenaline, 09:00–17:00), which agree with the
 * calendar date. A news post dated 6 December 2025 is not seeded.
 * The footer Instagram handle differs from the header
 * (falsebay.sportshootingclub); the header handle is stored.
 * The open-day poster prints Facebook as @FalseBaySC. The club
 * page keeps the header link, facebook.com/FalseBaySSC.
 */
class FalseBaySportsShootingClubSeeder extends Seeder
{
    public function run(): void
    {
        $disciplines = $this->disciplines();
        $primary = $disciplines->get('ipsc-practical');

        if ($primary === null) {
            throw new RuntimeException('IPSC Practical discipline is missing.');
        }

        $club = $this->club();
        $range = $this->range();
        $ids = $disciplines->pluck('id')->all();

        $club->syncDisciplines($ids, $primary->id);
        $range->syncDisciplines($ids, $primary->id);

        $this->openDay($club, $range);

        foreach ($this->sessions() as $session) {
            $event = Event::query()->updateOrCreate(
                ['slug' => $session['slug']],
                [
                    'title' => $session['title'],
                    'host_organisation_id' => $club->id,
                    'venue_id' => $range->id,
                    'starts_at' => $session['date'].' 00:00:00',
                    'ends_at' => null,
                    'all_day' => true,
                    'level' => $session['level'],
                    'kind' => $session['kind'],
                    'status' => EventStatus::Confirmed,
                    'publication' => EventPublication::Published,
                    'confirmed_at' => now(),
                    'website_url' => 'https://fbssc.co/calendar/',
                    'description' => $session['title'].', listed on the False Bay Sports Shooting Club calendar.',
                    'import_notes' => 'Public Google calendar embedded on https://fbssc.co/calendar/.',
                    'source' => ListingSource::Staff,
                    'last_verified_at' => now(),
                ],
            );

            $discipline = $session['discipline'] === null
                ? null
                : $disciplines->get($session['discipline']);

            if ($discipline !== null) {
                $event->syncDisciplines([$discipline->id], $discipline->id);
            }
        }
    }

    private function club(): Organisation
    {
        return Organisation::query()->updateOrCreate(
            ['slug' => 'false-bay-sports-shooting-club'],
            [
                'name' => 'False Bay Sports Shooting Club',
                'short_name' => 'FBSSC',
                'type' => OrganisationType::Club,
                'province' => Province::WesternCape,
                'town' => 'Glencairn',
                'email' => 'secretary@fbssc.co',
                'phone' => '063 393 7767',
                'private_contact' => 'Secretary secretary@fbssc.co. Club chair exco@fbssc.co. General manager Alwyn Marais, gm@fbssc.co. Postal address Postnet Suite #65, Private Bag X12, Tokai, 7966. YouTube https://www.youtube.com/channel/UC4OYIJpEotspOsjaRnrzBVg. Visitors https://member.fbssc.co/visitors/new. Members https://member.fbssc.co/users/sign_in.',
                'website_url' => 'https://fbssc.co/',
                'facebook_url' => 'https://www.facebook.com/FalseBaySSC/',
                'instagram_url' => 'https://www.instagram.com/falsebay.sportsshootingclub/',
                'description' => 'Sports shooting club in the Glencairn Quarry, Tercentenary Way, Glencairn, Cape Town. NPC 2012/010543/08. Affiliated to SAPSA, CTSASA and SADPA. The calendar runs IPSC, SADPA, MPDS, Steel Challenge, WTR cowboy action and Clay Busters. The club also offers rimfire, ICORE and tactical training. Annual membership is R830 standard, R1 600 gold with unlimited entry, or R350 pensioner, plus a once-off R250 registration. Member gate fee R50. Non-member gate fee R110. Shooting packages are booked on 021 782 0107.',
                'accredited' => false,
                'visitors_welcome' => true,
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
    }

    private function range(): Venue
    {
        return Venue::query()->updateOrCreate(
            ['slug' => 'false-bay-sports-shooting-range'],
            [
                'name' => 'False Bay Sports Shooting Range',
                'province' => Province::WesternCape,
                'town' => 'Glencairn',
                'address' => 'Tercentenary Way, Glencairn, Cape Town, 7975',
                'lat' => -34.1538764,
                'lng' => 18.4337522,
                'geocode_source' => GeocodeSource::Staff,
                'geocoded_at' => now(),
                'max_distance_m' => 120,
                'day_fee_cents' => 11000,
                'access' => VenueAccess::GuestByArrangement,
                'website_url' => 'https://fbssc.co/',
                'description' => "The club range in the Glencairn Quarry. The quarry closed in May 1978. Visitors are marked on Range 1 (handgun, 20 m), Range 2 (handgun, air rifle and air pistol, 20 m) and the rifle range (rifle only, 120 m). Other bays are members or vetted only. The non-member gate fee is R110. Members pay R50.\n\nOpening hours on the contact page are Monday to Friday and Saturday 10:00–16:30, and Sunday 10:00–12:30. The residential speed limit is 40 km/h.",
                'facilities' => $this->bays(),
                'notes' => 'Bay list from https://fbssc.co/wp-content/uploads/2026/04/FBSSC-Ranges_2024.pdf. Coordinates from the Google Maps embed on https://fbssc.co/contact/. The site footer prints 7995; the contact page address is 7975. The homepage lists Saturday until 17:00 and Sunday until 13:00.',
                'status' => ListingStatus::Published,
                'verification_state' => VerificationState::Unconfirmed,
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
    }

    /**
     * @return list<string>
     */
    private function bays(): array
    {
        return [
            'Range 1, visitors and members, handgun only, 20 m',
            'Range 2, visitors and members, handgun, air rifle and air pistol, 20 m',
            'Range 3, members, handgun, rifle and shotgun, 25 m',
            'Range 4, members, all calibre, 50 m',
            'Ronin Tac House 1, vetted only, all calibre',
            'Tac House 2, vetted only, all calibre',
            'Tac House 3, vetted only, all calibre',
            'Range 5, members, all calibre, 15 m',
            'Range 6, members, all calibre, 15 m',
            'Clay Trap Range, members, clay trap, 20 m',
            'Sporting Clays, members, sporting clays, 50 m',
            'High Tower, members, all calibre, 50 m',
            'High Tower 2, members, all calibre, 15 m',
            'Duck Pond Range, vetted only, all calibre, 40 m',
            'Middle Bay Range, vetted only, all calibre, 25 m',
            'Grassy Knoll, vetted only, all calibre, 35 m',
            'Academy Range 1, vetted only, False Bay Firearm Training Academy',
            'Academy Range 2, vetted only, False Bay Firearm Training Academy',
            'Seaview 1, members, all calibre, 25 m',
            'Seaview 2, members, all calibre, 25 m',
            'Seaview 3, members, all calibre, 25 m',
            'Rifle Range, visitors and members, rifle only, 120 m',
        ];
    }

    /**
     * Disciplines the club page and the 2024 ranges sheet both support.
     * Cowboy action, rimfire and ICORE are named on the site and have
     * no matching discipline row, so they stay in the description.
     *
     * @return Collection<string, Discipline>
     */
    private function disciplines(): Collection
    {
        $slugs = [
            'ipsc-practical',
            'action-defensive',
            'steel-challenge',
            'sporting-clays',
            'trap',
            '3-gun',
            'multigun',
        ];

        $disciplines = Discipline::query()->whereIn('slug', $slugs)->get()->keyBy('slug');

        foreach ($slugs as $slug) {
            if (! $disciplines->has($slug)) {
                throw new RuntimeException('Missing discipline ['.$slug.'].');
            }
        }

        return $disciplines;
    }

    private function openDay(Organisation $club, Venue $range): void
    {
        Event::query()->updateOrCreate(
            ['slug' => 'fbssc-open-day-2026-12-05'],
            [
                'title' => 'Operation: Adrenaline Open Day',
                'host_organisation_id' => $club->id,
                'venue_id' => $range->id,
                'starts_at' => '2026-12-05 09:00:00',
                'ends_at' => '2026-12-05 17:00:00',
                'all_day' => false,
                'level' => EventLevel::Club,
                'kind' => EventKind::Training,
                'status' => EventStatus::Confirmed,
                'publication' => EventPublication::Published,
                'confirmed_at' => now(),
                'website_url' => 'https://fbssc.co/calendar/',
                'banner_path' => $this->storeOpenDayBanner(),
                'description' => "Operation: Adrenaline open day at False Bay Sports Shooting Club, Tercentenary Way, Glencairn, on Saturday 5 December 2026. Open 09:00–17:00.\n\nThe poster lists The Wild West, defensive shooting, 9mm Parabellum handgun, .22 rifle, semi-auto rifles and carbines, sniper rifles, clay pigeon shooting, axe throwing, zombie survival, an exploding bloodpack T-shirt, and under-12 air rifles. Food stalls, family activities and photo opportunities. The poster says to find the club on Facebook at @FalseBaySC.",
                'import_notes' => 'Club posters for Operation: Adrenaline, Saturday 5 December 2026, 09:00–17:00. The public calendar also lists this day as Open Day. No entry fee is printed on the posters.',
                'source' => ListingSource::Staff,
                'last_verified_at' => now(),
            ],
        );
    }

    private function storeOpenDayBanner(): ?string
    {
        $filename = 'fbssc-open-day-2026-12-05.png';
        $source = database_path('seeders/data/'.$filename);

        if (! is_file($source)) {
            return Event::query()->where('slug', 'fbssc-open-day-2026-12-05')->value('banner_path');
        }

        $path = 'event-banners/'.$filename;
        Storage::disk('media')->put($path, (string) file_get_contents($source));

        return $path;
    }

    /**
     * @return list<array{slug: string, title: string, date: string, discipline: string|null, level: EventLevel, kind: EventKind}>
     */
    private function sessions(): array
    {
        $from = CarbonImmutable::parse('2026-11-20');
        $until = CarbonImmutable::parse('2027-03-31');

        return array_merge(
            $this->dated('ipsc-boland-handgun-level-2', 'IPSC Boland Handgun Level 2', ['2026-11-28'], 'ipsc-practical', EventLevel::Provincial),
            $this->dated('steel-challenge', 'Steel Challenge', ['2026-12-13'], 'steel-challenge'),
            $this->dated('ipsc-end-of-year-fun-shoot', 'IPSC End of Year Fun Shoot', ['2026-12-16'], 'ipsc-practical'),
            $this->dated('mpds', 'MPDS', $this->monthly($from, $until, 2, CarbonImmutable::SATURDAY), 'multigun'),
            $this->dated('wtr-cowboys', 'WTR Cowboys', $this->monthly($from, $until, 3, CarbonImmutable::SATURDAY), null),
            $this->dated('sadpa', 'SADPA', $this->monthly($from, $until, 4, CarbonImmutable::SATURDAY, ['2026-09-26', '2026-11-28']), 'action-defensive'),
            $this->dated('sadpa-club-shoot-wednesday', 'SADPA Club Shoot', $this->monthly($from, $until, 1, CarbonImmutable::WEDNESDAY), 'action-defensive'),
            $this->dated('mpds-fourth-saturday', 'MPDS', $this->monthly($from, $until, 4, CarbonImmutable::SATURDAY, ['2026-09-26', '2026-11-28']), 'multigun'),
            $this->dated('ipsc-club-shoot', 'IPSC Club Shoot', $this->monthly($from, $until, 1, CarbonImmutable::SATURDAY, ['2026-02-07', '2026-12-05']), 'ipsc-practical'),
            $this->dated('sadpa-club-shoot', 'SADPA Club Shoot', $this->monthly($from, $until, 2, CarbonImmutable::SATURDAY, ['2026-04-11']), 'action-defensive'),
            $this->dated('clay-busters', 'Clay Busters', array_merge(
                $this->weekly($from, $until, CarbonImmutable::WEDNESDAY, ['2026-09-23']),
                $this->weekly($from, $until, CarbonImmutable::SATURDAY, ['2026-09-26']),
                $this->weekly($from, $until, CarbonImmutable::SUNDAY, ['2026-05-31', '2026-09-27']),
            ), 'trap'),
        );
    }

    /**
     * @param  list<string>  $dates
     * @return list<array{slug: string, title: string, date: string, discipline: string|null, level: EventLevel, kind: EventKind}>
     */
    private function dated(
        string $key,
        string $title,
        array $dates,
        ?string $discipline,
        EventLevel $level = EventLevel::Club,
        EventKind $kind = EventKind::Competition,
    ): array {
        return array_map(fn (string $date): array => [
            'slug' => 'fbssc-'.$key.'-'.$date,
            'title' => $title,
            'date' => $date,
            'discipline' => $discipline,
            'level' => $level,
            'kind' => $kind,
        ], $dates);
    }

    /**
     * @param  list<string>  $except
     * @return list<string>
     */
    private function monthly(CarbonImmutable $from, CarbonImmutable $until, int $nth, int $dayOfWeek, array $except = []): array
    {
        $dates = [];
        $cursor = $from->startOfMonth();

        while ($cursor <= $until) {
            $date = $cursor->nthOfMonth($nth, $dayOfWeek);

            if ($date !== false && $date >= $from->startOfDay() && $date <= $until->endOfDay() && ! in_array($date->toDateString(), $except, true)) {
                $dates[] = $date->toDateString();
            }

            $cursor = $cursor->addMonth();
        }

        return $dates;
    }

    /**
     * @param  list<string>  $except
     * @return list<string>
     */
    private function weekly(CarbonImmutable $from, CarbonImmutable $until, int $dayOfWeek, array $except = []): array
    {
        $date = $from->dayOfWeek === $dayOfWeek ? $from : $from->next($dayOfWeek);
        $dates = [];

        while ($date <= $until) {
            if (! in_array($date->toDateString(), $except, true)) {
                $dates[] = $date->toDateString();
            }

            $date = $date->addWeek();
        }

        return $dates;
    }
}
