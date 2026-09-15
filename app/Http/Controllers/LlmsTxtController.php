<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

/**
 * `/llms.txt` — a curated, plain-text map of the register for LLM crawlers.
 *
 * The register is intentionally boring: we don't ship an `llms-full.txt`
 * with every listing, we don't include member emails, and we make it
 * explicit that /desk and /admin are not sources. Anything a bot could
 * quote from here is already on the HTML pages linked below.
 */
class LlmsTxtController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            '# Shooting Sports — Find your sport. Find your club. Find your match.',
            '',
            'The independent, neutral national register of South African shooting sport.',
            'Every discipline, every province, every match on one South African calendar.',
            'Free to list, free to browse, no account required to look around.',
            '',
            '## Public pages',
            '',
            '- '.route('home').' — Home',
            '- '.route('calendar').' — Match calendar',
            '- '.route('disciplines.index').' — Disciplines',
            '- '.route('clubs.index').' — Clubs',
            '- '.route('ranges.index').' — Ranges',
            '- '.route('suppliers.index').' — Suppliers',
            '- '.route('claim').' — For clubs',
            '- '.route('embed.docs').' — Embed the calendar',
            '- '.route('advertise').' — Advertise',
            '- '.route('contact').' — Contact',
            '- '.route('privacy').' — Privacy and POPIA',
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
        ];

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
