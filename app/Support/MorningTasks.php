<?php

namespace App\Support;

use App\Mail\MorningTasksMail;
use App\Models\SiteSetting;
use App\Models\Task;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The morning e-mail: what is late and what is due today, from 08:00 Erbil
 * time, once a day — none on a day with nothing to do. The host runs no
 * scheduled jobs, so it goes on the first page view after it falls due, a
 * visitor's (TrackPageView) or the admin's (RemindOfTasks), after the page is sent.
 */
class MorningTasks
{
    public const ENABLED = 'tasks_morning_enabled';

    public const LAST = 'tasks_morning_sent';   // the Erbil day it last went

    /** On unless switched off in the admin (Website settings → Enquiry alerts). */
    public static function enabled(): bool
    {
        return setting(self::ENABLED, '1') !== '0';
    }

    public static function isDue(): bool
    {
        $now = now(Task::TZ);

        return self::enabled() && StudioMail::enabled() && $now->hour >= 8 && setting(self::LAST) !== $now->toDateString();
    }

    /** Never twice a day, never in the visitor's way. */
    public static function sendIfDue(): void
    {
        try {
            Cache::lock('tasks-morning', 300)->get(function () {
                SiteSetting::flushCache();
                if (! self::isDue()) {
                    return;   // another request sent it a moment ago
                }
                // marked first: a failed send is not retried on every page view
                SiteSetting::put(self::LAST, now(Task::TZ)->toDateString(), 'alerts');
                SiteSetting::flushCache();
                self::send();
            });
        } catch (Throwable) {
            // an e-mail must never break a page
        }
    }

    /** Null when sent, or with nothing to send; else why it failed. */
    public static function send(): ?string
    {
        $tasks = Task::query()->open()->where('due_at', '<=', now(Task::TZ)->endOfDay()->utc())
            ->with(['project', 'room.project'])->orderBy('due_at')->get();
        if ($tasks->isEmpty()) {
            return null;
        }
        [$late, $today] = $tasks->partition(fn (Task $t) => $t->isLate());

        return StudioMail::send(new MorningTasksMail($late->values(), $today->values()));
    }
}
