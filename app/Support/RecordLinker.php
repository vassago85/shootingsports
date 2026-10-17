<?php

namespace App\Support;

use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Organisations\OrganisationResource;
use App\Filament\Resources\Providers\ProviderResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Model;

/**
 * Collapses the polymorphic "{type} / {id}" pair used by Claim,
 * Submission and Media into a single human-readable label + a
 * deep link into the related Filament resource. Anything we do
 * not have a resource for falls back to a plain "{type} #{id}".
 */
class RecordLinker
{
    public static function label(?Model $record): string
    {
        if ($record === null) {
            return 'Deleted record';
        }

        $name = self::preferredName($record);
        $type = self::typeLabel($record);

        if ($name === null) {
            return sprintf('%s #%d', $type, $record->getKey());
        }

        return sprintf('%s · %s', $type, $name);
    }

    public static function adminUrl(?Model $record): ?string
    {
        if ($record === null) {
            return null;
        }

        return match (true) {
            $record instanceof Event => EventResource::getUrl('edit', ['record' => $record]),
            $record instanceof Organisation => OrganisationResource::getUrl('edit', ['record' => $record]),
            $record instanceof Venue => VenueResource::getUrl('edit', ['record' => $record]),
            $record instanceof Provider => ProviderResource::getUrl('edit', ['record' => $record]),
            default => null,
        };
    }

    private static function preferredName(Model $record): ?string
    {
        foreach (['name', 'title'] as $attribute) {
            $value = $record->getAttribute($attribute);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function typeLabel(Model $record): string
    {
        return match (true) {
            $record instanceof Event => 'Event',
            $record instanceof Organisation => 'Club',
            $record instanceof Venue => 'Range',
            $record instanceof Provider => 'Industry',
            default => class_basename($record),
        };
    }
}
