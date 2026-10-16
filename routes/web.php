<?php

use App\Http\Controllers\BoardController;
use App\Http\Controllers\CardController;
use App\Http\Controllers\CareersController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\EstimateController;
use App\Http\Controllers\EventController;
use App\Http\Controllers\PhotosController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\ProjectPageController;
use App\Http\Controllers\ProjectsController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\SavedController;
use App\Http\Controllers\SeoController;
use App\Http\Controllers\SketchController;
use App\Http\Middleware\PrivatePage;
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
        // every project on one page, from the homepage's "Projects" heading
        Route::get('/projects', [ProjectsController::class, 'index'])->name('projects');
        Route::get('/projects/{slug}', [ProjectPageController::class, 'show'])
            ->where('slug', '[a-z0-9-]+')
            ->name('project');
        Route::get('/careers', [CareersController::class, 'index'])->name('careers');
        // every room from every project, side by side
        Route::get('/photos', [PhotosController::class, 'index'])->name('photos');
        // a visitor's hearted projects, or a list someone shared with them
        Route::get('/saved', [SavedController::class, 'index'])->name('saved');
        // "What will your house cost?"
        Route::get('/estimate', [EstimateController::class, 'index'])->name('estimate');
        // a holiday card with the visitor's name, between the occasion's dates
        Route::get('/card', [CardController::class, 'show'])->name('card');
    });

    // The estimator's live result, as each answer changes — not a page view.
    Route::middleware(SiteLanguage::class.':'.$lang)->group(function () {
        Route::get('/estimate/calc', [EstimateController::class, 'calc'])
            ->middleware('throttle:120,1')->name('estimate.calc');
    });
};

$pages('en');
Route::prefix('ku')->name('ku.')->group(fn () => $pages('ku'));

/*
| A client's private room: a secret link and a PIN (RoomController). In both
| languages like every page, but never counted in the visitor numbers (its
| address is the secret) and sent as a PrivatePage.
*/
$rooms = function (string $lang) {
    Route::middleware([SiteLanguage::class.':'.$lang, PrivatePage::class])
        ->prefix('room/{token}')->where(['token' => '[A-Za-z0-9]{40}'])
        ->group(function () {
            Route::get('/', [RoomController::class, 'show'])->name('room');
            Route::post('/pin', [RoomController::class, 'unlock'])->middleware('throttle:30,1')->name('room.unlock');
            Route::post('/drawings/{drawing}', [RoomController::class, 'decide'])->whereNumber('drawing')
                ->middleware('throttle:30,1')->name('room.decide');
            Route::post('/updates/{update}/comments', [RoomController::class, 'comment'])->whereNumber('update')
                ->middleware('throttle:30,1')->name('room.comment');
            Route::get('/drawings/{drawing}', [RoomController::class, 'drawing'])->whereNumber('drawing')->name('room.drawing');
            Route::get('/receipts/{payment}', [RoomController::class, 'receipt'])->whereNumber('payment')->name('room.receipt');
        });
};

$rooms('en');
Route::prefix('ku')->name('ku.')->group(fn () => $rooms('ku'));

// What a site board's QR code says (BoardController): short, so the code
// reads from across a street; on to the project's page, counted as a board.
Route::get('/b/{id}', [BoardController::class, 'scan'])->whereNumber('id')->name('board');

Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');

// A sketch drawn for the studio (sketch.js), and the address it is seen at
Route::post('/sketches', [SketchController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('sketch.store');
Route::get('/sketches/{token}', [SketchController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{40}')
    ->name('sketch.show');

Route::post('/careers/apply', [CareersController::class, 'apply'])
    ->middleware('throttle:applications')
    ->name('careers.apply');

// Anonymous pings for the dashboard (navigator.sendBeacon cannot send a CSRF
// header, and a stale cached page must not turn a tap into a 419): exempt
// from CSRF in bootstrap/app.php, rate-limited here.
Route::post('/t', [EventController::class, 'store'])
    ->middleware('throttle:events')
    ->name('event');
