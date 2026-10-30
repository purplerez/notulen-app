<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Worker antrean untuk shared hosting (berjalan tiap menit, berhenti saat antrean kosong, max 50 detik)
Schedule::command('queue:work database --queue=stt,default --stop-when-empty --max-time=50')
    ->everyMinute()
    ->withoutOverlapping();

// Daily retention scheduled tasks
Schedule::command('audio:purge')->dailyAt('02:00');
Schedule::command('audio:remind')->dailyAt('08:00');
