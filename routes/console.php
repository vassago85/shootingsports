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

// Dedicated-status cycle reminders: fires daily, but the command
// itself only emails on the configured "days before cycle end"
// days (default 60, 30 and 7 — i.e. 1 Sep, 1 Oct, 24 Oct when the
// cycle ends 31 Oct). Idempotent per cycle end.
Schedule::command('dedicated:send-reminders')->dailyAt('07:40');

// Answer pages, /llms-full.txt and sitemap lastmod share one daily
// stamp. IndexNow pings the landing URLs when the integration is on.
Schedule::command('answers:refresh')->dailyAt('05:10')->withoutOverlapping();

// Venue, title, and import-source problems. Output is also written
// to the log for whoever is watching the scheduler.
Schedule::command('data-quality:report')->dailyAt('05:40')->withoutOverlapping();
