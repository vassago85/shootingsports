<?php

namespace App\Http\Controllers;

use App\Enums\Province;
use App\Models\Discipline;
use App\Models\Organisation;
use App\Models\Venue;
use App\Queries\PublicEventQuery;
use Illuminate\View\View;

class DisciplineController extends Controller
{
    public function index(): View
    {
        $upcomingCounts = Discipline::upcomingCounts();
        $disciplines = Discipline::query()
            ->where('is_published', true)
            ->whereNull('parent_id')
            ->orderBy('family')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Discipline $discipline) use ($upcomingCounts): Discipline {
                $discipline->setAttribute('events_count', $upcomingCounts[$discipline->id] ?? 0);

                return $discipline;
            });

        return view('public.disciplines.index', [
            'disciplines' => $disciplines,
        ]);
    }

    public function show(Discipline $discipline, ?string $province = null): View
    {
        abort_unless($discipline->is_published, 404);

        $provinceEnum = $province ? Province::fromUrlSlug($province) : null;

        abort_if($province !== null && $provinceEnum === null, 404);

        return view('public.disciplines.show', $this->pageData($discipline, $provinceEnum));
    }

    /**
     * @return array<string, mixed>
     */
    private function pageData(Discipline $discipline, ?Province $province): array
    {
        $discipline->load(['children', 'federation', 'parent']);

        $events = (new PublicEventQuery(
            disciplineSlug: $discipline->slug,
            provinceSlug: $province?->urlSlug(),
        ))->get();

        $clubs = Organisation::query()
            ->published()
            ->clubs()
            ->whereHas('disciplines', fn ($q) => $q->whereIn('disciplines.id', $discipline->treeIds()))
            ->when($province, fn ($q) => $q->where('province', $province))
            ->with('disciplines')
            ->orderBy('name')
            ->get();

        $ranges = Venue::query()
            ->published()
            ->whereHas('disciplines', fn ($q) => $q->whereIn('disciplines.id', $discipline->treeIds()))
            ->when($province, fn ($q) => $q->where('province', $province))
            ->count();

        $listingCount = $clubs->count() + $events->count() + $ranges;
        $noindex = $province !== null && $listingCount < 3;

        $familySiblings = Discipline::query()
            ->where('is_published', true)
            ->where('family', $discipline->family)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        $related = Discipline::query()
            ->where('is_published', true)
            ->where('id', '!=', $discipline->id)
            ->whereNull('parent_id')
            ->where(function ($q) use ($discipline): void {
                $q->where('family', $discipline->family)
                    ->orWhere('id', '!=', $discipline->id);
            })
            ->where('id', '!=', $discipline->parent_id)
            ->orderByRaw('case when family = ? then 0 else 1 end', [$discipline->family->value])
            ->orderBy('sort_order')
            ->limit(4)
            ->get();

        $provincesActive = Organisation::query()
            ->published()
            ->whereHas('disciplines', fn ($q) => $q->whereIn('disciplines.id', $discipline->treeIds()))
            ->whereNotNull('province')
            ->distinct()
            ->count('province');

        return [
            'discipline' => $discipline,
            'province' => $province,
            'events' => $events,
            'clubs' => $clubs,
            'familySiblings' => $familySiblings,
            'related' => $related,
            'noindex' => $noindex,
            'figures' => [
                'matches' => $events->count(),
                'clubs' => $clubs->count(),
                'distance' => $discipline->typical_distances,
                'provinces' => $provincesActive,
            ],
        ];
    }
}
