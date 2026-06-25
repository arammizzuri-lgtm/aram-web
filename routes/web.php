<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\PortfolioController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| The admin panel lives at its own secret path and is registered separately
| by the Filament panel provider.
*/

Route::get('/', [PortfolioController::class, 'index'])->middleware(TrackPageView::class)->name('home');

Route::post('/contact', [ContactController::class, 'store'])
    ->middleware('throttle:contact')
    ->name('contact.store');
