<?php

namespace App\Http\Controllers;

use App\Enums\OrganisationType;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Support\PublicCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $stats = Cache::remember(PublicCache::key('stats'), 600, function (): array {
            return [
                'clubs' => Organisation::query()->published()->where('type', OrganisationType::Club)->count(),
                'ranges' => Venue::query()->published()->count(),
                'matches' => Event::query()->published()->count(),
                'disciplines' => Discipline::query()->where('is_published', true)->count(),
                'provinces' => 9,
                'suppliers' => Provider::query()->published()->count(),
            ];
        });

        $monthAhead = Event::query()
            ->upcoming()
            ->with(['hostOrganisation.parent', 'venue', 'disciplines'])
            ->where('starts_at', '<=', now()->addDays(30))
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        $rail = $monthAhead->isNotEmpty()
            ? $monthAhead
            : Event::query()
                ->upcoming()
                ->with(['hostOrganisation.parent', 'venue', 'disciplines'])
                ->orderBy('starts_at')
                ->limit(10)
                ->get();

        $upcomingCounts = Discipline::upcomingCounts();
        $disciplines = Discipline::query()
            ->where('is_published', true)
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Discipline $discipline) use ($upcomingCounts): Discipline {
                $discipline->setAttribute('events_count', $upcomingCounts[$discipline->id] ?? 0);

                return $discipline;
            });

        $clubs = Organisation::query()
            ->published()
            ->clubs()
            ->orderBy('name')
            ->limit(6)
            ->get();

        $ranges = Venue::query()
            ->published()
            ->orderBy('name')
            ->limit(6)
            ->get();

        $suppliers = Provider::query()
            ->published()
            ->orderBy('name')
            ->limit(6)
            ->get();

        return view('public.home', [
            'stats' => $stats,
            'rail' => $rail,
            'railLabel' => $monthAhead->isNotEmpty() ? 'Next 30 days' : 'Next up',
            'disciplines' => $disciplines,
            'clubs' => $clubs,
            'ranges' => $ranges,
            'suppliers' => $suppliers,
        ]);
    }
}
