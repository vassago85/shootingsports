<?php

namespace App\Services\ImpactScoring;

use App\Enums\EntryStatus;
use App\Models\Event;
use App\Models\EventResult;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Pull an Impact match URL into this site's results table.
 * Names that match an entered shooter are linked to that account.
 */
class ImpactResultsImporter
{
    public function __construct(
        private readonly ImpactScoringClient $client,
    ) {}

    /**
     * @return array{name: string, count: int}
     */
    public function import(Event $event, string $impactRef, bool $replaceExisting = true, ?int $disciplineId = null): array
    {
        $disciplineId ??= $event->primaryDiscipline()?->id
            ?? $event->disciplines()->orderBy('disciplines.id')->value('disciplines.id');

        if ($disciplineId === null) {
            throw new RuntimeException('Add a sport to this match before importing results.');
        }

        $live = $this->client->getLiveScores($this->client->parseMatchId($impactRef));
        $rows = $this->rows($event, $live['shooters'], (int) $disciplineId);

        if ($rows === []) {
            throw new RuntimeException('That Impact match has no shooters to import.');
        }

        $written = DB::transaction(function () use ($event, $rows, $replaceExisting): int {
            if ($replaceExisting) {
                $event->results()->delete();
            }

            $existing = $replaceExisting
                ? []
                : $event->results()->pluck('display_name')->map(fn (string $name): string => mb_strtolower($name))->all();

            $count = 0;

            foreach ($rows as $row) {
                if (! $replaceExisting && in_array(mb_strtolower($row['display_name']), $existing, true)) {
                    continue;
                }

                EventResult::query()->create($row);
                $count++;
            }

            return $count;
        });

        return [
            'name' => (string) ($live['match']['name'] ?? 'Impact match'),
            'count' => $written,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $shooters
     * @return list<array<string, mixed>>
     */
    private function rows(Event $event, array $shooters, int $disciplineId): array
    {
        $named = [];

        foreach ($shooters as $shooter) {
            $name = trim((string) ($shooter['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $named[] = $shooter;
        }

        $fieldByDivision = [];

        foreach ($named as $shooter) {
            $division = trim((string) ($shooter['divisionName'] ?? ''));
            $fieldByDivision[$division] = ($fieldByDivision[$division] ?? 0) + 1;
        }

        $linkedUsers = $event->entries()
            ->where('status', EntryStatus::Entered)
            ->with('user:id,name')
            ->get()
            ->mapWithKeys(function ($entry): array {
                $name = mb_strtolower(trim((string) $entry->user?->name));

                return $name === '' ? [] : [$name => $entry->user_id];
            });

        $rows = [];

        foreach ($named as $shooter) {
            $name = trim((string) $shooter['name']);
            $division = trim((string) ($shooter['divisionName'] ?? ''));
            $rank = $shooter['rank'] ?? null;

            $rows[] = [
                'event_id' => $event->id,
                'user_id' => $linkedUsers[mb_strtolower($name)] ?? null,
                'display_name' => $name,
                'discipline_id' => $disciplineId,
                'division' => $division !== '' ? $division : null,
                'placing' => is_numeric($rank) ? (int) $rank : null,
                'field_size' => $fieldByDivision[$division] ?? count($named),
                'score' => $this->score((int) ($shooter['points'] ?? 0)),
            ];
        }

        return $rows;
    }

    private function score(int $points): string
    {
        $impacts = $points / 100;

        return rtrim(rtrim(number_format($impacts, 2, '.', ''), '0'), '.');
    }
}
