<?php

namespace App\Http\Controllers;

use App\Enums\ListingStatus;
use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Models\Provider;
use App\Support\JsonLd;
use Illuminate\View\View;

class ProviderController extends Controller
{
    public function index(): View
    {
        $providers = Provider::query()
            ->published()
            ->orderByTier()
            ->get()
            ->groupBy(fn (Provider $provider) => $provider->category->value);

        return view('public.suppliers.index', [
            'grouped' => $providers,
            'categories' => ProviderCategory::cases(),
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

        return view('public.suppliers.category', [
            'category' => $categoryEnum,
            'province' => $provinceEnum,
            'providers' => $providers,
            'provinces' => Province::cases(),
            'noindex' => $provinceEnum !== null && $providers->count() < 3,
        ]);
    }

    public function show(Provider $provider): View
    {
        abort_unless($provider->status === ListingStatus::Published, 404);

        $provider->load('disciplines');

        return view('public.suppliers.show', [
            'provider' => $provider,
            'jsonLd' => JsonLd::provider($provider),
        ]);
    }
}
