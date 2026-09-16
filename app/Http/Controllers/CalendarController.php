<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Discipline;
use App\Queries\PublicEventQuery;
use App\Support\JsonLd;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function __invoke(Request $request): View
    {
        $disciplineSlug = $request->string('discipline')->toString() ?: null;
        $provinceSlug = $request->string('province')->toString() ?: null;
        $from = $request->string('from')->toString() ?: null;
        $to = $request->string('to')->toString() ?: null;

        $discipline = $disciplineSlug
            ? Discipline::query()->where('slug', $disciplineSlug)->where('is_published', true)->first()
            : null;

        $province = $provinceSlug ? Province::fromUrlSlug($provinceSlug) : null;

        // Cheap count of matches under the current filter — powers the
        // SEO description ("12 IPSC matches in Gauteng"). Same query
        // shape as the Livewire filter so the DB cache stays warm.
        // Radius filter is post-query (applyRadius) so builder->count
        // slightly over-reports when radius is set; not worth solving —
        // radius is a UI-only filter and is stripped from the canonical.
        $matchCount = (new PublicEventQuery(
            disciplineSlug: $discipline?->slug,
            provinceSlug: $province?->urlSlug(),
        ))->builder()->count();

        [$seoTitle, $seoDescription] = $this->composeMeta($discipline, $province, $matchCount);

        // BreadcrumbList: Home › Calendar (› Discipline)? (› Province)?
        // Deepest filter wins position 3 to keep the strip readable in
        // a SERP where breadcrumbs render as a single line.
        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Calendar', 'url' => route('calendar')],
        ];

        if ($discipline instanceof Discipline) {
            $crumbs[] = [
                'name' => $discipline->name,
                'url' => route('disciplines.show', $discipline->slug),
            ];
        }

        if ($province instanceof Province) {
            $crumbs[] = [
                'name' => $province->getLabel(),
                'url' => route('calendar', ['province' => $province->urlSlug()]),
            ];
        }

        return view('public.calendar', [
            'discipline' => $disciplineSlug,
            'province' => $provinceSlug,
            'radius' => $request->string('radius')->toString() ?: null,
            'near' => $request->string('near')->toString() ?: null,
            'lat' => $request->string('lat')->toString() ?: null,
            'lng' => $request->string('lng')->toString() ?: null,
            'from' => $from,
            'to' => $to,
            'family' => $request->string('family')->toString() ?: 'all',
            'novice' => $request->boolean('novice'),
            'confirmed' => $request->boolean('confirmed'),
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'canonical' => $this->canonical($discipline, $province),
            'jsonLd' => [JsonLd::breadcrumbs($crumbs)],
        ]);
    }

    /**
     * Filter-aware SEO copy. Rules:
     *   - No filters: static site-wide message.
     *   - Discipline only: "{Discipline} matches in South Africa — {N} upcoming."
     *   - Province only: "Shooting matches in {Province} — {N} upcoming."
     *   - Both: "{Discipline} matches in {Province} — {N} upcoming."
     *
     * Match count is embedded in the description (not the title) —
     * counts change often and Google penalises volatile title tags.
     *
     * @return array{0: string, 1: string}
     */
    private function composeMeta(?Discipline $discipline, ?Province $province, int $matchCount): array
    {
        if ($discipline === null && $province === null) {
            return [
                'Match calendar',
                'Every listed match in South African shooting sport, filterable by discipline, province, and how far you will drive.',
            ];
        }

        $countPhrase = match ($matchCount) {
            0 => 'no upcoming matches right now',
            1 => '1 upcoming match',
            default => "{$matchCount} upcoming matches",
        };

        if ($discipline !== null && $province !== null) {
            return [
                $discipline->name.' in '.$province->getLabel(),
                $discipline->name.' matches in '.$province->getLabel().' — '.$countPhrase.' on the South African calendar.',
            ];
        }

        if ($discipline !== null) {
            return [
                $discipline->name.' matches',
                $discipline->name.' matches across South Africa — '.$countPhrase.' from every listed club.',
            ];
        }

        return [
            'Shooting matches in '.$province->getLabel(),
            'Every shooting match in '.$province->getLabel().' — '.$countPhrase.' across all disciplines.',
        ];
    }

    /**
     * Canonical URL that folds equivalent filter permutations into one.
     * `?radius=100` and other non-indexable UI-state params are stripped
     * so Search Console sees one canonical page per discipline/province
     * combination, not a fresh URL for every widget position.
     */
    private function canonical(?Discipline $discipline, ?Province $province): string
    {
        $params = [];

        if ($discipline instanceof Discipline) {
            $params['discipline'] = $discipline->slug;
        }

        if ($province instanceof Province) {
            $params['province'] = $province->urlSlug();
        }

        return route('calendar', $params);
    }
}
