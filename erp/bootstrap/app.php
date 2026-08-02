<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        // Moved under the mount, with everything else registered at the root,
        // by App\Providers\MountServiceProvider.
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();

/*
 * The document root belongs to the public site, so this application's web-facing
 * directory is a subdirectory of it rather than the usual erp/public.
 *
 * Setting it here rather than in the front controller keeps the console in
 * agreement with the web: `storage:link` and `filament:assets` publish into the
 * same directory the browser is served from.
 */
$app->usePublicPath(dirname(__DIR__, 2).'/public/erp');

return $app;
