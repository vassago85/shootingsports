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
