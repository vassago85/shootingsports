<?php

namespace App\Support;

/**
 * Single source of truth for the public site navigation.
 *
 * Both the desktop bar and the mobile drawer render from the same
 * items() array so any label / route / active-pattern change ripples
 * to both surfaces automatically. Item shape:
 *
 *   array{
 *       label: string,
 *       route: ?string,
 *       active: list<string>,
 *       children?: list<array{label:string, route:string, active:list<string>}>,
 *   }
 */
final class PublicNav
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function items(): array
    {
        return [
            [
                'label' => 'Events',
                'route' => 'calendar',
                'active' => ['calendar', 'calendar.month', 'map', 'events.*'],
            ],
            [
                'label' => 'Sports',
                'route' => 'disciplines.index',
                'active' => ['disciplines.*', 'divisions.*'],
            ],
            [
                'label' => 'Find',
                // Hub label: no direct link, only children. Clubs,
                // ranges and the discipline wizard live under one
                // parent so they stop competing with Events / Sports /
                // Industry as peer nav items.
                'route' => null,
                'active' => ['clubs.*', 'federations.*', 'ranges.*', 'find'],
                'children' => [
                    [
                        'label' => 'Clubs',
                        'route' => 'clubs.index',
                        'active' => ['clubs.*', 'federations.*'],
                    ],
                    [
                        'label' => 'Ranges',
                        'route' => 'ranges.index',
                        'active' => ['ranges.*'],
                    ],
                    [
                        'label' => 'Events near me',
                        'route' => 'map',
                        'active' => ['map'],
                    ],
                    [
                        'label' => 'Which sport fits me',
                        'route' => 'find',
                        'active' => ['find'],
                    ],
                ],
            ],
            [
                'label' => 'Industry',
                'route' => 'suppliers.index',
                'active' => ['suppliers.*'],
            ],
        ];
    }

    /**
     * Whether the current request matches any of the item's active patterns.
     *
     * @param  array<string, mixed>  $item
     */
    public static function isActive(array $item): bool
    {
        $patterns = $item['active'] ?? [];

        return $patterns !== [] && request()->routeIs(...$patterns);
    }
}
