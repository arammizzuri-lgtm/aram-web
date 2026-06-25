<?php

namespace App\Providers\Filament;

use App\Filament\Auth\Login;
use App\Filament\Pages\Dashboard;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * The Aram Mizuri studio admin panel.
 *
 * Mounted at a secret path, dark by default, and themed with the Kurdistan
 * flag palette (gold / red / green) so it feels like part of the brand rather
 * than a generic dashboard. Public registration is intentionally NOT enabled —
 * the only accounts are the admin users created from the CLI.
 */
class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('studio-aks-panel')
            ->path('studio-aks-panel')          // secret route
            ->login(Login::class)
            ->profile(isSimple: false)
            ->brandName('Aram Mizuri Studio')
            ->favicon(asset('herosun.png'))
            ->font('Inter')
            ->defaultThemeMode(ThemeMode::Dark)
            ->darkMode(isForced: true)
            ->colors([
                'primary' => Color::hex('#F5C518'), // Kurdistan gold
                'danger' => Color::hex('#CE1126'), // Kurdistan red
                'success' => Color::hex('#007A3D'), // Kurdistan green
                'warning' => Color::hex('#C9A000'), // gold (dim)
            ])
            ->sidebarCollapsibleOnDesktop()
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
