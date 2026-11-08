<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The weekly report (App\Support\WeeklyReport): sent on Sunday morning by the
// site itself after a visit; this is the same by hand (--now: last week's
// report straight away, whether or not it has been sent).
Artisan::command('report:weekly {--now : Send last week\'s report now, even if it has gone out}', function () {
    if ($this->option('now')) {
        [$start, $end] = \App\Support\WeeklyReport::lastWeek();
        $error = \App\Support\WeeklyReport::send($start, $end);
        $error ? $this->error($error) : $this->info('Weekly report sent to: '.implode(', ', \App\Support\StudioMail::recipients()));

        return;
    }
    \App\Support\WeeklyReport::isDue() ? \App\Support\WeeklyReport::sendIfDue() : $this->info('Not due — last week\'s report has gone out.');
})->purpose('Send the weekly website report by e-mail');

\Illuminate\Support\Facades\Schedule::command('report:weekly')->weeklyOn(0, '8:15')->timezone('Asia/Baghdad');