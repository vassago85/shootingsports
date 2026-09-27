<?php

namespace App\Http\Controllers;

use App\Enums\Division;
use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Provider;
use App\Support\JsonLd;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function index(Request $request): View
    {
        $division = Division::fromPublicQuery($request->string('division')->toString());
        $search = trim($request->string('q')->toString());

        $listed = Provider::query()
            ->published()
            ->listed()
            ->when($division, fn ($query) => $query->inDivision($division))
            ->orderByTier()
            ->get();

        $providers = collect(ProviderCategory::publicCases())
            ->mapWithKeys(function (ProviderCategory $category) use ($listed) {
                [$primary, $secondary] = $listed
                    ->filter(fn (Provider $provider): bool => $provider->offers($category))
                    ->partition(fn (Provider $provider): bool => $provider->category === $category);

                return [$category->value => $primary->concat($secondary)->values()];
            });

        // In-memory search across the tier-ordered listing. Keeping
        // the filter here means the page still renders the category
        // grid; the search results sit above it so a shooter who
        // just wants "Bidvest" finds them without picking a category
        // first.
        $matches = collect();

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $matches = $listed->filter(function (Provider $provider) use ($needle): bool {
                $haystacks = [
                    $provider->name,
                    $provider->tagline,
                    $provider->description,
                    $provider->town,
                    $provider->province?->getLabel(),
                    $provider->metro?->getLabel(),
                    $provider->category?->getLabel(),
                ];

                foreach ($provider->offeredCategories() as $offer) {
                    $haystacks[] = $offer->getLabel();
                }

                foreach ($haystacks as $value) {
                    if ($value !== null && str_contains(mb_strtolower((string) $value), $needle)) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        $total = $listed->count();
        $countPhrase = match ($total) {
            0 => 'No industry listings yet',
            1 => '1 industry supplier',
            default => $total.' industry suppliers',
        };

        $seoTitle = 'Gunsmiths, dealers & instructors';
        $seoDescription = $countPhrase.' on the South African register. Gunsmiths, dealers, ammunition, optics, safes and more.';

        // ItemList by category since suppliers are grouped that way in
        // the UI. Passing category landing pages (not per-supplier)
        // keeps the list a manageable size for Google.
        $itemList = JsonLd::itemList(
            collect(ProviderCategory::publicCases())
                ->map(fn (ProviderCategory $c): array => [
                    'name' => $c->getLabel(),
                    'url' => route('suppliers.category', $c->urlSlug()),
                ])->values()->all(),
            'Firearms industry categories',
        );

        $breadcrumbs = JsonLd::breadcrumbs([
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Industry', 'url' => route('suppliers.index')],
        ]);

        return view('public.suppliers.index', [
            'grouped' => $providers,
            'matches' => $matches,
            'search' => $search,
            'division' => $division,
            'categories' => ProviderCategory::publicCases(),
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'jsonLd' => [$itemList, $breadcrumbs],
        ]);
    }

    public function category(string $category, ?string $province = null): View
    {
        $categoryEnum = ProviderCategory::fromUrlSlug($category);
        abort_unless($categoryEnum instanceof ProviderCategory && $categoryEnum->isPublic(), 404);

        $provinceEnum = $province ? Province::fromUrlSlug($province) : null;
        abort_if($province !== null && $provinceEnum === null, 404);

        $providers = Provider::query()
            ->published()
            ->listed()
            ->offering($categoryEnum)
            ->when($provinceEnum, fn ($q) => $q->where('province', $provinceEnum))
            ->orderByRaw('case when category = ? then 0 else 1 end', [$categoryEnum->value])
            ->orderByTier()
            ->get();

        $primaryProviders = $providers
            ->filter(fn (Provider $provider): bool => $provider->category === $categoryEnum)
            ->values();
        $secondaryProviders = $providers
            ->reject(fn (Provider $provider): bool => $provider->category === $categoryEnum)
            ->values();

        $crumbs = [
            ['name' => 'Home', 'url' => route('home')],
            ['name' => 'Industry', 'url' => route('suppliers.index')],
            ['name' => $categoryEnum->getLabel(), 'url' => route('suppliers.category', $categoryEnum->urlSlug())],
        ];

        if ($provinceEnum !== null) {
            $crumbs[] = [
                'name' => $provinceEnum->getLabel(),
                'url' => route('suppliers.province', [$categoryEnum->urlSlug(), $provinceEnum->urlSlug()]),
            ];
        }

        return view('public.suppliers.category', [
            'category' => $categoryEnum,
            'province' => $provinceEnum,
            'providers' => $providers,
            'primaryProviders' => $primaryProviders,
            'secondaryProviders' => $secondaryProviders,
            'provinces' => Province::cases(),
            'noindex' => $providers->isEmpty()
                || ($provinceEnum !== null && $providers->count() < 3),
            'jsonLd' => [JsonLd::breadcrumbs($crumbs)],
        ]);
    }

    public function show(Provider $provider): View
    {
        abort_unless($provider->status === ListingStatus::Published, 404);
        abort_unless($provider->category === null || $provider->category->isPublic(), 404);

        $provider->load('disciplines');

        // Sponsored / partnered matches that are still open. Only
        // rendered when there is at least one; nothing shows for a
        // supplier with no partnerships. Draft matches are excluded
        // by `upcoming()` (which composes `published()`).
        $sponsoredEvents = $provider->sponsoredEvents()
            ->upcoming()
            ->with(['hostOrganisation', 'venue', 'disciplines', 'flags', 'banner'])
            ->limit(6)
            ->get();

        return view('public.suppliers.show', [
            'provider' => $provider,
            'sponsoredEvents' => $sponsoredEvents,
            'seo' => Seo::forProvider($provider),
            'jsonLd' => [
                JsonLd::provider($provider),
                JsonLd::breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Industry', 'url' => route('suppliers.index')],
                    ['name' => $provider->category->getLabel(), 'url' => route('suppliers.category', $provider->category->urlSlug())],
                    ['name' => $provider->name, 'url' => route('suppliers.show', $provider->slug)],
                ]),
            ],
        ]);
    }
}
