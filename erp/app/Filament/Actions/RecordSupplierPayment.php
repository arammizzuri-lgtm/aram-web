<?php

namespace App\Filament\Actions;

use App\Models\DealPurchase;
use App\Services\Deals\SupplierPaymentWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Record money sent to a supplier.
 *
 * The second amount is the reason this form exists rather than a plain "amount
 * paid" field: the rate you are quoted is not the rate you trade at, and the
 * difference is real money that otherwise never gets counted.
 *
 * An action class rather than a method on a page, because paying a supplier is
 * now reachable from two places — the purchases screen and the deal the
 * purchase belongs to. Written twice it would be maintained once.
 */
class RecordSupplierPayment extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'pay';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Record payment')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->visible(fn (DealPurchase $record) => ! $record->isFullyPaid()
                && $record->status !== 'cancelled'
                && auth()->user()?->can('record_supplier_payment'))
            ->modalHeading(fn (DealPurchase $record) => "Pay {$record->supplier?->name}")
            ->modalDescription(fn (DealPurchase $record) => sprintf(
                'Still owed: %s. Goods total %s.',
                $record->outstandingBase()->display(),
                $record->goodsTotal()->display(),
            ))
            ->schema(fn (DealPurchase $record) => [
                TextInput::make('amount')
                    ->label('Amount sent')
                    ->numeric()
                    ->required()
                    ->suffix($record->currency)
                    // Pre-filled with the usual deposit; a balance payment is a
                    // one-field edit rather than a calculation.
                    ->default(fn () => (float) app(SupplierPaymentWriter::class)
                        ->suggestedDeposit($record)->amount)
                    ->helperText('In the supplier\'s currency, as they invoiced it.'),

                TextInput::make('actual_cost_base')
                    ->label('What it actually cost you')
                    ->numeric()
                    ->prefix('$')
                    ->helperText(
                        'What you really handed over, including the exchange house\'s cut. '
                        .'Leave blank if you do not know — but recorded, it stops every deal '
                        .'reporting slightly more profit than you made.'
                    ),

                Select::make('method')
                    ->options([
                        'exchange' => 'Exchange office',
                        'bank' => 'Bank transfer',
                        'cash' => 'Cash',
                    ])
                    ->default('exchange')
                    ->native(false),

                DatePicker::make('paid_at')->label('Date')->default(now())->required(),

                TextInput::make('reference')->label('Reference')->maxLength(255),

                Textarea::make('notes')->rows(2),
            ])
            ->action(function (DealPurchase $record, array $data) {
                $payment = app(SupplierPaymentWriter::class)->record(
                    purchase: $record,
                    amount: $data['amount'],
                    actualCostBase: filled($data['actual_cost_base'] ?? null)
                        ? (float) $data['actual_cost_base']
                        : null,
                    paidAt: $data['paid_at'] ?? null,
                    method: $data['method'] ?? 'exchange',
                    reference: $data['reference'] ?? null,
                    notes: $data['notes'] ?? null,
                );

                $loss = $payment->transferLossBase();

                Notification::make()
                    ->title("Payment {$payment->number} recorded")
                    ->body($loss->isPositive()
                        ? $loss->display().' of that was the transfer, not the goods.'
                        : null)
                    ->success()
                    ->send();
            });
    }
}
