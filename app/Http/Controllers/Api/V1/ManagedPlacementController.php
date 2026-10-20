<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Placement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only advert stats for the mobile Shop card. The desk website
 * stays the only place a user can edit placements, upload creatives,
 * or change dates; the app just shows impressions and clicks per
 * active placement so the owner can tell their ads are running.
 */
class ManagedPlacementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $providerIds = $user->managedProviders()->pluck('providers.id')
            ->merge($user->providers()->pluck('id'))
            ->unique();

        if ($providerIds->isEmpty()) {
            return response()->json(['data' => []]);
        }

        $rows = Placement::query()
            ->whereIn('provider_id', $providerIds)
            ->orderByDesc('is_active')
            ->orderByDesc('starts_on')
            ->limit(100)
            ->get(['id', 'provider_id', 'slot', 'headline', 'starts_on', 'ends_on', 'impressions', 'clicks', 'is_active'])
            ->map(fn (Placement $placement): array => [
                'id' => $placement->id,
                'provider_id' => $placement->provider_id,
                'slot' => $placement->slot?->value,
                'headline' => $placement->headline,
                'starts_on' => $placement->starts_on?->toDateString(),
                'ends_on' => $placement->ends_on?->toDateString(),
                'impressions' => (int) $placement->impressions,
                'clicks' => (int) $placement->clicks,
                'is_active' => (bool) $placement->is_active,
            ])
            ->all();

        return response()->json(['data' => $rows]);
    }
}
