<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Consignments\ConsignmentResource;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\Deals\DealResource;
use App\Support\DashboardRange;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The first screen, with the three things you came here to start.
 *
 * Filament's stock dashboard has no header actions, so beginning the ordinary
 * day's work — a customer asks for something, money arrives, the forwarder
 * sends a tracking number — meant navigating to the right screen first and only
 * then starting. Three buttons remove a step from each of the three jobs this
 * business is made of.
 *
 * Only three. A dashboard with a button for everything is a menu, and the point
 * of a starting screen is that it says what is worth starting.
 */
class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard';

    public function getSubheading(): ?string
    {
        return 'What needs you, where the money is, and what is on.';
    }

    /**
     * The one control that sets the window for everything that has one.
     *
     * Flows, the customer and supplier rankings and the cash-flow figures move
     * with this; the balances, the aging and the twelve-month trend do not,
     * because they answer questions a date range cannot change. Custom dates
     * only appear once you ask for them, so the ordinary choice is one click.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('range')
                ->label('Time range')
                ->options(DashboardRange::options())
                ->default(DashboardRange::defaultValue())
                ->selectablePlaceholder(false)
                ->native(false)
                ->live(),

            DatePicker::make('startDate')
                ->label('From')
                ->native(false)
                ->visible(fn (Get $get): bool => $get('range') === DashboardRange::CUSTOM)
                ->required(fn (Get $get): bool => $get('range') === DashboardRange::CUSTOM),

            DatePicker::make('endDate')
                ->label('To')
                ->native(false)
                ->visible(fn (Get $get): bool => $get('range') === DashboardRange::CUSTOM)
                ->required(fn (Get $get): bool => $get('range') === DashboardRange::CUSTOM),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newDeal')
                ->label('New deal')
                ->icon('heroicon-o-plus')
                ->url(fn () => DealResource::getUrl('create')),

            Action::make('recordPayment')
                ->label('Record a payment')
                ->icon('heroicon-o-banknotes')
                ->color('gray')
                ->url(fn () => CustomerPaymentResource::getUrl()),

            Action::make('logTracking')
                ->label('Log a tracking number')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(fn () => ConsignmentResource::getUrl()),
        ];
    }
}
