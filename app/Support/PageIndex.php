<?php

namespace App\Support;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Http\Request;

/**
 * One decision per public page: is it indexable, what is the canonical,
 * what HTTP status does it return, what robots directive wins.
 *
 * Every public controller, the sitemap and the IndexNow observer read
 * from the same factory so they can't disagree. When they disagreed we
 * got soft 404s (indexable page with no real content) and canonical
 * conflicts (we vouched for `/calendar?x=y`, Google preferred `/x/y`).
 */
final readonly class PageIndex
{
    public function __construct(
        public bool $indexable,
        public string $canonical,
        public int $status = 200,
        public ?string $robots = null,
    ) {}

    public static function indexable(string $canonical): self
    {
        return new self(indexable: true, canonical: $canonical);
    }

    public static function thin(string $canonical): self
    {
        return new self(
            indexable: false,
            canonical: $canonical,
            robots: 'noindex,follow',
        );
    }

    public static function gone(string $canonical): self
    {
        return new self(
            indexable: false,
            canonical: $canonical,
            status: 410,
            robots: 'noindex',
        );
    }

    /**
     * Decision for an entity page (match, club, range, supplier).
     *
     * Delegates thin-content gating to Indexability so the sitemap's
     * `->indexable()` scopes and the page's robots tag share one rule.
     */
    public static function forModel(Event|Organisation|Venue|Provider $record): self
    {
        $canonical = self::canonicalFor($record);

        return Indexability::allows($record)
            ? self::indexable($canonical)
            : self::thin($canonical);
    }

    /**
     * Decision for the discipline hub (all provinces rolled up).
     *
     * A hub without upcoming matches is still the indexable parent for
     * the discipline — thin-content sits on the province slices, not
     * the hub itself.
     */
    public static function forDiscipline(Discipline $discipline): self
    {
        return self::indexable(route('disciplines.show', $discipline->slug));
    }

    /**
     * Decision for a discipline × province page.
     *
     * Noindex when fewer than `seo.min_upcoming` upcoming matches. A
     * page with clubs and zero matches is thin to Google — the clubs
     * block stays visible for humans but the page leaves the sitemap.
     */
    public static function forDisciplineProvince(Discipline $discipline, Province $province, int $upcoming): self
    {
        $canonical = route('disciplines.province', [$discipline->slug, $province->urlSlug()]);
        $minimum = (int) config('seo.min_upcoming', 1);

        return $upcoming >= $minimum
            ? self::indexable($canonical)
            : self::thin($canonical);
    }

    /**
     * Decision for `/calendar`.
     *
     * Any filter or marketing query string makes this a filter view,
     * not a canonical URL. Canonical is always the bare `/calendar`.
     * `/disciplines/{slug}` and `/disciplines/{slug}/{province}` are
     * the canonical surfaces for a discipline or province cut.
     */
    public static function forCalendar(Request $request): self
    {
        $canonical = route('calendar');

        return $request->query() === []
            ? self::indexable($canonical)
            : self::thin($canonical);
    }

    /**
     * Decision for `/calendar/month` — same rule as `/calendar`.
     */
    public static function forCalendarMonth(Request $request): self
    {
        $canonical = route('calendar.month');

        return $request->query() === []
            ? self::indexable($canonical)
            : self::thin($canonical);
    }

    /**
     * Decision for a weekend answer page.
     *
     * Time windows stay 200 for humans but leave the sitemap. Their
     * canonical points at `/calendar` because stripping the filter
     * leaves no province path that is indexable in its own right.
     */
    public static function forWeekend(): self
    {
        return self::thin(route('calendar'));
    }

    /**
     * Decision for a directory index with filters (clubs, ranges,
     * suppliers).
     *
     * A filtered index is a UI view, not a canonical URL. Canonical
     * resolves to the bare index route.
     */
    public static function forDirectoryIndex(string $routeName, Request $request): self
    {
        $canonical = route($routeName);

        return $request->query() === []
            ? self::indexable($canonical)
            : self::thin($canonical);
    }

    /**
     * Canonical URL for a model-backed page. Centralised so the
     * sitemap, the meta tag and IndexNow use the exact same string.
     */
    public static function canonicalFor(Event|Organisation|Venue|Provider $record): string
    {
        return match (true) {
            $record instanceof Event => $record->publicUrl(),
            $record instanceof Organisation => $record->isFederationListing()
                ? route('federations.show', $record->slug)
                : route('clubs.show', $record->slug),
            $record instanceof Venue => route('ranges.show', $record->slug),
            $record instanceof Provider => route('suppliers.show', $record->slug),
        };
    }
}
