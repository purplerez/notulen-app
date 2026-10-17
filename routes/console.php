<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily retention scheduled tasks
Schedule::command('audio:purge')->dailyAt('02:00');
Schedule::command('audio:remind')->dailyAt('08:00');
