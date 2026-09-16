<?php

namespace App\Filament\Resources\Venues\Pages;

use App\Enums\GeocodeSource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Venue;
use App\Services\Geocoding\VenueGeocoder;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditVenue extends EditRecord
{
    protected static string $resource = VenueResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('geocode')
                ->label('Geocode from address')
                ->icon('heroicon-o-map-pin')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Geocode this range?')
                ->modalDescription('Looks up name + town + address via OpenStreetMap Nominatim. Will not run if the pin is staff-locked — clear geocode source first by wiping lat/lng and saving.')
                ->action(function (VenueGeocoder $geocoder): void {
                    /** @var Venue $venue */
                    $venue = $this->record;

                    if ($venue->isStaffPinned()) {
                        Notification::make()
                            ->title('Staff pin locked')
                            ->body('Clear lat/lng and save, or leave the staff pin as-is.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $ok = $geocoder->fill($venue, force: true);

                    if ($ok) {
                        $this->refreshFormData(['lat', 'lng', 'geocode_source', 'geocoded_at']);
                        Notification::make()
                            ->title('Pinned')
                            ->body($venue->fresh()->lat.', '.$venue->fresh()->lng)
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('No result')
                            ->body('Nominatim found nothing usable. Set lat/lng by hand.')
                            ->danger()
                            ->send();
                    }
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;

        if ($lat !== null && $lat !== '' && $lng !== null && $lng !== '') {
            $originalLat = $this->record->lat;
            $originalLng = $this->record->lng;

            if ((string) $lat !== (string) $originalLat || (string) $lng !== (string) $originalLng) {
                $data['geocode_source'] = GeocodeSource::Staff->value;
                $data['geocoded_at'] = now();
            }
        }

        return $data;
    }
}
