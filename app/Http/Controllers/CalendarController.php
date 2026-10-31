<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
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
        // Same filters as the list below, including kind=training, so the
        // description does not name a match the page has hidden.
        $matchQuery = PublicEventQuery::fromRequest($request)->builder();
        $matchCount = (clone $matchQuery)->count();
        $nextMatch = (clone $matchQuery)->reorder()->orderBy('starts_at')->first();

        [$seoTitle, $seoDescription] = $this->composeMeta($discipline, $province, $matchCount, $nextMatch);

        // BreadcrumbList: Home › Calendar (› Discipline)? (› Province)?
        // Deepest filter wins position 3 to keep the strip readable in
        // a SERP where breadcrumbs render as a single line.
        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Shooting events', 'url' => route('calendar')],
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

        // Build the JSON-LD ItemList with the same filters the user is
        // actually looking at. Hand-rolling just discipline/province
        // here used to leak competition rows into ?kind=training and
        // midweek matches into ?weekend=1 schema payloads.
        $listed = PublicEventQuery::fromRequest($request)->builder()->limit(24)->get();

        $jsonLd = [JsonLd::breadcrumbs($crumbs)];

        if ($listed->isNotEmpty()) {
            array_unshift($jsonLd, JsonLd::itemList(
                $listed->map(fn (Event $event): array => [
                    'name' => $event->title,
                    'url' => $event->publicUrl(),
                    'id' => $event->schemaId(),
                ])->all(),
                $seoTitle,
            ));
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
            'weekend' => $request->boolean('weekend'),
            'featuredDisciplines' => Discipline::featuredHubs(),
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'canonical' => $this->canonical($discipline, $province),
            'jsonLd' => $jsonLd,
        ]);
    }

    /**
     * Filter-aware SEO copy. Rules:
     *   - No filters: the head query, "Shooting events in South Africa".
     *   - Discipline only: "{Discipline} matches in South Africa. {N} upcoming."
     *   - Province only: "Shooting matches in {Province}. {N} upcoming."
     *   - Both: "{Discipline} matches in {Province}. {N} upcoming."
     *
     * Match count is embedded in the description (not the title) —
     * counts change often and Google penalises volatile title tags.
     *
     * @return array{0: string, 1: string}
     */
    private function composeMeta(?Discipline $discipline, ?Province $province, int $matchCount, ?Event $next): array
    {
        $when = $next?->starts_at?->timezone((string) config('app.timezone'))->format('j M Y');
        $facts = $matchCount === 0
            ? 'No upcoming match is listed yet.'
            : ($matchCount === 1 ? 'There is 1 upcoming match.' : 'There are '.$matchCount.' upcoming matches.')
                .($next !== null && $when !== null ? ' The next is '.$next->title.' on '.$when.'.' : '');

        if ($discipline === null && $province === null) {
            return [
                'Shooting events in South Africa',
                'Shooting events in South Africa. '.$facts,
            ];
        }

        if ($discipline !== null && $province !== null) {
            return [
                $discipline->name.' in '.$province->getLabel(),
                $discipline->name.' matches in '.$province->getLabel().'. '.$facts,
            ];
        }

        if ($discipline !== null) {
            return [
                $discipline->name.' matches',
                $discipline->name.' matches across South Africa. '.$facts,
            ];
        }

        return [
            'Shooting matches in '.$province->getLabel(),
            'Shooting matches in '.$province->getLabel().'. '.$facts,
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
