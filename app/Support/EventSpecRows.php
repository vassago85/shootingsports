<?php

namespace App\Support;

use App\Enums\DisciplineFamily;
use App\Models\Event;

class EventSpecRows
{
    /**
     * Spec rows differ by discipline. Empty values are omitted.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function for(Event $event): array
    {
        $discipline = $event->primaryDiscipline() ?? $event->disciplines->first();
        $family = $discipline?->family;
        $slug = $discipline?->slug ?? '';

        $rows = [
            ['Discipline', $discipline?->name],
            ['Venue', $event->locationLabel()],
        ];

        if (self::isShotgun($family, $slug)) {
            $rows[] = ['Targets', self::number($event->target_count)];
            $rows[] = ['Stands', self::number($event->stage_count)];
        } elseif (self::isPractical($family, $slug)) {
            $rows[] = ['Stages', self::number($event->stage_count)];
            $rows[] = ['Min rounds', self::number($event->round_count)];
        } elseif (self::isFieldTarget($slug)) {
            $rows[] = ['Lanes', self::number($event->target_count ?? $event->stage_count)];
        } else {
            $rows[] = ['Distance', $discipline?->typical_distances];
            $rows[] = ['Rounds', self::number($event->round_count)];
        }

        $rows[] = ['Entry', Money::rand($event->entry_fee_cents)];

        if ($event->capacity) {
            $rows[] = ['Squads', ($event->entries_taken ?? 0).' / '.$event->capacity];
        }

        return collect($rows)
            ->filter(fn (array $row): bool => filled($row[1]))
            ->map(fn (array $row): array => [$row[0], (string) $row[1]])
            ->values()
            ->all();
    }

    private static function isShotgun(?DisciplineFamily $family, string $slug): bool
    {
        return $family === DisciplineFamily::Shotgun
            || in_array($slug, ['trap', 'skeet', 'sporting-clays', 'compak-five-stand', 'wingshooting'], true);
    }

    private static function isPractical(?DisciplineFamily $family, string $slug): bool
    {
        return str_contains($slug, 'ipsc')
            || str_contains($slug, 'practical')
            || ($family === DisciplineFamily::Handgun && str_contains($slug, 'steel-challenge'));
    }

    private static function isFieldTarget(string $slug): bool
    {
        return str_contains($slug, 'field-target');
    }

    private static function number(int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
