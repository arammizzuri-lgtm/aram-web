<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * For pages only one person should see (a client room): never kept in a
 * cache along the way, never indexed, and leaving no referrer behind — the
 * address itself is the secret, and a referrer would hand it to whatever
 * site the next link goes to.
 */
class PrivatePage
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Cache-Control', 'private, no-store');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }
}
