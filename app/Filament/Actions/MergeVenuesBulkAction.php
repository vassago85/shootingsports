<?php

namespace App\Filament\Actions;

use App\Models\Venue;
use App\Services\Venues\VenueMerger;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class MergeVenuesBulkAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('mergeVenues')
            ->label('Merge into…')
            ->icon('heroicon-o-arrows-pointing-in')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Merge selected venues')
            ->modalDescription('Events move to the primary venue. The other selected venues are archived.')
            ->form(fn (Collection $records): array => [
                Select::make('primary_id')
                    ->label('Primary venue (keep)')
                    ->options($records
                        ->mapWithKeys(fn (Venue $v): array => [$v->id => $v->name.' ('.$v->town.')'])
                        ->all())
                    ->required()
                    ->native(false),
            ])
            ->action(function (Collection $records, array $data): void {
                /** @var Venue|null $primary */
                $primary = $records->firstWhere('id', (int) $data['primary_id']);

                if (! $primary instanceof Venue) {
                    Notification::make()->title('Primary venue missing')->danger()->send();

                    return;
                }

                $losers = $records->filter(fn (Venue $v): bool => $v->id !== $primary->id);

                try {
                    $result = app(VenueMerger::class)->merge($primary, $losers);

                    Notification::make()
                        ->title('Venues merged')
                        ->body("{$result['events']} events moved, {$result['archived']} venues archived.")
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Merge failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            })
            ->deselectRecordsAfterCompletion();
    }
}
