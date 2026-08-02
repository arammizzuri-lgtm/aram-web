<?php

namespace App\Providers;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

/**
 * Everything the ERP needs in order to answer on a path of the public site
 * (/erp) rather than on a host of its own.
 *
 * The public site is a Laravel application too, and it owns the root of this
 * domain — including /livewire/update, which its own admin panel needs. So a
 * route left at the root here is not merely at the wrong address, it is at
 * somebody else's, and requests for it never reach this application at all.
 *
 * See public/erp/index.php for why the prefix is carried by the routes rather
 * than by Laravel's base-path handling.
 */
class MountServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (($mount = $this->mount()) === '') {
            return;
        }

        /*
         * Filament registers its export and failed-import downloads outside any
         * panel, under a prefix of its own. Set during registration, because
         * that package loads its route file when it boots — after every
         * provider has registered. Set absolutely rather than by prepending to
         * the current value, so that caching the configuration and reading it
         * back cannot apply the mount twice.
         */
        config(['filament.system_route_prefix' => $mount.'/filament']);
    }

    public function boot(): void
    {
        if (($mount = $this->mount()) === '') {
            return;
        }

        $this->app->booted(fn () => $this->moveRoutesLeftAtTheRootUnder($mount));

        $this->serveFilesFromTheMount();
    }

    private function mount(): string
    {
        return trim((string) config('erp.mount'), '/');
    }

    /**
     * Packages register their own endpoints — Livewire's update, script, upload
     * and file-preview routes above all — at the application root, which is not
     * this application's to publish at. Each is moved under the mount.
     *
     * The routes are edited in place rather than re-registered there, and that
     * is the point rather than an economy: a second route bearing the same name
     * is what `route:cache` refuses to serialize, and the packages ask for these
     * URLs by name and by route object, so a copy under a different name would
     * not be used anyway.
     *
     * Run once every provider has booted, so that nothing registered after this
     * provider is missed, and skipping anything already under the mount so that
     * a cached route table — which was written after this ran — is left alone.
     */
    private function moveRoutesLeftAtTheRootUnder(string $mount): void
    {
        foreach (Route::getRoutes()->getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');

            if ($uri === $mount || str_starts_with($uri, $mount.'/')) {
                continue;
            }

            $route->setUri($mount.'/'.$uri);
        }
    }

    /**
     * Stylesheets, scripts, fonts and uploaded files are all files in
     * public/erp, so their URLs need the path the site publishes that directory
     * at. The front controller reads it off the request rather than guessing,
     * which keeps the deployed site and `artisan serve` on the same code.
     *
     * Applied per request rather than in the configuration files, because the
     * deploy caches the configuration from the console — where there is no
     * request to read the mount from.
     *
     * An explicit ASSET_URL wins, and is how the console, which renders invoices
     * to PDF outside any request, is told the same thing.
     */
    private function serveFilesFromTheMount(): void
    {
        if (! \defined('ERP_MOUNT') || ERP_MOUNT === '' || filled(config('app.asset_url'))) {
            return;
        }

        $origin = $this->app['request']->getSchemeAndHttpHost().ERP_MOUNT;

        URL::useAssetOrigin($origin);

        config(['filesystems.disks.public.url' => $origin.'/storage']);
    }
}
