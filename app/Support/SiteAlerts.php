<?php

namespace App\Support;

use App\Mail\SiteError;
use Illuminate\Support\Facades\Cache;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Tells the studio when the website breaks: an e-mail for a server error,
 * through the same mailbox and to the same people as the enquiry alerts
 * (StudioMail, admin → Enquiry alerts).
 *
 * Quietly: the same error sends one mail in half an hour however often it
 * happens, and no more than six go out in an hour in all — a bad moment is
 * one or two messages, not a flood. Sent after the visitor has their page,
 * and never itself the cause of another error.
 */
class SiteAlerts
{
    private const QUIET_MINUTES = 30;

    private const PER_HOUR = 6;

    public static function error(Throwable $e): void
    {
        try {
            if (app()->runningUnitTests() || ! StudioMail::enabled()) {
                return;
            }
            $key = 'site-alert:'.md5(get_class($e).'|'.$e->getMessage().'|'.$e->getFile().'|'.$e->getLine());
            if (! Cache::add($key, 1, now()->addMinutes(self::QUIET_MINUTES))) {
                return;
            }
            $hour = 'site-alerts:'.now()->format('YmdH');
            Cache::add($hour, 0, now()->addHour());
            if (Cache::increment($hour) > self::PER_HOUR) {
                return;
            }

            $request = app()->runningInConsole() ? null : request();
            $mail = new SiteError(
                error: mb_substr($e->getMessage() ?: get_class($e), 0, 500),
                type: get_class($e),
                where: str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile()).':'.$e->getLine(),
                url: $request?->fullUrl(),
                method: $request?->method(),
                referrer: $request?->headers->get('referer'),
                agent: $request ? mb_substr((string) $request->userAgent(), 0, 200) : 'command line (deploy, scheduled task)',
                at: now()->format('Y-m-d H:i:s'),
            );
            defer(fn () => StudioMail::send($mail));
        } catch (Throwable) {
            // an alert that cannot go out must not break anything else
        }
    }
}
