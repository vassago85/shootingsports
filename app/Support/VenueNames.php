<?php

namespace App\Support;

/**
 * A calendar cell sometimes names a timetable ("Day 1 - Papaberg / Day 2 - Legends")
 * rather than one range. Those strings must not become directory listings.
 */
class VenueNames
{
    public static function isScheduleFragment(string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        if (str_contains($name, '/')) {
            return true;
        }

        return preg_match('/\bday\s*\d+\b/i', $name) === 1;
    }
}
