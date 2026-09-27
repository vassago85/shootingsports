<?php

namespace App\Http\Controllers;

use App\Enums\OrganisationType;
use App\Models\Article;
use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Support\PublicCache;
use App\Support\ThisWeekend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
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
        $weekend = $this->railEventsBetween($weekendFrom, $weekendTo);

        if ($weekend->isNotEmpty()) {
            $rail = $weekend;
            $railLabel = 'This weekend';
        } else {
            // Sunday evening the current window is today only. Show the
            // coming Friday–Sunday instead of the rest of the month.
            [$nextFrom, $nextTo] = ThisWeekend::nextRange();
            $nextWeekend = $this->railEventsBetween($nextFrom, $nextTo);

            if ($nextWeekend->isNotEmpty()) {
                $rail = $nextWeekend;
                $railLabel = 'Next weekend';
            } else {
                $rail = Event::query()
                    ->upcoming()
                    ->with(['hostOrganisation.parent', 'venue', 'disciplines'])
                    ->orderBy('starts_at')
                    ->limit(4)
                    ->get();
                $railLabel = 'Next up';
            }
        }

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
            'articles' => Article::query()->published()->with('author')->orderByDesc('published_at')->limit(3)->get(),
        ]);
    }

    /**
     * @return Collection<int, Event>
     */
    private function railEventsBetween(Carbon $from, Carbon $to): Collection
    {
        return Event::query()
            ->upcoming()
            ->with(['hostOrganisation.parent', 'venue', 'disciplines'])
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();
    }
}
