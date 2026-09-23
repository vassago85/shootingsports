<?php

namespace App\Http\Controllers;

use App\Enums\OrganisationType;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Support\PublicCache;
use App\Support\ThisWeekend;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $stats = Cache::remember(PublicCache::key('stats'), 600, function (): array {
            return [
                // Kept strict: only membership clubs. Series and associations
                // get their own counters so the stats bar matches the mental
                // model users have when they see "clubs" (people they join)
                // versus "series" (branded recurring matches they enter).
                'clubs' => Organisation::query()->published()->where('type', OrganisationType::Club)->count(),
                'series' => Organisation::query()->published()->where('type', OrganisationType::Series)->count(),
                'ranges' => Venue::query()->published()->count(),
                // Count what visitors can actually see on /calendar right now.
                // The old published() scope included Completed/Cancelled and
                // inflated the number 2-3x above what any user could reach.
                'matches' => Event::query()->upcoming()->count(),
                'disciplines' => Discipline::query()->where('is_published', true)->count(),
                'provinces' => 9,
                'suppliers' => Provider::query()->published()->listed()->count(),
            ];
        });

        [$weekendFrom, $weekendTo] = ThisWeekend::range();

        $weekend = Event::query()
            ->upcoming()
            ->with(['hostOrganisation.parent', 'venue', 'disciplines'])
            ->whereBetween('starts_at', [$weekendFrom, $weekendTo])
            ->orderBy('starts_at')
            ->limit(4)
            ->get();

        $monthAhead = $weekend->isNotEmpty()
            ? $weekend
            : Event::query()
                ->upcoming()
                ->with(['hostOrganisation.parent', 'venue', 'disciplines'])
                ->where('starts_at', '<=', now()->addDays(30))
                ->orderBy('starts_at')
                ->limit(4)
                ->get();

        $rail = $monthAhead->isNotEmpty()
            ? $monthAhead
            : Event::query()
                ->upcoming()
                ->with(['hostOrganisation.parent', 'venue', 'disciplines'])
                ->orderBy('starts_at')
                ->limit(4)
                ->get();

        $railLabel = match (true) {
            $weekend->isNotEmpty() => 'This weekend',
            $monthAhead->isNotEmpty() => 'Next 30 days',
            default => 'Next up',
        };

        $upcoming = Event::query()
            ->upcoming()
            ->with(['hostOrganisation.parent', 'venue', 'disciplines'])
            ->where('starts_at', '<=', now()->addDays(30))
            ->orderBy('starts_at')
            ->limit(10)
            ->get();

        return view('public.home', [
            'stats' => $stats,
            'rail' => $rail,
            'railLabel' => $railLabel,
            'upcoming' => $upcoming,
        ]);
    }
}
