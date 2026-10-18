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

// Club membership lapse + renewal reminders. Both are idempotent and
// safe to re-run — see LapseExpiredClubMembers and
// SendClubMembershipRenewalReminders docblocks. 07:30 SAST so they
// land in the working-day inbox, after the Pro nudges.
Schedule::command('club-members:lapse')->dailyAt('07:30');
Schedule::command('club-members:send-renewal-reminders')->dailyAt('07:35');

// Platform billing: trial-ending reminders at T-5 / T-1 / T+0 for org admins and secretaries.
Schedule::command('subscriptions:notify-trial-ending')->dailyAt('09:00');

// Platform billing: compile last month's fee statements from paid membership invoices. Idempotent.
Schedule::command('platform:compile-monthly-fee-statements')->monthlyOn(1, '03:00');

// Platform billing: recount active members, stamp/clear tier grace windows, auto-upgrade expired overages.
Schedule::command('platform:enforce-tier-overages')->dailyAt('03:00');
