<?php

namespace App\Filament\Concerns;

use App\Enums\Division;
use App\Models\Discipline;
use App\Support\YouTube;

trait SyncsDisciplineExtras
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillDivisionExtras(array $data): array
    {
        /** @var Discipline $record */
        $record = $this->record;

        $data['division_values'] = $record->divisionLinks()
            ->pluck('division')
            ->map(fn (Division $division): string => $division->value)
            ->all();

        $data['videos'] = $record->videos()
            ->get(['title', 'url'])
            ->map(fn ($video): array => [
                'title' => $video->title,
                'url' => $video->url,
            ])
            ->all();

        return $data;
    }

    protected function persistDivisionExtras(): void
    {
        /** @var Discipline $record */
        $record = $this->record;
        $state = $this->form->getState();

        $divisions = [];

        foreach ($state['division_values'] ?? [] as $value) {
            $division = Division::tryFrom((string) $value);

            if ($division !== null) {
                $divisions[] = $division;
            }
        }

        $record->syncDivisions($divisions);

        $record->videos()->delete();

        foreach (array_values($state['videos'] ?? []) as $index => $video) {
            if ($index >= 4 || ! is_array($video)) {
                break;
            }

            $url = is_string($video['url'] ?? null) ? $video['url'] : '';
            $videoId = YouTube::idFromUrl($url);

            if ($videoId === null || ! is_string($video['title'] ?? null) || $video['title'] === '') {
                continue;
            }

            $record->videos()->create([
                'title' => $video['title'],
                'url' => $url,
                'video_id' => $videoId,
                'sort_order' => $index,
            ]);
        }
    }
}
