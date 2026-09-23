<?php

namespace App\Http\Controllers;

use App\Enums\EmailCategory;
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
        $allowed = collect($journey)->flatMap(fn (array $group): array => array_column($group['screens'], 'key'))->all();
        $screen = $request->string('screen')->toString();

        if (! in_array($screen, $allowed, true)) {
            $screen = 'login';
        }

        $matches = $this->matches();
        $clubs = $this->clubs();
        $ranges = $this->ranges();
        $sports = $this->sports();
        $suppliers = $this->suppliers();

        $slug = $request->string('slug')->toString();

        return view('mockups.apps', [
            'screen' => $screen,
            'journey' => $journey,
            'matches' => $matches,
            'match' => $this->find($matches, $slug) ?? ($matches[0] ?? null),
            'clubs' => $clubs,
            'club' => $this->find($clubs, $slug) ?? ($clubs[0] ?? null),
            'ranges' => $ranges,
            'range' => $this->find($ranges, $slug) ?? ($ranges[0] ?? null),
            'sports' => $sports,
            'sport' => $this->find($sports, $slug) ?? ($sports[0] ?? null),
            'suppliers' => $suppliers,
            'supplier' => $this->find($suppliers, $slug) ?? ($suppliers[0] ?? null),
            'emailCategories' => collect(EmailCategory::cases())
                ->reject(fn (EmailCategory $category): bool => $category === EmailCategory::Transactional)
                ->all(),
        ]);
    }

    /**
     * @return array<int, array{group: string, screens: array<int, array{key: string, label: string}>}>
     */
    private function journey(): array
    {
        return [
            ['group' => '01 · Sign in', 'screens' => [
                ['key' => 'login', 'label' => 'Log in'],
                ['key' => 'register', 'label' => 'Create account'],
            ]],
            ['group' => '02 · Shooting', 'screens' => [
                ['key' => 'home', 'label' => 'Home'],
                ['key' => 'matches', 'label' => 'Matches'],
                ['key' => 'match', 'label' => 'Match'],
                ['key' => 'calendar', 'label' => 'Calendar'],
                ['key' => 'map', 'label' => 'Map'],
            ]],
            ['group' => '03 · Your account', 'screens' => [
                ['key' => 'my-calendar', 'label' => 'My calendar'],
                ['key' => 'log', 'label' => 'My log'],
                ['key' => 'notifications', 'label' => 'Notifications'],
                ['key' => 'upgrade', 'label' => 'Go Pro'],
                ['key' => 'you', 'label' => 'You'],
            ]],
            ['group' => '04 · Find', 'screens' => [
                ['key' => 'find', 'label' => 'Find'],
                ['key' => 'clubs', 'label' => 'Clubs'],
                ['key' => 'club', 'label' => 'Club'],
                ['key' => 'ranges', 'label' => 'Ranges'],
                ['key' => 'range', 'label' => 'Range'],
                ['key' => 'sports', 'label' => 'Sports'],
                ['key' => 'sport', 'label' => 'Sport'],
                ['key' => 'industry', 'label' => 'Industry'],
                ['key' => 'supplier', 'label' => 'Supplier'],
            ]],
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
