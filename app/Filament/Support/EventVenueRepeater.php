<?php

namespace App\Filament\Support;

use App\Models\Event;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

/**
 * Repeater for additional venues on a match ("Day 2 — Legends
 * Adventure Farm", "Rifle stages — Papaberg"). The primary venue
 * lives on the top-level `venue_id` select and is stored on
 * `events.venue_id` for back-compat; this repeater covers everything
 * beyond that.
 *
 * On save, the controlling page trait `SyncsEventVenues` combines the
 * primary + extras into `Event::syncVenues()`.
 */
class EventVenueRepeater
{
    public static function make(): Repeater
    {
        return Repeater::make('additional_venues')
            ->label('Additional venues (multi-day)')
            ->helperText('Only fill this in for matches that span more than one range. The primary venue above is Day 1.')
            ->addActionLabel('Add another venue')
            ->columnSpanFull()
            ->defaultItems(0)
            ->reorderable(true)
            ->orderColumn('sort_order')
            ->schema([
                Select::make('venue_id')
                    ->label('Range')
                    ->relationship('venue', 'name')
                    ->options(fn () => \App\Models\Venue::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                TextInput::make('day_label')
                    ->label('Day / segment label')
                    ->placeholder('e.g. Day 2'),
                DatePicker::make('starts_on')
                    ->label('Date')
                    ->native(false),
            ])
            ->columns(3)
            ->dehydrated(false);
    }

    /**
     * Load the pivot into the form as `additional_venues`. The
     * primary venue is excluded — it lives on the top-level select.
     *
     * @return list<array{venue_id: int, day_label: ?string, starts_on: ?string}>
     */
    public static function fillFromEvent(Event $event): array
    {
        $event->loadMissing('venues');

        return $event->venues
            ->reject(fn ($v) => $v->id === $event->venue_id)
            ->map(fn ($v): array => [
                'venue_id' => $v->id,
                'day_label' => $v->pivot?->day_label,
                'starts_on' => $v->pivot?->starts_on,
            ])
            ->values()
            ->all();
    }

    /**
     * Sync the pivot with primary + repeater rows.
     *
     * @param  list<array{venue_id: int|string, day_label?: string|null, starts_on?: string|\DateTimeInterface|null}>  $extras
     */
    public static function sync(Event $event, ?int $primaryVenueId, array $extras): void
    {
        $rows = [];

        if ($primaryVenueId) {
            $rows[] = [
                'venue_id' => (int) $primaryVenueId,
                'day_label' => null,
                'starts_on' => null,
            ];
        }

        foreach ($extras as $row) {
            $venueId = (int) ($row['venue_id'] ?? 0);

            if ($venueId <= 0) {
                continue;
            }

            $rows[] = [
                'venue_id' => $venueId,
                'day_label' => $row['day_label'] ?? null,
                'starts_on' => $row['starts_on'] ?? null,
            ];
        }

        // If nothing at all, drop the pivot cleanly. `syncVenues` will
        // also null the primary venue_id — matching the "no venues"
        // state used by hostless/venue-TBC matches.
        if ($rows === []) {
            $event->venues()->sync([]);

            return;
        }

        $event->syncVenues($rows);
    }
}
