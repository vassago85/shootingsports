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
            '# Shooting Sports — The SA Register of Shooting Sport',
            '',
            'The independent, neutral national register of South African shooting sport.',
            'Clubs, ranges, suppliers and every match on the calendar — filtered by discipline,',
            'by province, and by how far you are willing to drive.',
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
