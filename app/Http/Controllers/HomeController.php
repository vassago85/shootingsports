<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Event;
use App\Support\PublicCounts;
use App\Support\ThisWeekend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        // Counts come from a single source so the hero, the footer,
        // and any empty-state copy always agree.
        $stats = PublicCounts::all();

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
