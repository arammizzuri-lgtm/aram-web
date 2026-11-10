<?php

namespace App\Http\Middleware;

use App\Models\MissingPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts the pages people ask for that do not exist, for the admin's Broken
 * links: the address, how often, and the page that linked to it. Global, so
 * an address no route answers is seen too.
 *
 * Only what a visitor could have followed: a GET for a page, not the bots
 * that try every site for WordPress, PHP or config files, and not the admin.
 * Never in the way: if it cannot write, the visitor still gets their page.
 */
class RecordMissingPage
{
    /** What scanners ask every site for — not links anyone followed. */
    private const PROBE = '#(^/(wp-|wordpress|xmlrpc|cgi-bin|\.well-known|\.git|\.env|vendor/|phpmyadmin|admin\b|administrator|studio-aks-panel|livewire|up$)|\.(php\d?|asp|aspx|jsp|cgi|env|sql|bak|zip|gz|tar|ini|log|yml|yaml)$|/apple-touch-icon|/favicon)#i';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 404 && $request->isMethod('GET') && ! $request->expectsJson()) {
            $this->record($request);
        }

        return $response;
    }

    private function record(Request $request): void
    {
        $path = '/'.ltrim(mb_substr($request->path(), 0, 250), '/');
        if (preg_match(self::PROBE, $path)) {
            return;
        }
        $referrer = mb_substr((string) $request->headers->get('referer', ''), 0, 500) ?: null;

        try {
            $page = MissingPage::query()->where('path', $path)->first();
            if ($page) {
                $page->forceFill(['hits' => $page->hits + 1, 'last_seen_at' => now(), 'referrer' => $referrer ?? $page->referrer])->save();
            } elseif (MissingPage::query()->count() < MissingPage::MAX_ROWS) {
                MissingPage::query()->create(['path' => $path, 'hits' => 1, 'referrer' => $referrer, 'first_seen_at' => now(), 'last_seen_at' => now()]);
            }
        } catch (Throwable) {
            // the list is a help, never a reason for a page to fail
        }
    }
}
