<?php

namespace App\Support;

use App\Mail\WeeklyReportMail;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\SiteSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * The studio's weekly report by e-mail: last week on the website — visitors
 * and how they found it, the projects looked at most, WhatsApp taps,
 * estimates and every enquiry — each figure beside the week before.
 *
 * A week runs Sunday to Saturday, Erbil time. The report is sent from 08:00
 * on Sunday, to the addresses the enquiry alerts go to (StudioMail). The host
 * runs no scheduled jobs, so the website sends it itself: after any page view
 * it checks, at almost no cost, whether last week's report is still due
 * (TrackPageView). `php artisan report:weekly` does the same by hand, and a
 * cron job may call the scheduler where one exists.
 */
class WeeklyReport
{
    public const ENABLED = 'weekly_report_enabled';

    /** The Sunday (Y-m-d) that began the last week reported. */
    public const LAST = 'weekly_report_last_week';

    public const TZ = 'Asia/Baghdad';

    /** On unless switched off in the admin (Website settings → Enquiry alerts). */
    public static function enabled(): bool
    {
        return setting(self::ENABLED, '1') !== '0';
    }

    /**
     * Last full week before $now: [Sunday 00:00, the next Sunday 00:00), Erbil
     * time, as UTC instants for the database.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function lastWeek(?Carbon $now = null): array
    {
        $now = ($now ?? now())->copy()->timezone(self::TZ);
        $end = $now->copy()->startOfDay()->subDays($now->dayOfWeek);   // this Sunday 00:00
        $start = $end->copy()->subDays(7);

        return [$start->utc(), $end->utc()];
    }

    /** True from Sunday 08:00 (Erbil) until last week's report has gone out. */
    public static function isDue(?Carbon $now = null): bool
    {
        if (! self::enabled() || ! StudioMail::enabled()) {
            return false;
        }
        $now = ($now ?? now())->copy()->timezone(self::TZ);
        [, $end] = self::lastWeek($now);
        $sundayErbil = $end->copy()->timezone(self::TZ);
        if ($now->lt($sundayErbil->copy()->addHours(8))) {
            return false;
        }

        return setting(self::LAST) !== $sundayErbil->copy()->subDays(7)->toDateString();
    }

    /** Send last week's report if it is due — never twice, never in the visitor's way. */
    public static function sendIfDue(): void
    {
        try {
            if (! self::isDue()) {
                return;
            }
            Cache::lock('weekly-report', 300)->get(function () {
                SiteSetting::flushCache();
                if (! self::isDue()) {
                    return;   // another request sent it a moment ago
                }
                [$start, $end] = self::lastWeek();
                // marked first: a failed send is not retried on every page view
                SiteSetting::put(self::LAST, $start->copy()->timezone(self::TZ)->toDateString(), 'alerts');
                SiteSetting::flushCache();
                self::send($start, $end);
            });
        } catch (Throwable) {
            // the report must never break a page
        }
    }

    /** Send the report for a window; null on success, else why not. */
    public static function send(Carbon $start, Carbon $end): ?string
    {
        return StudioMail::send(new WeeklyReportMail(self::data($start, $end)));
    }

    /**
     * Everything the report says, for a window and the one before it.
     *
     * @return array<string, mixed>
     */
    public static function data(Carbon $start, Carbon $end): array
    {
        $prevStart = $start->copy()->subDays(7);

        $visitors = fn (Carbon $a, Carbon $b) => PageView::query()->whereBetween('created_at', [$a, $b])->distinct()->count('visitor_hash');
        $views = fn (Carbon $a, Carbon $b) => PageView::query()->whereBetween('created_at', [$a, $b])->count();
        $enquiries = fn (Carbon $a, Carbon $b) => ContactMessage::query()->whereBetween('created_at', [$a, $b]);

        $pages = PageView::query()->whereBetween('created_at', [$start, $end])
            ->selectRaw('path, count(*) as c')->groupBy('path')->orderByDesc('c')->limit(6)->pluck('c', 'path')->all();

        return [
            'from' => $start->copy()->timezone(self::TZ),
            'to' => $end->copy()->timezone(self::TZ)->subDay(),
            'visitors' => [$visitors($start, $end), $visitors($prevStart, $start)],
            'views' => [$views($start, $end), $views($prevStart, $start)],
            'whatsapp' => [Insights::whatsappTaps($start, $end), Insights::whatsappTaps($prevStart, $start)],
            'estimates' => Insights::estimates($start, $end),
            'enquiryList' => $enquiries($start, $end)->with('project')->latest()->get(),
            'enquiriesBefore' => $enquiries($prevStart, $start)->count(),
            'sources' => array_slice(Insights::sources($start, $end), 0, 5),
            'projects' => array_slice(Insights::topProjects($start, $end, 5), 0, 5),
            'pages' => $pages,
            'partners' => self::partners($start, $end),
        ];
    }

    /**
     * The partners visitors did something with this week, busiest first:
     * opened, reached (calls, WhatsApp, website, directions), and arrivals
     * from the partner's own badge. Empty in a quiet week.
     *
     * @return list<array{name: string, open: int, reached: int, badge: int}>
     */
    private static function partners(Carbon $start, Carbon $end): array
    {
        $counts = rescue(fn () => \App\Models\PartnerEvent::countsSince($start, $end), [], false);
        if (! $counts) {
            return [];
        }
        $names = \App\Models\Partner::query()->whereIn('id', array_keys($counts))->pluck('name', 'id');

        return collect($counts)->map(fn ($c, $id) => [
            'name' => (string) ($names[$id] ?? 'Partner #'.$id),
            'open' => $c['open'] ?? 0,
            'reached' => collect(\App\Models\PartnerEvent::CONTACTS)->sum(fn ($t) => $c[$t] ?? 0),
            'badge' => $c['badge'] ?? 0,
        ])->sortByDesc(fn ($p) => $p['reached'] * 3 + $p['open'] + $p['badge'])->take(6)->values()->all();
    }
}
