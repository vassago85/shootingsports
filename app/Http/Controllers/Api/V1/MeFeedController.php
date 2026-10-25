<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Http\Resources\Api\V1\FeedEntryResource;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\User;
use App\Support\HomeWindows;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Personalised home feed for signed-in shooters. Three buckets:
 *
 * - `saved`     — the shooter's upcoming saved matches, cancellations
 *                 included so the "my match is off" case is loud.
 * - `this_weekend` — Fri–Sun window per HomeWindows::weekend().
 * - `next_30_days` — rolling 30-day window per HomeWindows::next30Days().
 *
 * The two "bucket" lists are the union of:
 *   1. Matches hosted by a followed club or federation.
 *   2. Matches in a followed discipline whose venue is in the user's
 *      home or extra provinces. If no province is set, this half is
 *      skipped and meta.needs_province flips true.
 *
 * NOTE on sanctioning bodies: the brief asks for "matches sanctioned
 * by followed federation" as an OR clause here. The current Event
 * model has no sanctioning_body relation (only host_organisation_id
 * and a boolean "sanctioned-ranked" flag that doesn't name the
 * sanctioning body), so this surface is covered by (1) above —
 * federation-hosted matches only. If a sanctioning_body column lands
 * later, extend the buildBucket query without changing the response
 * shape.
 *
 * Cancelled matches and already-saved matches are excluded from both
 * buckets so the user never sees the same thing twice on one screen.
 */
class MeFeedController extends Controller
{
    private const EAGER = [
        'hostOrganisation.parent',
        'venue',
        'disciplines.parent',
        'flags',
        'banner',
    ];

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        [$followedOrgIds, $followedDisciplineIds] = $this->loadFollows($user);
        $userProvinces = $this->userProvinceValues($user);

        $hasFollows = $followedOrgIds->isNotEmpty() || $followedDisciplineIds->isNotEmpty();
        $needsProvince = $followedDisciplineIds->isNotEmpty() && $userProvinces->isEmpty();

        // Preload the followed orgs so we can quote their name /
        // province in the `reasons` array without an N+1.
        $followedOrgs = $followedOrgIds->isEmpty()
            ? collect()
            : Organisation::query()->whereIn('id', $followedOrgIds)->get()->keyBy('id');

        $followedDisciplines = $followedDisciplineIds->isEmpty()
            ? collect()
            : Discipline::query()->whereIn('id', $followedDisciplineIds)->get()->keyBy('id');

        $saved = $this->savedBucket($user);
        $savedIds = $saved->pluck('id');

        [$weekendFrom, $weekendTo] = HomeWindows::weekend();
        [, $thirtyDayEnd] = HomeWindows::next30Days();

        $thisWeekend = $this->buildBucket(
            $followedOrgIds,
            $followedDisciplineIds,
            $userProvinces,
            $weekendFrom,
            $weekendTo,
            $savedIds,
            $followedOrgs,
            $followedDisciplines,
        );

        $next30 = $this->buildBucket(
            $followedOrgIds,
            $followedDisciplineIds,
            $userProvinces,
            Carbon::now(),
            $thirtyDayEnd,
            $savedIds,
            $followedOrgs,
            $followedDisciplines,
        );

