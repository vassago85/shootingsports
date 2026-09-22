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

        $providers = Provider::query()
            ->published()
            ->when($division, fn ($query) => $query->inDivision($division))
            ->orderByTier()
            ->get()
            ->groupBy(fn (Provider $provider) => $provider->category->value);

        $total = $providers->reduce(fn (int $carry, $group): int => $carry + $group->count(), 0);
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
            collect(ProviderCategory::cases())
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
            'division' => $division,
            'categories' => ProviderCategory::cases(),
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'jsonLd' => [$itemList, $breadcrumbs],
        ]);
    }

    public function category(string $category, ?string $province = null): View
    {
        $categoryEnum = ProviderCategory::fromUrlSlug($category);
        abort_unless($categoryEnum instanceof ProviderCategory, 404);

        $provinceEnum = $province ? Province::fromUrlSlug($province) : null;
        abort_if($province !== null && $provinceEnum === null, 404);

        $providers = Provider::query()
            ->published()
            ->where('category', $categoryEnum)
            ->when($provinceEnum, fn ($q) => $q->where('province', $provinceEnum))
            ->orderByTier()
            ->get();

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
            'provinces' => Province::cases(),
            'noindex' => $providers->isEmpty()
                || ($provinceEnum !== null && $providers->count() < 3),
            'jsonLd' => [JsonLd::breadcrumbs($crumbs)],
        ]);
    }

    public function show(Provider $provider): View
    {
        abort_unless($provider->status === ListingStatus::Published, 404);

        $provider->load('disciplines');

        return view('public.suppliers.show', [
            'provider' => $provider,
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
