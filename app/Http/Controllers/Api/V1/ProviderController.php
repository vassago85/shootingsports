<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProviderCategory;
use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProviderResource;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public providers ("shops" / suppliers) directory for the mobile
 * Find Shops mode.
 *
 * Distributor-only listings are excluded by `scopeListed()` because
 * they're advertising accounts, not shopping destinations.
 */
class ProviderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $province = $request->string('province')->toString() !== ''
            ? Province::fromUrlSlug($request->string('province')->toString())
                ?? Province::tryFrom($request->string('province')->toString())
            : null;

        $search = trim($request->string('q')->toString());

        $category = $request->string('category')->toString() !== ''
            ? ProviderCategory::fromUrlSlug($request->string('category')->toString())
                ?? ProviderCategory::tryFrom($request->string('category')->toString())
            : null;

        $providers = Provider::query()
            ->published()
            ->listed()
            ->when($province, fn ($q) => $q->where('province', $province))
            ->when($category, fn ($q) => $q->offering($category))
            ->when($search !== '', function ($q) use ($search): void {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(function ($inner) use ($like): void {
                    $inner->where('name', 'like', $like)
                        ->orWhere('town', 'like', $like)
                        ->orWhere('tagline', 'like', $like);
                });
            })
            ->orderByTier()
            ->get();

        return ProviderResource::collection($providers);
    }
}
