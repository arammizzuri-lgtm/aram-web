<?php

namespace App\Support;

use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\Project;
use App\Models\SiteEvent;
use Illuminate\Support\Carbon;

/**
 * The dashboard's answers to "what is working": where visitors come from and
 * what each source leads to, and which projects draw people in — each for a
 * window of time (DashboardRange).
 */
class Insights
{
    /**
     * One row per source that sent anyone: visitors, WhatsApp taps and
     * enquiries credited to it, busiest first.
     *
     * Enquiries from before sources were recorded have none, and are left
     * out rather than guessed at.
     *
     * @return array<int, array{key: string, label: string, visitors: int, whatsapp: int, enquiries: int}>
     */
    public static function sources(Carbon $start, Carbon $end): array
    {
        $visitors = PageView::query()->whereBetween('created_at', [$start, $end])
            ->whereNotNull('source')->where('source', '!=', TrafficSource::INTERNAL)
            ->selectRaw('source, count(distinct visitor_hash) as c')->groupBy('source')
            ->pluck('c', 'source');

        $whatsapp = SiteEvent::query()->where('type', SiteEvent::WHATSAPP_CLICK)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw("coalesce(source, 'direct') as s, count(*) as c")->groupBy('s')
            ->pluck('c', 's');

        $enquiries = ContactMessage::query()->whereBetween('created_at', [$start, $end])
            ->whereNotNull('source')
            ->selectRaw('source, count(*) as c')->groupBy('source')
            ->pluck('c', 'source');

        return collect(TrafficSource::labels())
            ->map(fn (string $label, string $key) => [
                'key' => $key,
                'label' => $label,
                'visitors' => (int) ($visitors[$key] ?? 0),
                'whatsapp' => (int) ($whatsapp[$key] ?? 0),
                'enquiries' => (int) ($enquiries[$key] ?? 0),
            ])
            ->filter(fn ($r) => $r['visitors'] + $r['whatsapp'] + $r['enquiries'] > 0)
            ->sortByDesc(fn ($r) => [$r['visitors'], $r['enquiries'], $r['whatsapp']])
            ->values()->all();
    }

    /**
     * The projects people look at most — opened in the homepage overlay or
     * read on their own page — with how often each was saved (the heart) and
     * shared, and the WhatsApp taps and enquiries each led to.
     *
     * @return array<int, array{project: Project, opens: int, pages: int, views: int, saves: int, shares: int, whatsapp: int, enquiries: int}>
     */
    public static function topProjects(Carbon $start, Carbon $end, int $limit = 8): array
    {
        $ids = Project::query()->pluck('id', 'slug');

        // /projects/{slug} and /ku/projects/{slug}, both counted for the project
        $pages = [];
        PageView::query()->whereBetween('created_at', [$start, $end])
            ->where(fn ($q) => $q->where('path', 'like', '/projects/%')->orWhere('path', 'like', '/ku/projects/%'))
            ->selectRaw('path, count(*) as c')->groupBy('path')
            ->pluck('c', 'path')
            ->each(function ($count, $path) use ($ids, &$pages) {
                $id = $ids[basename($path)] ?? null;
                if ($id) {
                    $pages[$id] = ($pages[$id] ?? 0) + (int) $count;
                }
            });

        $events = fn (string $type) => SiteEvent::query()->where('type', $type)
            ->whereBetween('created_at', [$start, $end])->whereNotNull('project_id')
            ->selectRaw('project_id, count(*) as c')->groupBy('project_id')
            ->pluck('c', 'project_id');
        $opens = $events(SiteEvent::PROJECT_OPEN);
        $whatsapp = $events(SiteEvent::WHATSAPP_CLICK);
        $saves = $events(SiteEvent::SAVE);
        $shares = $events(SiteEvent::SHARE);

        $enquiries = ContactMessage::query()->whereBetween('created_at', [$start, $end])
            ->whereNotNull('project_id')
            ->selectRaw('project_id, count(*) as c')->groupBy('project_id')
            ->pluck('c', 'project_id');

        $rows = collect(array_unique([...array_keys($pages), ...$opens->keys(), ...$whatsapp->keys(),
            ...$saves->keys(), ...$shares->keys(), ...$enquiries->keys()]))
            ->map(fn ($id) => [
                'id' => (int) $id,
                'opens' => (int) ($opens[$id] ?? 0),
                'pages' => (int) ($pages[$id] ?? 0),
                'saves' => (int) ($saves[$id] ?? 0),
                'shares' => (int) ($shares[$id] ?? 0),
                'whatsapp' => (int) ($whatsapp[$id] ?? 0),
                'enquiries' => (int) ($enquiries[$id] ?? 0),
            ])
            ->map(fn ($r) => $r + ['views' => $r['opens'] + $r['pages']])
            ->sortByDesc(fn ($r) => [$r['views'], $r['enquiries'], $r['saves'], $r['whatsapp']])
            ->take($limit)->values();

        $projects = Project::query()->whereIn('id', $rows->pluck('id'))->get()->keyBy('id');

        return $rows
            ->filter(fn ($r) => $projects->has($r['id']))
            ->map(fn ($r) => ['project' => $projects[$r['id']]] + $r)
            ->values()->all();
    }

    /**
     * Cost estimates worked out in a window (once per visit to the
     * estimator), and how many were sent to the studio with an enquiry.
     *
     * @return array{run: int, sent: int}
     */
    public static function estimates(Carbon $start, Carbon $end): array
    {
        return [
            'run' => SiteEvent::query()->where('type', SiteEvent::ESTIMATE)->whereBetween('created_at', [$start, $end])->count(),
            'sent' => ContactMessage::query()->whereNotNull('estimate')->whereBetween('created_at', [$start, $end])->count(),
        ];
    }

    /** WhatsApp taps in a window, from every button. */
    public static function whatsappTaps(Carbon $start, Carbon $end): int
    {
        return SiteEvent::query()->where('type', SiteEvent::WHATSAPP_CLICK)
            ->whereBetween('created_at', [$start, $end])->count();
    }
}
