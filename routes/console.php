<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reviews:remind')->hourly();
Schedule::command('posts:publish-due')->everyMinute();
Schedule::command('accounts:check-tokens')->dailyAt('08:00');
Schedule::command('comments:fetch')->hourly();
Schedule::command('posts:insights')->dailyAt('06:00');
Schedule::command('reports:monthly')->monthlyOn(1, '07:00');
