<?php

/*
|--------------------------------------------------------------------------
| Dedicated-status tracker
|--------------------------------------------------------------------------
|
| South-African accrediting bodies renew dedicated status annually.
| Activities for the cycle are normally submitted at end of October,
| so the shipped default cycle runs 1 November through 31 October.
|
| Everything that would otherwise be hardcoded in User / Livewire /
| the printable report lives here so a body-specific rule change (or
| a staff tweak after the 2026 round) is a one-file edit, not a
| migration + redeploy.
*/

return [

    /*
    |----------------------------------------------------------------------
    | Cycle end
    |----------------------------------------------------------------------
    |
    | The month (1-12) and day (1-31) on which the current activity
    | cycle ends. The cycle START is derived as "the day AFTER the
    | end, one year earlier". With the defaults below, that's
    | 1 November (previous year) through 31 October (current year).
    */

    'cycle_end' => [
        'month' => (int) env('DEDICATED_CYCLE_END_MONTH', 10),
        'day' => (int) env('DEDICATED_CYCLE_END_DAY', 31),
    ],

    /*
    |----------------------------------------------------------------------
    | Accrediting bodies
    |----------------------------------------------------------------------
    |
    | Keyed by storage code → human label. NRAPA is first by design
    | (default selection for new users). Adding a body here is enough
    | to expose it in the settings dropdown; no code change needed.
    */

    'bodies' => [
        'nrapa' => 'NRAPA',
        'natshoot' => 'NATSHOOT',
        'fosa' => 'FOSA',
    ],

    /*
    |----------------------------------------------------------------------
    | Dedicated-status categories
    |----------------------------------------------------------------------
    |
    | What the shooter holds dedicated status FOR. Rendered on the
    | printable licence-supporting document so the DFO sees whether
    | the holder is a sport shooter, a hunter, or both.
    */

    'categories' => [
        'sport_shooter' => 'Sport shooter',
        'hunter' => 'Hunter',
        'both' => 'Both',
    ],

    /*
    |----------------------------------------------------------------------
    | Required count
    |----------------------------------------------------------------------
    |
    | Minimum activity-counting matches per cycle. The regulation
    | floor is two per year — some bodies ask for more, which is
    | why the user can override on their settings page. Validation
    | enforces this as the lower bound.
    */

    'default_required_count' => 2,
    'minimum_required_count' => 2,

    /*
    |----------------------------------------------------------------------
    | Reminder schedule
    |----------------------------------------------------------------------
    |
    | Days BEFORE the cycle end on which to send a cycle-progress
    | reminder email. The defaults map to 1 September (60), 1 October
    | (30) and 24 October (7) when the cycle ends 31 October. The
    | daily artisan command compares today's days-remaining against
    | this list and fires one mail per hit, idempotently.
    */

    'reminder_days_before' => [60, 30, 7],

];
