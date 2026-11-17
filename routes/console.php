<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Automatically purge job circulars whose deadline passed more than 60 days ago.
 * Runs every day at midnight (00:00).
 */
Schedule::command('circulars:prune')->dailyAt('00:00');

/**
 * Automatically synchronize fresh circulars (Govt, Bank, Corporate, Remote)
 * Runs twice a day (morning 06:00 and evening 18:00).
 */
Schedule::command('circulars:sync')->twiceDaily(6, 18);
