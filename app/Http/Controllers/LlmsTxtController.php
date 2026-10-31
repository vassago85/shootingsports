<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\MagazineIssue;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

/**
 * `/llms.txt` — a curated, plain-text map of the register for LLM crawlers.
 *
 * `/llms-full.txt` is the next 60 days of events, regenerated daily.
 * Member emails stay off both files. /desk and /admin are not sources.
 */
class LlmsTxtController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            '# Shooting Sports — Find your sport. Find your club. Find your match.',
            '',
            'Shooting Sports is the national register of shooting events, clubs, and ranges in South Africa.',
            'Every discipline, every province, every match on one South African calendar.',
            'Free to list, free to browse, no account required to look around.',
            '',
            '## Public pages',
            '',
            '- '.route('home').' — Home',
            '- '.route('calendar').' — Match calendar',
            '- '.route('answers.this-weekend').' — Matches this Saturday and Sunday, grouped by province',
            '- '.route('disciplines.index').' — Disciplines',
            '- '.route('clubs.index').' — Clubs',
            '- '.route('ranges.index').' — Ranges',
            '- '.route('suppliers.index').' — Suppliers',
        ];

        if (MagazineIssue::query()->published()->exists()) {
            $lines[] = '- '.route('magazines.index').' — Published magazines';
        }

        foreach (Province::cases() as $province) {
            $lines[] = '- '.route('answers.province-this-weekend', $province->urlSlug()).' — '.$province->getLabel().' this weekend';
            $lines[] = '- '.route('answers.province-next-weekend', $province->urlSlug()).' — '.$province->getLabel().' next weekend';
        }

        $lines[] = '';
        $lines[] = '## Disciplines';
        $lines[] = '';

        foreach ($this->disciplines() as $discipline) {
            $lines[] = '- '.route('disciplines.show', $discipline->slug).' — '.$discipline->name.' matches in South Africa';
        }

        $lines[] = 'Province pages live at /disciplines/{slug}/{province}.';

        $lines = array_merge($lines, [
            '',
            '## Machine-readable',
            '',
            '- '.route('events.json').' — Upcoming events as JSON. Same fields as the Event structured data on each event page, plus last_updated. Cached.',
            '- '.route('llms.full').' — The next 60 days of events as plain text, regenerated daily.',
            '- '.url('/disciplines/{slug}/calendar.ics').' — iCal feed for one discipline',
            '- '.url('/clubs/{slug}/calendar.ics').' — iCal feed for one club',
            '',
            '- '.route('claim').' — For clubs',
            '- '.route('embed.docs').' — Embed the calendar',
            '- '.route('advertise').' — Advertise',
            '- '.route('contact').' — Contact',
            '- '.route('privacy').' — Privacy and POPIA',
            '- '.route('terms').' — Terms of Use',
            '',
            '## Sitemaps',
            '',
            '- '.url('/sitemap.xml').' — Sitemap index',
            '',
            '## Not sources',
            '',
            '- /desk and /admin are the private operator surface and are not source material.',
            '- Member names, emails and personal contact information are never shown beyond',
            '  what is already on the linked HTML pages.',
            '',
        ]);

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Centrefire PRS and PR22 first, then every other published hub.
     *
     * @return Collection<int, Discipline>
     */
    private function disciplines(): Collection
    {
        $first = ['precision-rifle', 'pr22-rimfire'];

        return Discipline::query()
            ->where('is_published', true)
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->sortBy(function (Discipline $discipline) use ($first): array {
                $position = array_search($discipline->slug, $first, true);

                return [$position === false ? 1 : 0, $position === false ? $discipline->name : $position];
            })
            ->values();
    }
}
