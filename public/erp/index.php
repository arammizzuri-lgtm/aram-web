<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

/*
 * Front controller for the ERP: a second Laravel application, kept in /erp at
 * the root of the repository, reachable at /erp on the public site. Only this
 * directory is web-reachable; its code, configuration and database sit outside
 * the document root.
 *
 * Two things here are unusual, both of them consequences of the application
 * answering on a path of the site rather than on a host of its own.
 *
 * First, this directory — public/erp — is that application's public directory;
 * erp/public does not exist. See usePublicPath() in erp/bootstrap/app.php.
 *
 * Second, SCRIPT_NAME is normalised below so that Laravel does NOT recognise
 * /erp as a base path. That is deliberate, and it is the whole reason the mount
 * works. When Laravel knows about a base path it subtracts it from every
 * root-relative URL it generates (RouteUrlGenerator::to), and both Livewire and
 * Filament hand such URLs to the browser — which resolves them against the site
 * root and sends them to the public site instead. So the prefix is carried by
 * the application's own routes (config('erp.mount')) rather than by Laravel's
 * base-path handling: real, rather than an adjustment made and then undone.
 */

define('LARAVEL_START', microtime(true));

$erp = __DIR__.'/../../erp';

/*
 * Where this directory is published: '/erp' when Apache serves it from the
 * site's document root, '' when `artisan serve` serves it directly. Static
 * assets are addressed from here — see App\Providers\MountServiceProvider.
 */
define('ERP_MOUNT', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/'));

// As above: with the script at the root, Symfony finds no base path to strip
// and hands Laravel the request path whole, /erp included.
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $erp.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $erp.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $erp.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