        return response()->json([
            'data' => [
                'saved' => EventResource::collection($saved)->resolve(),
                'this_weekend' => $this->toResource($thisWeekend),
                'next_30_days' => $this->toResource($next30),
            ],
            'meta' => [
                'has_follows' => $hasFollows,
                'needs_province' => $needsProvince,
            ],
        ]);
    }

    /**
     * @return array{0: Collection<int, int>, 1: Collection<int, int>}
     */
    private function loadFollows(User $user): array
    {
        $follows = $user->follows()
            ->whereIn('followable_type', ['organisation', 'discipline'])
            ->get(['followable_type', 'followable_id']);

        return [
            $follows->where('followable_type', 'organisation')
                ->pluck('followable_id')
                ->map(fn ($id) => (int) $id)
                ->values(),
            $follows->where('followable_type', 'discipline')
                ->pluck('followable_id')
                ->map(fn ($id) => (int) $id)
                ->values(),
        ];
    }

    /**
     * @return Collection<int, string>
     */
    private function userProvinceValues(User $user): Collection
    {
        return collect([$user->home_province?->value])
            ->merge($user->extra_provinces ?? [])
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Saved matches: upcoming (incl. cancelled, so the shooter sees
     * cancellations before travelling). Does not drop cancelled —
     * EventResource surfaces `status` and the mobile UI colours it.
     *
     * @return Collection<int, Event>
     */
    private function savedBucket(User $user): Collection
    {
        return $user->savedEvents()
            ->notFinished()
            ->with(self::EAGER)
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * @param  Collection<int, int>  $followedOrgIds
     * @param  Collection<int, int>  $followedDisciplineIds
     * @param  Collection<int, string>  $userProvinces
     * @param  Collection<int, int>  $savedIds
     * @param  Collection<int, Organisation>  $followedOrgs
     * @param  Collection<int, Discipline>  $followedDisciplines
     * @return Collection<int, array{event: Event, reasons: array<int, array<string, mixed>>}>
     */
    private function buildBucket(
        Collection $followedOrgIds,
        Collection $followedDisciplineIds,
        Collection $userProvinces,
        Carbon $from,
        Carbon $to,
        Collection $savedIds,
        Collection $followedOrgs,
        Collection $followedDisciplines,
    ): Collection {
        if ($followedOrgIds->isEmpty() && $followedDisciplineIds->isEmpty()) {
            return collect();
        }

        $query = Event::query()
            ->upcoming()
            ->with(self::EAGER)
            ->whereBetween('starts_at', [$from, $to])
            ->whereNotIn('id', $savedIds->all());

        $hasActiveBranch = false;

        $query->where(function (Builder $outer) use ($followedOrgIds, $followedDisciplineIds, $userProvinces, &$hasActiveBranch): void {
            if ($followedOrgIds->isNotEmpty()) {
                $outer->whereIn('host_organisation_id', $followedOrgIds->all());
                $hasActiveBranch = true;
            }

            // Discipline branch only fires when the user has at least
            // one province set, per the brief ("If no home province:
            // discipline part returns nothing + meta.needs_province=true").
            if ($followedDisciplineIds->isNotEmpty() && $userProvinces->isNotEmpty()) {
                $callback = function (Builder $sub) use ($followedDisciplineIds, $userProvinces): void {
                    $sub->whereHas('disciplines', function (Builder $d) use ($followedDisciplineIds): void {
                        $d->whereIn('disciplines.id', $followedDisciplineIds->all());
                    })->whereHas('venue', function (Builder $v) use ($userProvinces): void {
                        $v->whereIn('venues.province', $userProvinces->all());
                    });
                };

                $hasActiveBranch
                    ? $outer->orWhere($callback)
                    : $outer->where($callback);

                $hasActiveBranch = true;
            }
        });

        // No branch fired (e.g. discipline-only follows with no province).
        // The outer where closure above is empty — unguarded, every
        // match in the window would leak through.
        if (! $hasActiveBranch) {
            return collect();
        }

        $events = $query->orderBy('starts_at')->get();

        return $events->map(fn (Event $event) => [
            'event' => $event,
            'reasons' => $this->reasonsFor($event, $followedOrgs, $followedDisciplines),
        ])->values();
    }

    /**
     * Build the per-match reasons list. Club reasons first, then
     * disciplines. Deduped on (type, name) so a match tagged with
     * both the parent and child of a followed discipline doesn't
     * get two identical rows.
     *
     * @param  Collection<int, Organisation>  $followedOrgs
     * @param  Collection<int, Discipline>  $followedDisciplines
     * @return array<int, array<string, mixed>>
     */
    private function reasonsFor(
        Event $event,
        Collection $followedOrgs,
        Collection $followedDisciplines,
    ): array {
        $reasons = [];

        if ($event->host_organisation_id !== null && $followedOrgs->has($event->host_organisation_id)) {
            /** @var Organisation $org */
            $org = $followedOrgs->get($event->host_organisation_id);
            $reasons[] = [
                'type' => 'club',
                'name' => $org->name,
                'province' => $org->province?->getLabel(),
            ];
        }

        if ($followedDisciplines->isNotEmpty() && $event->relationLoaded('disciplines')) {
            foreach ($event->disciplines as $discipline) {
                if (! $followedDisciplines->has($discipline->id)) {
                    continue;
                }

                $reasons[] = [
                    'type' => 'discipline',
                    // displayName() collapses a child discipline onto
                    // its parent label so follower of "Precision Rifle"
                    // and a match tagged "PRS" still reads as "Precision Rifle".
                    'name' => $discipline->displayName(),
                    'province' => $event->venue?->province?->getLabel(),
                ];
            }
        }

        return collect($reasons)
            ->unique(fn (array $r) => $r['type'].'|'.$r['name'])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array{event: Event, reasons: array<int, array<string, mixed>>}>  $entries
     * @return array<int, array<string, mixed>>
     */
    private function toResource(Collection $entries): array
    {
        return $entries->map(
            fn (array $entry) => (new FeedEntryResource($entry['event'], $entry['reasons']))->resolve()
        )->all();
    }
}
