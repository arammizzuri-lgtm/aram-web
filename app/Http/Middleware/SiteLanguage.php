<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders the routes under /ku in Kurdish.
 *
 * The site used to be English on the server and turned Kurdish only in the
 * browser, so Google — and every link preview — only ever saw English. Each
 * language now has its own address, and the page arrives already in it:
 * bival(), bi() and site_url() read the locale this sets.
 */
class SiteLanguage
{
    public function handle(Request $request, Closure $next, string $lang = 'en'): Response
    {
        app()->setLocale($lang === 'ku' ? 'ku' : 'en');

        return $next($request);
    }
}
