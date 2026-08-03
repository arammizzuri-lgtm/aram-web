<?php

namespace App\Filament\Actions;

use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Services\Deals\PaymentWriter;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;

/**
 * Take money back off an invoice.
 *
 * `PaymentWriter::unallocate()` has always existed and nothing ever called it,
 * so a payment matched to the wrong invoice was matched to it permanently —
 * and since an invoice with money against it cannot be cancelled either, one
 * wrong click could wedge a customer's account for good.
 *
 * Nothing is lost by unmatching. The payment stays exactly as recorded and the
 * customer's balance does not move; the money simply stops being pointed at
 * that invoice and goes back to being credit, which is where it started.
 */
class UnmatchPayment
{
    public static function make(): Action
    {
        return Action::make('unmatch')
            ->label('Unmatch')
            ->icon('heroicon-o-link-slash')
            ->color('warning')
            ->visible(fn (CustomerPayment $record) => $record->allocations()->exists())
            ->modalHeading(fn (CustomerPayment $record) => "Unmatch {$record->number}")
            ->modalDescription(
                'The money goes back to credit on the customer\'s account. '
                .'Nothing about the payment itself changes.'
            )
            ->modalSubmitActionLabel('Unmatch these')
            ->schema(fn (CustomerPayment $record) => [
                CheckboxList::make('allocations')
                    ->label('Matched to')
                    ->options(fn () => $record->allocations()
                        ->with('invoice')
                        ->get()
                        ->mapWithKeys(fn (CustomerPaymentAllocation $allocation) => [
                            $allocation->id => sprintf(
                                '%s — %s',
                                $allocation->invoice?->number ?? 'a deleted invoice',
                                '$'.number_format((float) $allocation->base_amount, 2),
                            ),
                        ]))
                    ->required()
                    ->bulkToggleable(),
            ])
            ->action(function (CustomerPayment $record, array $data): void {
                $writer = app(PaymentWriter::class);

                $allocations = CustomerPaymentAllocation::query()
                    ->whereIn('id', $data['allocations'] ?? [])
                    ->where('customer_payment_id', $record->id)
                    ->get();

                foreach ($allocations as $allocation) {
                    $writer->unallocate($allocation);
                }

                $freed = $record->fresh()->load('allocations')->unallocatedBase();

                Notification::make()
                    ->title($allocations->count() === 1 ? 'Unmatched' : $allocations->count().' unmatched')
                    ->body($freed->display().' is now sitting as credit.')
                    ->success()
                    ->send();
            });
    }
}
