<?php

use App\Support\CalendarImportSettings;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Federation calendars: staff set each source's frequency on the
// Calendar imports admin page; `when` reads it at run time.
foreach (CalendarImportSettings::sources() as $position => $source) {
    Schedule::command('calendar:import '.$source['key'])
        ->dailyAt(CalendarImportSettings::runTime($position))
        ->when(fn (): bool => CalendarImportSettings::isDueOn($source['key'], now()));
}

// Pro-trial nudges: T-3 "ending soon" + T+0 "ended". Idempotent and
// safe to re-run — see SendProTrialNudges docblock. 07:20 SAST so
// mail lands in the working-day inbox, not overnight.
Schedule::command('pro:trial-nudges')->dailyAt('07:20');
