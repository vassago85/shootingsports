<?php

namespace App\Http\Controllers;

use App\Models\Discipline;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use App\Support\EventDate;
use App\Support\Money;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AppMockupController extends Controller
{
    public function __invoke(Request $request): View
    {
        $journey = $this->journey();
        $allowed = array_column($journey, 'key');
        $screen = $request->string('screen')->toString();

        if (! in_array($screen, $allowed, true)) {
            $screen = 'home';
        }

        $matches = $this->matches();
        $slug = $request->string('slug')->toString();

        return view('mockups.apps', [
            'screen' => $screen,
            'journey' => $journey,
            'matches' => $matches,
            'match' => $this->find($matches, $slug) ?? ($matches[0] ?? null),
            'clubs' => $this->clubs(),
            'ranges' => $this->ranges(),
            'sports' => $this->sports(),
            'suppliers' => $this->suppliers(),
        ]);
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function journey(): array
    {
        return [
            ['key' => 'onboarding', 'label' => 'Onboarding'],
            ['key' => 'home', 'label' => 'Home'],
            ['key' => 'events', 'label' => 'Events'],
            ['key' => 'detail', 'label' => 'Event detail'],
            ['key' => 'find', 'label' => 'Find'],
            ['key' => 'sports', 'label' => 'Sports'],
            ['key' => 'following', 'label' => 'Following'],
            ['key' => 'activity', 'label' => 'Activity'],
            ['key' => 'profile', 'label' => 'Profile'],
            ['key' => 'organiser', 'label' => 'Organiser'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function matches(): array
    {
        return Event::query()
            ->upcoming()
            ->with(['venue', 'disciplines', 'hostOrganisation'])
            ->orderBy('starts_at')
            ->limit(12)
            ->get()
            ->map(function (Event $event): array {
                $discipline = $event->primaryDiscipline() ?? $event->disciplines->first();

                return [
                    'slug' => $event->slug,
                    'title' => $event->title,
                    'dow' => EventDate::weekday($event->starts_at, $event->ends_at),
                    'day' => EventDate::dayOfMonth($event->starts_at, $event->ends_at),
                    'month' => EventDate::monthWithYear($event->starts_at, $event->ends_at),
                    'discipline' => $discipline?->name,
                    'host' => $event->listedHost(),
                    'place' => $event->listedLocation(),
                    'venue' => $event->venue?->name,
                    'fee' => Money::rand($event->entry_fee_cents),
                    'entry' => filled($event->entry_url),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function clubs(): array
    {
        return Organisation::query()
            ->published()
            ->clubs()
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Organisation $club): array => [
                'slug' => $club->slug,
                'name' => $club->name,
                'place' => collect([$club->town, $club->province?->getLabel()])->filter()->implode(' · '),
                'type' => $club->type?->getLabel() ?? 'Club',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ranges(): array
    {
        return Venue::query()
            ->published()
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Venue $venue): array => [
                'slug' => $venue->slug,
                'name' => $venue->name,
                'place' => collect([$venue->town, $venue->province?->getLabel()])->filter()->implode(' · '),
                'distance' => $venue->max_distance_m ? $venue->max_distance_m.' m' : null,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sports(): array
    {
        return Discipline::query()
            ->orderBy('name')
            ->limit(12)
            ->get()
            ->map(fn (Discipline $sport): array => [
                'slug' => $sport->slug,
                'name' => $sport->name,
                'family' => $sport->family?->getLabel(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function suppliers(): array
    {
        return Provider::query()
            ->published()
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Provider $provider): array => [
                'slug' => $provider->slug,
                'name' => $provider->name,
                'category' => $provider->category?->getLabel(),
                'place' => collect([$provider->town, $provider->province?->getLabel()])->filter()->implode(' · '),
            ])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, mixed>|null
     */
    private function find(array $rows, string $slug): ?array
    {
        if ($slug === '') {
            return null;
        }

        foreach ($rows as $row) {
            if (($row['slug'] ?? null) === $slug) {
                return $row;
            }
        }

        return null;
    }
}
