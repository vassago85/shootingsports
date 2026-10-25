<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Province;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\FollowSuggestionResource;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Suggests clubs the shooter should follow given a province and a
 * list of disciplines they care about. Ranks by upcoming match
 * count so the clubs that actually run matches in those sports
 * show first, and groups the result into "national" bodies
 * (federations and nationwide associations) separately from
 * regional clubs so the mobile UI can render them in two sections.
 */
class MeFollowSuggestionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $data = $request->validate([
            'province' => ['nullable', 'string'],
            'disciplines' => ['nullable', 'array'],
            'disciplines.*' => ['string'],
        ]);

        $province = $this->resolveProvince($data['province'] ?? null);
        $disciplineIds = $this->resolveDisciplineIds($data['disciplines'] ?? []);

        // Base: upcoming matches, optionally scoped to the chosen
        // disciplines and venue province. Group by host organisation
        // to get the match count per club.
        $matchCountsByOrg = Event::query()
            ->upcoming()
            ->whereNotNull('host_organisation_id')
            ->when($disciplineIds->isNotEmpty(), function (Builder $q) use ($disciplineIds): void {
                $q->whereHas('disciplines', function (Builder $d) use ($disciplineIds): void {
                    $d->whereIn('disciplines.id', $disciplineIds->all());
                });
            })
            ->when($province !== null, function (Builder $q) use ($province): void {
                $q->whereHas('venue', function (Builder $v) use ($province): void {
                    $v->where('venues.province', $province->value);
                });
            })
            ->selectRaw('host_organisation_id, count(*) as upcoming_count')
            ->groupBy('host_organisation_id')
            ->orderByDesc('upcoming_count')
            ->get()
            ->keyBy('host_organisation_id');

        if ($matchCountsByOrg->isEmpty()) {
            return response()->json([
                'data' => [
                    'national' => [],
                    'regional' => [],
                ],
            ]);
        }

        $organisations = Organisation::query()
            ->whereIn('id', $matchCountsByOrg->keys()->all())
            ->get()
            ->sortByDesc(fn (Organisation $org) => (int) $matchCountsByOrg->get($org->id)->upcoming_count);

        [$national, $regional] = $organisations->partition(
            fn (Organisation $org) => $org->isNationalBody()
        );

        $toCollection = fn (Collection $orgs): array => $orgs->values()
            ->map(fn (Organisation $org) => (new FollowSuggestionResource(
                $org,
                (int) $matchCountsByOrg->get($org->id)->upcoming_count,
            ))->resolve())
            ->all();

        return response()->json([
            'data' => [
                'national' => $toCollection($national),
                'regional' => $toCollection($regional),
            ],
        ]);
    }

    private function resolveProvince(?string $input): ?Province
    {
        if ($input === null || $input === '') {
            return null;
        }

        return Province::tryFrom($input) ?? Province::fromUrlSlug($input);
    }

    /**
     * @param  array<int, string>  $inputs
     * @return Collection<int, int>
     */
    private function resolveDisciplineIds(array $inputs): Collection
    {
        if ($inputs === []) {
            return collect();
        }

        // Accept either numeric ids or slugs — the mobile client
        // sends whichever representation it has from the public
        // disciplines list.
        $numeric = collect($inputs)->filter(fn ($v) => ctype_digit((string) $v))->map(fn ($v) => (int) $v);
        $slugs = collect($inputs)->reject(fn ($v) => ctype_digit((string) $v));

        $fromSlugs = $slugs->isEmpty()
            ? collect()
            : Discipline::query()->whereIn('slug', $slugs->all())->pluck('id');

        return $numeric->merge($fromSlugs)->unique()->values();
    }
}
