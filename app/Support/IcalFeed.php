<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IcalFeed
{
    /**
     * @param  Collection<int, Event>|iterable<Event>  $events
     */
    public static function build(string $name, iterable $events): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Shooting Sports//Register//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.self::escape($name),
            'X-WR-TIMEZONE:Africa/Johannesburg',
        ];

        foreach ($events as $event) {
            $event->loadMissing(['hostOrganisation', 'venue']);

            $uid = 'event-'.$event->id.'@shootingsports.co.za';
            $stamp = now()->utc()->format('Ymd\THis\Z');
            $summary = self::escape($event->title);
            $description = self::escape(trim(implode(' — ', array_filter([
                $event->hostOrganisation?->name,
                $event->status?->getLabel(),
            ]))));
            $location = self::escape($event->venue?->name ?: $event->locationLabel());

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$uid;
            $lines[] = 'DTSTAMP:'.$stamp;

            if ($event->all_day) {
                $lines[] = 'DTSTART;VALUE=DATE:'.$event->starts_at->timezone('Africa/Johannesburg')->format('Ymd');

                if ($event->ends_at) {
                    $lines[] = 'DTEND;VALUE=DATE:'.$event->ends_at->timezone('Africa/Johannesburg')->addDay()->format('Ymd');
                }
            } else {
                $lines[] = 'DTSTART:'.$event->starts_at->timezone('Africa/Johannesburg')->format('Ymd\THis');

                if ($event->ends_at) {
                    $lines[] = 'DTEND:'.$event->ends_at->timezone('Africa/Johannesburg')->format('Ymd\THis');
                }
            }

            $lines[] = 'SUMMARY:'.$summary;
            $lines[] = 'DESCRIPTION:'.$description;
            $lines[] = 'LOCATION:'.$location;
            $lines[] = 'URL:'.$event->publicUrl();
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    private static function escape(string $value): string
    {
        return Str::of($value)
            ->replace(['\\', ';', ',', "\n"], ['\\\\', '\;', '\,', '\\n'])
            ->toString();
    }
}
