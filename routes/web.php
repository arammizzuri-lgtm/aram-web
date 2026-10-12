<?php

use App\Http\Controllers\CareersController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProjectPageController;
use App\Http\Controllers\SeoController;
use App\Http\Middleware\SiteLanguage;
use App\Http\Middleware\TrackPageView;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| The admin panel lives at its own secret path and is registered separately
| by the Filament panel provider.
|
| Every public page exists twice: in English at its plain address and in
| Kurdish under /ku, so each language can be linked to and found on its own.
*/

$pages = function (string $lang) {
    Route::middleware([SiteLanguage::class.':'.$lang, TrackPageView::class])->group(function () {
        Route::get('/', [PortfolioController::class, 'index'])->name('home');
        Route::get('/projects/{slug}', [ProjectPageController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')
            ->name('project');
        Route::get('/careers', [CareersController::class, 'index'])->name('careers');
    });
};

$pages('en');
Route::prefix('ku')->name('ku.')->group(fn () => $pages('ku'));

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

Route::post('/careers/apply', [CareersController::class, 'apply'])
    ->middleware('throttle:applications')
    ->name('careers.apply');

// Anonymous pings for the dashboard (navigator.sendBeacon cannot send a CSRF
// header, and a stale cached page must not turn a tap into a 419): exempt
// from CSRF in bootstrap/app.php, rate-limited here.
Route::post('/t', [EventController::class, 'store'])
    ->middleware('throttle:events')
    ->name('event');
