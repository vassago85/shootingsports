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
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $sitemaps = [
            url('/sitemaps/events.xml'),
            url('/sitemaps/organisations.xml'),
            url('/sitemaps/venues.xml'),
            url('/sitemaps/disciplines.xml'),
            url('/sitemaps/providers.xml'),
        ];

        return $this->xml('public.sitemaps.index', ['sitemaps' => $sitemaps]);
    }

    public function events(): Response
    {
        $urls = Event::query()
            ->published()
            ->orderBy('starts_at')
            ->get()
            ->map(fn (Event $event): array => [
                'loc' => route('matches.show', $event->slug),
                'lastmod' => $event->updated_at?->toAtomString(),
            ]);

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function organisations(): Response
    {
        $urls = Organisation::query()
            ->published()
            ->orderBy('name')
            ->get()
            ->map(fn (Organisation $org): array => [
                'loc' => $org->isFederationListing()
                    ? route('federations.show', $org->slug)
                    : route('clubs.show', $org->slug),
                'lastmod' => $org->updated_at?->toAtomString(),
            ]);

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function venues(): Response
    {
        $urls = Venue::query()
            ->published()
            ->orderBy('name')
            ->get()
            ->map(fn (Venue $venue): array => [
                'loc' => route('ranges.show', $venue->slug),
                'lastmod' => $venue->updated_at?->toAtomString(),
            ]);

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function disciplines(): Response
    {
        $disciplines = Discipline::query()
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->get();

        $urls = collect();

        foreach ($disciplines as $discipline) {
            $urls->push([
                'loc' => route('disciplines.show', $discipline->slug),
                'lastmod' => $discipline->updated_at?->toAtomString(),
            ]);

            foreach (Province::cases() as $province) {
                $urls->push([
                    'loc' => route('disciplines.province', [$discipline->slug, $province->urlSlug()]),
                ]);
            }
        }

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
    }

    public function providers(): Response
    {
        $urls = collect();

        foreach (ProviderCategory::cases() as $category) {
            $urls->push(['loc' => route('suppliers.category', $category->urlSlug())]);

            foreach (Province::cases() as $province) {
                $urls->push([
                    'loc' => route('suppliers.province', [$category->urlSlug(), $province->urlSlug()]),
                ]);
            }
        }

        Provider::query()
            ->where('status', ListingStatus::Published)
            ->get()
            ->each(function (Provider $provider) use ($urls): void {
                $urls->push([
                    'loc' => route('suppliers.show', $provider->slug),
                    'lastmod' => $provider->updated_at?->toAtomString(),
                ]);
            });

        return $this->xml('public.sitemaps.urlset', ['urls' => $urls]);
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
