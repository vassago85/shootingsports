<?php

namespace App\Support;

use App\Models\Event;
use App\Models\Organisation;
use App\Models\Provider;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Builder;

/**
 * Decides which public records are worth an index.
 *
 * A directory URL with a name and nothing else competes with the
 * landing pages that should rank. Location is required. Clubs, ranges
 * and suppliers also need a discipline, a contact channel, or a real
 * fixture. A match is itself the fixture, so a start time plus a host
 * or a range is enough.
 */
class Indexability
{
    public static function allows(Organisation|Venue|Event|Provider $record): bool
    {
        $query = match (true) {
            $record instanceof Organisation => self::organisations($record->newQuery()),
            $record instanceof Venue => self::venues($record->newQuery()),
            $record instanceof Event => self::events($record->newQuery()),
            $record instanceof Provider => self::providers($record->newQuery()),
        };

        return $query->whereKey($record->getKey())->exists();
    }

    /**
     * @param  Builder<Organisation>  $query
     * @return Builder<Organisation>
     */
    public static function organisations(Builder $query): Builder
    {
        return $query->published()
            ->where(function (Builder $located): void {
                $located->whereNotNull('province')
                    ->orWhere(function (Builder $town): void {
                        $town->whereNotNull('town')->where('town', '!=', '');
                    });
            })
            ->where(function (Builder $substance): void {
                $substance->whereHas('disciplines')
                    ->orWhere(fn (Builder $email) => self::filled($email, 'email'))
                    ->orWhere(fn (Builder $phone) => self::filled($phone, 'phone'))
                    ->orWhere(fn (Builder $web) => self::filled($web, 'website_url'))
                    ->orWhereHas('hostedEvents', function (Builder $events): void {
                        $events->published()->where('starts_at', '>=', now());
                    });
            });
    }

    /**
     * @param  Builder<Venue>  $query
     * @return Builder<Venue>
     */
    public static function venues(Builder $query): Builder
    {
        return $query->published()
            ->where(function (Builder $located): void {
                $located->whereNotNull('province')
                    ->whereNotNull('town')
                    ->where('town', '!=', '');
            })
            ->where(function (Builder $substance): void {
                $substance->where(fn (Builder $address) => self::filled($address, 'address'))
                    ->orWhereNotNull('max_distance_m')
                    ->orWhere(fn (Builder $notes) => self::filled($notes, 'notes'))
                    ->orWhereHas('disciplines')
                    ->orWhereHas('events', fn (Builder $events) => $events->published());
            });
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public static function events(Builder $query): Builder
    {
        return $query->published()
            ->where(function (Builder $place): void {
                $place->whereNotNull('venue_id')
                    ->orWhereNotNull('host_organisation_id');
            });
    }

    /**
     * @param  Builder<Provider>  $query
     * @return Builder<Provider>
     */
    public static function providers(Builder $query): Builder
    {
        return $query->published()
            ->whereNotNull('province')
            ->whereNotNull('town')
            ->where('town', '!=', '')
            ->where(function (Builder $substance): void {
                $substance->where(fn (Builder $email) => self::filled($email, 'email'))
                    ->orWhere(fn (Builder $phone) => self::filled($phone, 'phone'))
                    ->orWhere(fn (Builder $web) => self::filled($web, 'website_url'))
                    ->orWhere(fn (Builder $about) => self::filled($about, 'description'))
                    ->orWhereHas('disciplines');
            });
    }

    /**
     * @param  Builder<Organisation>|Builder<Venue>|Builder<Provider>  $query
     * @return Builder<Organisation>|Builder<Venue>|Builder<Provider>
     */
    private static function filled(Builder $query, string $column): Builder
    {
        return $query->whereNotNull($column)->where($column, '!=', '');
    }
}
