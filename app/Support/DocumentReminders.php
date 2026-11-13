<?php

namespace App\Support;

use App\Mail\RoomActivity;
use App\Models\RoomDocument;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * E-mails the studio about each room document expiring within 30 days, once.
 * The host runs no scheduled jobs, so it is checked on the first visit of
 * each day (Erbil time), like the weekly report, after the page is sent.
 */
class DocumentReminders
{
    public const LAST = 'document_reminders_checked';   // the Erbil day of the last check

    public static function isDue(): bool
    {
        return StudioMail::enabled() && setting(self::LAST) !== now('Asia/Baghdad')->toDateString();
    }

    /** Never twice a day, never in the visitor's way. */
    public static function sendIfDue(): void
    {
        try {
            Cache::lock('document-reminders', 300)->get(function () {
                SiteSetting::flushCache();
                if (! self::isDue()) {
                    return;   // another request checked a moment ago
                }
                // marked first: a failed send is not retried on every page view
                SiteSetting::put(self::LAST, now('Asia/Baghdad')->toDateString(), 'alerts');
                SiteSetting::flushCache();
                RoomDocument::query()->dueForReminder()->with('room.project')->get()->each(function (RoomDocument $d) {
                    $d->forceFill(['reminded_at' => now()])->save();
                    StudioMail::send(RoomActivity::expiring($d->room, $d));
                });
            });
        } catch (Throwable) {
            // a reminder must never break a page
        }
    }
}
