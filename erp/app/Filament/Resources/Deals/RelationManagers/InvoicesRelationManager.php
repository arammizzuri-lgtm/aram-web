<?php

namespace App\Filament\Resources\Deals\RelationManagers;

use App\Models\CustomerInvoice;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * What the customer has been billed, and what is still owed on it.
 *
 * Issued from the buttons at the top of the deal, so nothing is created here —
 * this is where you see what those buttons produced, and reach the printable
 * document without going looking for it.
 */
class InvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    protected static ?string $title = 'Invoices';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('allocations'))
            ->recordTitleAttribute('number')
            ->emptyStateHeading('Nothing billed yet')
            ->emptyStateDescription('Use "Invoice goods" above once the prices are settled.')
            ->defaultSort('invoice_date', 'desc')
            ->columns([
                TextColumn::make('number')->label('Invoice')->weight('medium'),

                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => CustomerInvoice::TYPES[$state] ?? $state)
                    ->color(fn (string $state) => $state === 'shipping' ? 'info' : 'gray'),

                TextColumn::make('invoice_date')->label('Date')->date('d M Y'),

                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    // In the currency it was billed in — the figure the customer
                    // is holding a copy of.
                    ->formatStateUsing(fn ($state, CustomerInvoice $r) => number_format(
                        (float) $state, $r->currency === 'IQD' ? 0 : 2
                    ).' '.$r->currency),

                TextColumn::make('paid')
                    ->label('Paid')
                    ->state(fn (CustomerInvoice $r) => $r->paidBase()->toFloat())
                    ->money('USD')
                    ->alignEnd(),

                TextColumn::make('outstanding')
                    ->label('Still due')
                    ->state(fn (CustomerInvoice $r) => $r->outstandingBase()->toFloat())
                    ->money('USD')
                    ->alignEnd()
                    ->color(fn (CustomerInvoice $r) => $r->isPaid() ? 'gray' : 'warning'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'paid' => 'success',
                        'issued' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                /*
                 * Printing goes through the browser, which is what lays out the
                 * right-to-left Sorani documents correctly. A new tab, because
                 * you are usually printing it beside the deal rather than
                 * instead of it.
                 */
                Action::make('print')
                    ->label('Print')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(
                        fn (CustomerInvoice $record) => route('filament.admin.invoices.print', $record),
                        shouldOpenInNewTab: true,
                    ),
            ]);
    }
}
