<?php

namespace App\Http\Middleware;

use App\Models\PageView;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Records an anonymized page view for public GET page loads. Bots and non-HTML
 * requests are skipped. The visitor hash is a one-way daily fingerprint, so the
 * same visitor on the same day counts once and no personal data is stored.
 */
class TrackPageView
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($this->shouldTrack($request, $response)) {
                PageView::create([
                    'visitor_hash' => hash('sha256', implode('|', [
                        $request->ip(),
                        $request->userAgent(),
                        now()->toDateString(),
                        config('app.key'),
                    ])),
                    'path' => '/'.ltrim($request->path(), '/'),
                    'referrer' => $request->headers->get('referer'),
                    'created_at' => now(),
                ]);
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
        // Skip obvious bots/crawlers.
        $ua = strtolower((string) $request->userAgent());
        if ($ua === '' || preg_match('/bot|crawl|spider|slurp|bing|google|preview|monitor|headless/i', $ua)) {
            return false;
        }

        return true;
    }
}
