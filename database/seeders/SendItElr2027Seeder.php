<?php

namespace Database\Seeders;

use App\Enums\EventLevel;
use App\Enums\EventPublication;
use App\Enums\EventStatus;
use App\Enums\ListingSource;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Venue;
use App\Support\Venues\CorrectSendItElrRange;
use Illuminate\Database\Seeder;

/**
 * Send It ELR 2027 dates, typed from the one-page sheet
 * "Send IT, ELR Shooting - event dates 2027".
 *
 * The sheet still prints "Matroosbergstasie Skietklub" for the 2-mile
 * rows. That club no longer operates. Those matches stay on the
 * Send It ELR 2-Mile Range, close to Matroosbergstasie.
 */
class SendItElr2027Seeder extends Seeder
{
    public function run(): void
    {
        $this->call(SendItElr2026Seeder::class);

        $elr = Discipline::query()->where('slug', 'elr')->firstOrFail();
        $series = Organisation::query()->where('slug', 'send-it-elr')->firstOrFail();

        $venues = [
            'darling-steel-valley' => Venue::query()->where('slug', 'darling-steel-valley')->firstOrFail(),
            'peks-range-wellington' => Venue::query()->where('slug', 'peks-range-wellington')->firstOrFail(),
            CorrectSendItElrRange::SLUG => Venue::query()->where('slug', CorrectSendItElrRange::SLUG)->firstOrFail(),
            'hanover-northern-cape' => Venue::query()->where('slug', 'hanover-northern-cape')->firstOrFail(),
        ];

        foreach ($this->matches() as $match) {
            $event = Event::query()->updateOrCreate(
                ['slug' => $match['slug']],
                [
                    'title' => $match['title'],
                    'host_organisation_id' => $series->id,
                    'venue_id' => $venues[$match['venue']]->id,
                    'starts_at' => $match['starts_at'],
                    'ends_at' => $match['ends_at'],
                    'all_day' => true,
                    'level' => EventLevel::Series,
                    'status' => EventStatus::Confirmed,
                    'publication' => EventPublication::Published,
                    'confirmed_at' => now(),
                    'description' => $match['description'],
                    'source' => ListingSource::Staff,
                    'last_verified_at' => now(),
                ],
            );

            $event->syncDisciplines([$elr->id], $elr->id);
        }
    }

    /**
     * @return list<array{slug: string, title: string, venue: string, starts_at: string, ends_at: ?string, description: string}>
     */
    private function matches(): array
    {
        $oneMile = 'Hosted by APW and 3% Tactical at Darling Steel Valley.';
        $twoMile = '2-mile challenge close to Matroosbergstasie, off the R318 towards Montagu.';

        return [
            [
                'slug' => 'send-it-1-mile-challenge-2027-02-06',
                'title' => '1-Mile Challenge',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2027-02-06 08:00:00',
                'ends_at' => null,
                'description' => $oneMile,
            ],
            [
                'slug' => 'send-it-peks-22lr-elr-2027-03-06',
                'title' => 'PEKS Range .22 LR ELR',
                'venue' => 'peks-range-wellington',
                'starts_at' => '2027-03-06 08:00:00',
                'ends_at' => null,
                'description' => '.22 LR ELR at PEKS Range, Wellington.',
            ],
            [
                'slug' => 'send-it-2-mile-challenge-2027-03-13',
                'title' => '2-Mile Challenge',
                'venue' => CorrectSendItElrRange::SLUG,
                'starts_at' => '2027-03-13 08:00:00',
                'ends_at' => null,
                'description' => $twoMile,
            ],
            [
                'slug' => 'send-it-1-mile-challenge-2027-04-03',
                'title' => '1-Mile Challenge',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2027-04-03 08:00:00',
                'ends_at' => null,
                'description' => $oneMile,
            ],
            [
                'slug' => 'send-it-2-mile-challenge-2027-04-10',
                'title' => '2-Mile Challenge',
                'venue' => CorrectSendItElrRange::SLUG,
                'starts_at' => '2027-04-10 08:00:00',
                'ends_at' => null,
                'description' => $twoMile,
            ],
            [
                'slug' => 'send-it-ultra-2027',
                'title' => 'Send It Ultra',
                'venue' => 'hanover-northern-cape',
                'starts_at' => '2027-04-29 08:00:00',
                'ends_at' => '2027-05-01 17:00:00',
                'description' => 'Send It Ultra at Hanover, Northern Cape.',
            ],
            [
                'slug' => 'send-it-peks-22lr-elr-2027-09-04',
                'title' => 'PEKS Range .22 LR ELR',
                'venue' => 'peks-range-wellington',
                'starts_at' => '2027-09-04 08:00:00',
                'ends_at' => null,
                'description' => '.22 LR ELR at PEKS Range, Wellington.',
            ],
            [
                'slug' => 'send-it-1-mile-challenge-2027-10-30',
                'title' => '1-Mile Challenge',
                'venue' => 'darling-steel-valley',
                'starts_at' => '2027-10-30 08:00:00',
                'ends_at' => null,
                'description' => $oneMile,
            ],
            [
                'slug' => 'send-it-2-mile-challenge-2027-11-13',
                'title' => '2-Mile Challenge',
                'venue' => CorrectSendItElrRange::SLUG,
                'starts_at' => '2027-11-13 08:00:00',
                'ends_at' => null,
                'description' => $twoMile,
            ],
        ];
    }
}
