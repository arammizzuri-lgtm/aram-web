<?php

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName('Import ERP')
            ->colors([
                // Indigo, replacing Filament's default amber: one accent, used only for
                // primary action, active navigation and focus. Deliberately clear of the
                // categorical chart hues so the accent never reads as a data series.
                //
                // The built-in ramp is used rather than Color::hex('#4f46e5') because
                // hex() anchors the given colour at shade 500, which pushed every
                // interactive surface a step too pale. Indigo-600 is #4f46e5 exactly.
                'primary' => Color::Indigo,
                'gray' => Color::Zinc,
                // Status ramps carry the reserved meanings from docs/05-UIUX.md §A1.
                'success' => Color::hex('#0ca30c'),
                'warning' => Color::hex('#fab219'),
                'danger' => Color::hex('#d03b3b'),
                'info' => Color::hex('#2a78d6'),
            ])
            ->defaultThemeMode(ThemeMode::System)
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->globalSearchKeyBindings(['command+k', 'ctrl+k'])
            // The bell reads what `erp:alerts` writes each morning.
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->navigationGroups($this->navigationGroups())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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

    /**
     * Navigation follows the shape of the business, not the shape of the schema:
     * goods are bought, shipped, costed, stocked, then sold.
     *
     * @return array<NavigationGroup>
     */
    protected function navigationGroups(): array
    {
        return [
            NavigationGroup::make('Overview')->icon('heroicon-o-squares-2x2'),
            // Supplier catalogues you quote from, upstream of what you stock.
            NavigationGroup::make('Price Lists')->icon('heroicon-o-tag'),
            NavigationGroup::make('Catalog')->icon('heroicon-o-cube'),
            NavigationGroup::make('Purchasing')->icon('heroicon-o-shopping-cart'),
            NavigationGroup::make('Logistics')->icon('heroicon-o-truck'),
            NavigationGroup::make('Inventory')->icon('heroicon-o-archive-box'),
            NavigationGroup::make('Sales')->icon('heroicon-o-banknotes'),
            NavigationGroup::make('Finance')->icon('heroicon-o-calculator'),
            NavigationGroup::make('Reports')->icon('heroicon-o-chart-bar'),
            NavigationGroup::make('Settings')->icon('heroicon-o-cog-6-tooth')->collapsed(),
        ];
    }
}
