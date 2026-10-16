<?php

namespace App\Providers;

use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Spam guard for the public contact form: 5 submissions / minute / IP.
        RateLimiter::for('contact', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Job applications: the same, which leaves room to fix a rejected CV
        // and send again, but not to flood the private disk with uploads.
        RateLimiter::for('applications', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // The dashboard's pings (overlay opens, WhatsApp taps): generous for a
        // person browsing, a ceiling for anything scripted.
        RateLimiter::for('events', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Brand styling for every admin page (dark + gold, branded login).
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => view('filament.brand-css')->render(),
        );

        // Leaflet + the map-picker Alpine component, for the project location field.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => view('filament.leaflet-assets')->render(),
        );

        // Cover / focal-point picker for the project images.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => view('filament.cover-picker-assets')->render(),
        );

        // Notes placed on the project's photographs.
        FilamentView::registerRenderHook(
            PanelsRenderHook::HEAD_END,
            fn (): string => view('filament.callout-picker-assets')->render(),
        );
    }
}
