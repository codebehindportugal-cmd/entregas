<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('orders:sync')
    ->everyFifteenMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:work --stop-when-empty --tries=2 --timeout=180')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('site:health-check')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('site:backup')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('site:security-scan')
    ->weeklyOn(1, '03:30')
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('site:update-check')
    ->weeklyOn(1, '04:00')
    ->withoutOverlapping()
    ->runInBackground();
