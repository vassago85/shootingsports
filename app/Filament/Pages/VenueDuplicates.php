<?php

namespace App\Filament\Pages;

use App\Models\Venue;
use App\Services\Venues\VenueDuplicateFinder;
use App\Services\Venues\VenueMerger;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Throwable;
use UnitEnum;

class VenueDuplicates extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Directory';

    protected static ?int $navigationSort = 21;

    protected static ?string $navigationLabel = 'Venue duplicates';

    protected static ?string $title = 'Likely venue duplicates';

    protected string $view = 'filament.pages.venue-duplicates';

    /**
     * @return list<array{key: string, venues: Collection<int, Venue>}>
     */
    public function getGroups(): array
    {
        return app(VenueDuplicateFinder::class)->groups();
    }

    public function mergeInto(int $primaryId, int $loserId): void
    {
        $primary = Venue::query()->findOrFail($primaryId);
        $loser = Venue::query()->findOrFail($loserId);

        try {
            $result = app(VenueMerger::class)->merge($primary, [$loser]);

            Notification::make()
                ->title('Merged')
                ->body("{$result['events']} events moved; {$loser->name} archived.")
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Merge failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
