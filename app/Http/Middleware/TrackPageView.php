<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use App\Support\TrafficSource;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records an anonymized page view for public GET page loads. Bots and non-HTML
 * requests are skipped. The visitor hash is a one-way daily fingerprint, so the
 * same visitor on the same day counts once and no personal data is stored.
 *
 * Each view also notes where it came from (App\Support\TrafficSource), and
 * the first outside source of a visit is kept in the session as "came_from",
 * so an enquiry or a WhatsApp tap later in the visit is credited to it.
 */
class TrackPageView
{
    public const SESSION_KEY = 'came_from';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->shouldTrack($request, $response)) {
                $source = TrafficSource::classify(
                    $request->headers->get('referer'),
                    $request->userAgent(),
                    $request->query('utm_source'),
                    $request->getHost(),
                );

                PageView::create([
                    'visitor_hash' => PageView::visitorHash($request),
                    'path' => '/'.ltrim($request->path(), '/'),
                    'referrer' => $request->headers->get('referer'),
                    'source' => $source,
                    'created_at' => now(),
                ]);

                if ($source !== TrafficSource::INTERNAL && $request->hasSession()
                    && ! $request->session()->has(self::SESSION_KEY)) {
                    $request->session()->put(self::SESSION_KEY, $source);
                }
            }
        } catch (\Throwable $e) {
            // Analytics must never break a page load.
        }

        return $response;
    }

    private function shouldTrack(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->ajax()) {
            return false;
        }
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        return ! PageView::isBot($request);
    }
}
