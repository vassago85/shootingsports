<?php

namespace App\Support;

use App\Enums\DisciplineFamily;
use App\Models\Discipline;
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

        $facts = PublicEventFacts::for($event);

        $rows = [
            ['Discipline', $discipline?->name],
            ['Venue', $facts->venueLine()],
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
            $rows[] = ['Distance', self::distance($event, $discipline)];
            $rows[] = ['Rounds', self::number($event->round_count)];
        }

        foreach ($facts->priceRows() as $priceRow) {
            $rows[] = $priceRow;
        }

        if ($event->capacity) {
            $rows[] = ['Squads', ($event->entries_taken ?? 0).' / '.$event->capacity];
        }

        return collect($rows)
            ->filter(fn (array $row): bool => filled($row[1]))
            ->map(fn (array $row): array => [$row[0], (string) $row[1]])
            ->values()
            ->all();
    }

    /**
     * A more specific child discipline wins over the parent's range.
     * .22 LR ELR is 50–500 m, not the centerfire ELR mile-plus range.
     */
    public static function distance(Event $event, ?Discipline $discipline = null): ?string
    {
        $event->loadMissing('disciplines');
        $discipline ??= $event->primaryDiscipline() ?? $event->disciplines->first();

        $specific = $event->disciplines
            ->first(fn (Discipline $row): bool => $row->parent_id !== null && filled($row->typical_distances));

        $value = $specific?->typical_distances ?? $discipline?->typical_distances;

        return filled($value) ? $value : null;
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
