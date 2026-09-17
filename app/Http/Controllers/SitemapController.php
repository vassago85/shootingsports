<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemaps = [
            url('/sitemaps/pages.xml'),
            url('/sitemaps/events.xml'),
            url('/sitemaps/organisations.xml'),
            url('/sitemaps/venues.xml'),
            url('/sitemaps/disciplines.xml'),
            url('/sitemaps/providers.xml'),
        ];

        return $this->xml('public.sitemaps.index', ['sitemaps' => $sitemaps]);
    }

    /**
     * Static, evergreen public URLs that Google would otherwise never find
     * from the entity-driven sitemaps. Intentionally excludes desk, admin,
     * my-calendar, thank-you screens, iCal feeds and oembed.
     */
    public function pages(): Response
    {
        $now = now()->toAtomString();

        $urls = collect([
            'home',
            'calendar',
            'calendar.month',
            'map',
            'disciplines.index',
            'clubs.index',
            'ranges.index',
            'suppliers.index',
            'claim',
            'advertise',
            'contact',
            'embed.docs',
            'privacy',
        ])->map(fn (string $name): array => [
            'loc' => route($name),
            'lastmod' => $now,
        ]);

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function events(): Response
    {
        $urls = Event::query()
            ->published()
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $event) => $this->safeUrl('events sitemap', $event, fn (): array => [
                'loc' => route('matches.show', $event->slug),
                'lastmod' => $event->updated_at?->toAtomString(),
            ]))
            ->filter()
            ->values();

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function organisations(): Response
    {
        $urls = Organisation::query()
            ->published()
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

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function venues(): Response
    {
        $urls = Venue::query()
            ->published()
            ->orderBy('name')
            ->get()
            ->map(fn (Venue $venue) => $this->safeUrl('venues sitemap', $venue, fn (): array => [
                'loc' => route('ranges.show', $venue->slug),
                'lastmod' => $venue->updated_at?->toAtomString(),
            ]))
            ->filter()
            ->values();

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function disciplines(): Response
    {
        $disciplines = Discipline::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->get();

        $urls = new Collection;

        foreach ($disciplines as $discipline) {
            $root = $this->safeUrl('disciplines sitemap', $discipline, fn (): array => [
                'loc' => route('disciplines.show', $discipline->slug),
                'lastmod' => $discipline->updated_at?->toAtomString(),
            ]);

            if ($root !== null) {
                $urls->push($root);
            }

            foreach (Province::cases() as $province) {
                // Same threshold as DisciplineController noindex guard.
                if ($this->disciplineProvinceListingCount($discipline, $province) < 3) {
                    continue;
                }

                $slice = $this->safeUrl('disciplines province sitemap', $discipline, fn (): array => [
                    'loc' => route('disciplines.province', [$discipline->slug, $province->urlSlug()]),
                    'lastmod' => $discipline->updated_at?->toAtomString(),
                ]);

                if ($slice !== null) {
                    $urls->push($slice);
                }
            }
        }

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function providers(): Response
    {
        $urls = new Collection;
        $now = now()->toAtomString();

        foreach (ProviderCategory::cases() as $category) {
            $nationalCount = Provider::query()
                ->published()
                ->where('category', $category)
                ->count();

            // Empty category shells stay out of the sitemap (and are noindexed).
            if ($nationalCount < 1) {
                continue;
            }

            $urls->push([
                'loc' => route('suppliers.category', $category->urlSlug()),
                'lastmod' => $now,
            ]);

            foreach (Province::cases() as $province) {
                $provinceCount = Provider::query()
                    ->published()
                    ->where('category', $category)
                    ->where('province', $province)
                    ->count();

                // Same threshold as ProviderController noindex guard.
                if ($provinceCount < 3) {
                    continue;
                }

                $urls->push([
                    'loc' => route('suppliers.province', [$category->urlSlug(), $province->urlSlug()]),
                    'lastmod' => $now,
                ]);
            }
        }

        Provider::query()
            ->where('status', ListingStatus::Published)
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

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    /**
     * Mirrors DisciplineController thin-slice counting (clubs + upcoming
     * events + ranges) so sitemap membership matches the noindex guard.
     */
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
     * Build a single sitemap entry, logging + dropping the row on failure.
     * Sitemap outages should never take down the whole file just because
     * one bad row (missing slug, unroutable enum, whatever) trips
     * route() generation — a partial sitemap is strictly better than a
     * 500 for Search Console, which caches the last successful fetch.
     *
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function xml(string $view, array $data): Response
    {
        return response()
            ->view($view, $data)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
