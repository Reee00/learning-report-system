<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Audit keamanan 2026-09-11: retensi activity log 7 hari.
Schedule::command('activity-logs:purge')->dailyAt('02:17');
