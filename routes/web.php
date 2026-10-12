<?php

use App\Http\Controllers\ContactController;
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
    });
};

$pages('en');
Route::prefix('ku')->name('ku.')->group(fn () => $pages('ku'));

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');
