<?php

namespace App\Http\Controllers;

use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\MagazineIssue;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use App\Support\PublicCache;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemaps = array_merge(
            $this->locs('pages', $this->pageUrls()->count()),
            $this->locs('events', $this->eventUrls()->count()),
            $this->locs('organisations', $this->organisationUrls()->count()),
            $this->locs('venues', $this->venueUrls()->count()),
            $this->locs('disciplines', $this->disciplineUrls()->count()),
            $this->locs('providers', $this->providerUrls()->count()),
            $this->locs('magazines', $this->magazineUrls()->count()),
        );

        return $this->cached('index', 'public.sitemaps.index', ['sitemaps' => $sitemaps]);
    }

    public function pages(): Response
    {
        return $this->urlset('pages', $this->pageUrls());
    }

    public function events(): Response
    {
        return $this->urlset('events', $this->eventUrls());
    }

    public function organisations(): Response
    {
        return $this->urlset('organisations', $this->organisationUrls());
    }

    public function venues(): Response
    {
        return $this->urlset('venues', $this->venueUrls());
    }

    public function disciplines(): Response
    {
        return $this->urlset('disciplines', $this->disciplineUrls());
    }

    public function providers(): Response
    {
        return $this->urlset('providers', $this->providerUrls());
    }

    public function magazines(): Response
    {
        return $this->urlset('magazines', $this->magazineUrls());
    }

    public function chunk(string $type, int $page): Response
    {
        $urls = match ($type) {
            'pages' => $this->pageUrls(),
            'events' => $this->eventUrls(),
            'organisations' => $this->organisationUrls(),
            'venues' => $this->venueUrls(),
            'disciplines' => $this->disciplineUrls(),
            'providers' => $this->providerUrls(),
            'magazines' => $this->magazineUrls(),
            default => abort(404),
        };

        return $this->urlset($type, $urls, $page);
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function pageUrls(): Collection
    {
        if (! config('seo.indexable')) {
            return collect();
        }

        $pages = [
            'home',
            'calendar',
            'calendar.month',
            'map',
            'disciplines.index',
            'clubs.index',
            'ranges.index',
            'suppliers.index',
            'advertise',
            'contact',
            'privacy',
            'terms',
        ];

        if (MagazineIssue::query()->published()->exists()) {
            $pages[] = 'magazines.index';
        }

        return collect($pages)->map(fn (string $name): array => [
            'loc' => route($name),
        ]);
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function eventUrls(): Collection
    {
        if (! config('seo.indexable')) {
            return collect();
        }

        return Event::query()
            ->indexable()
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $event) => $this->safeUrl('events sitemap', $event, fn (): array => [
                'loc' => route('events.show', $event->slug),
                'lastmod' => $event->updated_at?->toAtomString(),
            ]))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function organisationUrls(): Collection
    {
        if (! config('seo.indexable')) {
            return collect();
        }

        return Organisation::query()
            ->indexable()
            ->orderBy('name')
            ->get()
            ->map(fn (Organisation $org) => $this->safeUrl('organisations sitemap', $org, fn (): array => [
                'loc' => $org->isFederationListing()
                    ? route('federations.show', $org->slug)
                    : route('clubs.show', $org->slug),
                'lastmod' => $org->updated_at?->toAtomString(),
            ]))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function venueUrls(): Collection
    {
        if (! config('seo.indexable')) {
            return collect();
        }

        return Venue::query()
            ->indexable()
            ->orderBy('name')
            ->get()
            ->map(fn (Venue $venue) => $this->safeUrl('venues sitemap', $venue, fn (): array => [
                'loc' => route('ranges.show', $venue->slug),
                'lastmod' => $venue->updated_at?->toAtomString(),
            ]))
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function disciplineUrls(): Collection
    {
        if (! config('seo.indexable')) {
            return collect();
        }

        $disciplines = Discipline::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->get();

        $urls = new Collection;
        $minimum = (int) config('seo.landing_min');

        foreach ($disciplines as $discipline) {
            $root = $this->safeUrl('disciplines sitemap', $discipline, fn (): array => [
                'loc' => route('disciplines.show', $discipline->slug),
                'lastmod' => $discipline->updated_at?->toAtomString(),
            ]);

            if ($root !== null) {
                $urls->push($root);
            }

            $about = $this->safeUrl('disciplines about sitemap', $discipline, fn (): array => [
                'loc' => route('disciplines.about', $discipline->slug),
                'lastmod' => $discipline->updated_at?->toAtomString(),
            ]);

            if ($about !== null) {
                $urls->push($about);
            }

            foreach (Province::cases() as $province) {
                if ($this->disciplineProvinceListingCount($discipline, $province) < $minimum) {
                    continue;
                }

                $slice = $this->safeUrl('disciplines province sitemap', $discipline, fn (): array => [
                    'loc' => route('clubs.landing', [$province->urlSlug(), $discipline->slug]),
                    'lastmod' => $discipline->updated_at?->toAtomString(),
                ]);

                if ($slice !== null) {
                    $urls->push($slice);
                }
            }
        }

        return $urls;
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function providerUrls(): Collection
    {
        if (! config('seo.indexable')) {
            return collect();
        }

        $urls = new Collection;
        $minimum = (int) config('seo.landing_min');

        foreach (ProviderCategory::publicCases() as $category) {
            $national = $this->providerListingStats($category);

            if ($national['count'] < 1) {
                continue;
            }

            $urls->push(array_filter([
                'loc' => route('suppliers.category', $category->urlSlug()),
                'lastmod' => $national['lastmod'],
            ]));

            foreach (Province::cases() as $province) {
                $slice = $this->providerListingStats($category, $province);

                if ($slice['count'] < $minimum) {
                    continue;
                }

                $urls->push(array_filter([
                    'loc' => route('suppliers.province', [$category->urlSlug(), $province->urlSlug()]),
                    'lastmod' => $slice['lastmod'],
                ]));
            }
        }

        Provider::query()
            ->indexable()
            ->orderBy('name')
            ->get()
            ->each(function (Provider $provider) use ($urls): void {
                $url = $this->safeUrl('providers sitemap', $provider, fn (): array => [
                    'loc' => route('suppliers.show', $provider->slug),
                    'lastmod' => $provider->updated_at?->toAtomString(),
                ]);

                if ($url !== null) {
                    $urls->push($url);
                }
            });

        return $urls;
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null}>
     */
    private function magazineUrls(): Collection
    {
        if (! config('seo.indexable')) {
            return collect();
        }

        return MagazineIssue::query()
            ->published()
            ->where('reader_access', 'public')
            ->orderByDesc('published_at')
            ->get()
            ->map(fn (MagazineIssue $issue) => $this->safeUrl('magazines sitemap', $issue, fn (): array => [
                'loc' => route('magazines.show', $issue->slug),
                'lastmod' => $issue->updated_at?->toAtomString(),
            ]))
            ->filter()
            ->values();
    }

    /**
     * @return list<string>
     */
    private function locs(string $name, int $count): array
    {
        $chunk = max(1, (int) config('seo.sitemap_chunk'));

        if ($count <= $chunk) {
            return [url('/sitemaps/'.$name.'.xml')];
        }

        $pages = (int) ceil($count / $chunk);

        return array_map(
            fn (int $page): string => url('/sitemaps/'.$name.'-'.$page.'.xml'),
            range(1, $pages),
        );
    }

    /**
     * @param  Collection<int, array{loc: string, lastmod?: string|null}>  $urls
     */
    private function urlset(string $name, Collection $urls, ?int $page = null): Response
    {
        $chunk = max(1, (int) config('seo.sitemap_chunk'));
        $count = $urls->count();
        $pages = (int) ceil($count / $chunk);

        if ($page !== null) {
            abort_if($count <= $chunk || $page < 1 || $page > $pages, 404);
        }

        $slice = $count > $chunk
            ? $urls->forPage($page ?? 1, $chunk)->values()
            : $urls;

        $key = $count > $chunk ? $name.'-'.($page ?? 1) : $name;

        return $this->cached($key, 'public.sitemaps.urlset', ['urls' => $slice]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function cached(string $key, string $view, array $data): Response
    {
        $body = PublicCache::sitemap($key, fn (): string => view($view, $data)->render());

        return response($body, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    /**
     * @return array{count: int, lastmod: string|null}
     */
    private function providerListingStats(ProviderCategory $category, ?Province $province = null): array
    {
        $query = Provider::query()
            ->published()
            ->listed()
            ->offering($category)
            ->when($province, fn ($builder) => $builder->where('province', $province));

        $query->getQuery()->columns = null;

        $stats = $query
            ->selectRaw('count(*) as listing_count, max(updated_at) as latest_change')
            ->toBase()
            ->first();

        return [
            'count' => (int) ($stats->listing_count ?? 0),
            'lastmod' => $this->atom($stats->latest_change ?? null),
        ];
    }

    private function atom(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->timezone((string) config('app.timezone'))->toAtomString();
    }

    private function disciplineProvinceListingCount(Discipline $discipline, Province $province): int
    {
        $treeIds = $discipline->treeIds();

        $events = (new PublicEventQuery(
            disciplineSlug: $discipline->slug,
            provinceSlug: $province->urlSlug(),
        ))->builder()->count();

        $clubs = Organisation::query()
            ->published()
            ->clubs()
            ->whereHas('disciplines', fn ($q) => $q->whereIn('disciplines.id', $treeIds))
            ->where('province', $province)
            ->count();

        $ranges = Venue::query()
            ->published()
            ->whereHas('disciplines', fn ($q) => $q->whereIn('disciplines.id', $treeIds))
            ->where('province', $province)
            ->count();

        return $events + $clubs + $ranges;
    }

    /**
     * @param  callable(): array<string, mixed>  $build
     * @return array<string, mixed>|null
     */
    private function safeUrl(string $context, object $subject, callable $build): ?array
    {
        try {
            return $build();
        } catch (Throwable $e) {
            Log::warning('Sitemap row skipped', [
                'context' => $context,
                'model' => $subject::class,
                'id' => property_exists($subject, 'id') ? $subject->id : null,
                'slug' => property_exists($subject, 'slug') ? $subject->slug : null,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
