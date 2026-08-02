<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Early enough to be read with the morning coffee, before the day's decisions.
Schedule::command('erp:alerts')->dailyAt('07:00')->withoutOverlapping();

// Yesterday's figures, settled, so the dashboard paints from a snapshot.
Schedule::command('erp:kpi-snapshot')->dailyAt('00:20')->withoutOverlapping();

/*
 * Backups.
 *
 * Ordering matters: take the backup first, prune second, then check health.
 * Running cleanup before the new backup lands would, on the night the backup
 * itself fails, delete old archives and leave nothing at all.
 *
 * `backup:monitor` is the part that actually protects you — it fails loudly
 * when the newest archive is too old or too small, which is how a backup that
 * has been quietly writing zero bytes for a month gets noticed.
 */
Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('03:00');
