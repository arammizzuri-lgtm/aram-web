<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Consignments\ConsignmentResource;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Filament\Resources\Deals\DealResource;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;

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
    protected static ?string $title = 'Dashboard';

    public function getSubheading(): ?string
    {
        return 'What needs you, where the money is, and what is on.';
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
