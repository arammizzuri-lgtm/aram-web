<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Http\Controllers\CustomerInvoicePrintController;
use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            /*
             * The mount, not 'admin'. This application answers on /erp of the
             * public site and the panel is the whole of it, so the panel sits at
             * the root of the mount: /erp is the login, and everything else
             * hangs off it. Nothing else here serves a page for it to shadow.
             */
            ->path(config('erp.mount'))
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName('Aram Mizuri Sourcing')
            ->colors([
                // Cyan, the one accent of the dark-glass design: primary action,
                // active navigation and focus. Kept clear of the categorical chart
                // hues so the accent never reads as a data series.
                'primary' => Color::Cyan,
                'gray' => Color::Slate,
                // Status ramps carry the reserved meanings of the design system:
                // green gain, amber watch, red loss, blue information.
                'success' => Color::hex('#34d399'),
                'warning' => Color::hex('#fbbf24'),
                'danger' => Color::hex('#f87171'),
                'info' => Color::hex('#38bdf8'),
            ])
            // Dark-only: the glass aesthetic is a dark one, so the panel commits
            // to it rather than keeping a light half that would never match.
            ->defaultThemeMode(ThemeMode::Dark)
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
            /*
             * The customer's copy of an invoice, opened on its own so a browser
             * can print it or save it as a PDF.
             *
             * A page rather than a panel screen, but declared on the panel all
             * the same: that is what gives it the panel's session, its guard and
             * its login screen. Registered in routes/web.php it would fall to
             * Laravel's stock auth middleware, which redirects to a route named
             * `login` — and a Filament application has no such route, so an
             * expired session would meet a 500 instead of a sign-in form.
             */
            ->routes(fn () => Route::get('invoices/{invoice}/print', CustomerInvoicePrintController::class)
                ->middleware(Authenticate::class)
                ->name('invoices.print'))
            /*
             * The listener behind every Undo button.
             *
             * Mounted on every page because a notification can be raised on any
             * of them, and a notification cannot carry a closure — it is
             * serialised and rebuilt, so what it dispatches has to be caught by
             * a component that is already there. See App\Livewire\UndoDelete.
             */
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => Blade::render('@livewire(\'undo-delete\')'),
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                ValidateCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    /**
     * Navigation follows the shape of the business.
     *
     * It had drifted out of step with it. **Trading** — Deals, Consignments,
     * Purchases, which is nearly everything anybody does here — was never
     * declared, so it took no icon and sorted outside the intended order: the
     * central group of the system, placed by accident. Meanwhile Logistics and
     * Inventory were declared and empty, left behind by the redesign that
     * removed the warehouse. Two ghosts above the real thing.
     *
     * The order is the working day. The deal first, because everything is one;
     * then the money it brings in, then the money it costs; then the catalogues
     * behind the prices; then the books, the reports, and the settings nobody
     * opens twice a year.
     *
     * @return array<NavigationGroup>
     */
    protected function navigationGroups(): array
    {
        return [
            NavigationGroup::make('Overview')->icon('heroicon-o-squares-2x2'),
            // The centre of the system: one customer request, followed through.
            NavigationGroup::make('Trading')->icon('heroicon-o-briefcase'),
            NavigationGroup::make('Sales')->icon('heroicon-o-banknotes'),
            NavigationGroup::make('Purchasing')->icon('heroicon-o-shopping-cart'),
            // Supplier catalogues you quote from, upstream of the prices.
            NavigationGroup::make('Price Lists')->icon('heroicon-o-tag'),
            NavigationGroup::make('Catalog')->icon('heroicon-o-cube'),
            NavigationGroup::make('Finance')->icon('heroicon-o-calculator'),
            NavigationGroup::make('Reports')->icon('heroicon-o-chart-bar'),
            NavigationGroup::make('Settings')->icon('heroicon-o-cog-6-tooth')->collapsed(),
        ];
    }
}
