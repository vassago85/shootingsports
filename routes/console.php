<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('calendar:import saprf')->weeklyOn(1, '06:00');
Schedule::command('calendar:import mpsa')->weeklyOn(1, '06:20');
Schedule::command('calendar:import cgpsa')->weeklyOn(1, '06:40');
Schedule::command('calendar:import vektor')->weeklyOn(1, '07:00');

// Pro-trial nudges: T-3 "ending soon" + T+0 "ended". Idempotent and
// safe to re-run — see SendProTrialNudges docblock. 07:20 SAST so
// mail lands in the working-day inbox, not overnight.
Schedule::command('pro:trial-nudges')->dailyAt('07:20');
