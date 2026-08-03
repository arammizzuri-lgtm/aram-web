<?php

namespace App\Filament\Resources\CustomerPayments\Pages;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Actions\UnmatchPayment;
use App\Filament\Resources\CustomerPayments\CustomerPaymentResource;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Services\Deals\PaymentWriter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\HtmlString;
use Throwable;

class ManageCustomerPayments extends ManageRecords
{
    protected static string $resource = CustomerPaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record a payment')
                ->using(fn (array $data) => $this->record($data))
                // The money is safe the moment it is saved; matching it to
                // invoices is offered straight afterwards, not demanded.
                ->after(fn (CustomerPayment $record) => $this->offerToMatch($record)),
        ];
    }

    protected function getTableActions(): array
    {
        return [
            $this->matchAction(),

            /*
             * Taking it back off again.
             *
             * PaymentWriter::unallocate() has existed since this was written and
             * nothing ever called it, so money matched to the wrong invoice was
             * matched to it for good — and because an invoice with money against
             * it cannot be cancelled, one wrong click could wedge an account
             * with no way out of it.
             */
            UnmatchPayment::make(),

            EditAction::make()
                ->using(fn (CustomerPayment $record, array $data) => app(PaymentWriter::class)->amend($record, $data)),
            ...RecordDeletion::actions(),
        ];
    }

    /**
     * Saving the form is delegated to PaymentWriter rather than written
     * straight to the table.
     *
     * A payment is not only the fields on the screen: it needs its receipt
     * number, and the amount converted to the currency the business measures
     * itself in. Neither is asked of whoever records it, and neither has a
     * default — so a form saved directly is rejected by the database for the
     * missing number, which is exactly what it did.
     *
     * @param  array<string, mixed>  $data
     */
    private function record(array $data): CustomerPayment
    {
        $payments = app(PaymentWriter::class);

        $customer = Customer::findOrFail($data['customer_id']);
        $rate = $payments->rate($data['exchange_rate'] ?? null);

        // How much, and which way, are separate questions on this form, so the
        // amount is a magnitude and Direction alone decides the sign.
        $amount = abs((float) $data['amount']);

        $arguments = [
            'customer' => $customer,
            'amount' => $amount,
            'currency' => $data['currency'],
            'exchangeRate' => $rate,
            'paidAt' => $data['paid_at'] ?? null,
            'method' => $data['method'] ?? 'cash',
            'reference' => $data['reference'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        return ($data['direction'] ?? 'in') === 'refund'
            ? $payments->refund(...$arguments)
            : $payments->receive(...$arguments);
    }

    private function offerToMatch(CustomerPayment $record): void
    {
        $suggestion = app(PaymentWriter::class)->suggestAllocation($record);

        if ($suggestion === []) {
            Notification::make()
                ->title('Payment recorded')
                ->body('Nothing outstanding to match it against — it is sitting as credit.')
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Payment recorded')
            ->body(sprintf('It can cover %d invoice%s — use Match to apply it.',
                count($suggestion),
                count($suggestion) === 1 ? '' : 's',
            ))
            ->success()
            ->send();
    }

    /**
     * Point money at invoices.
     *
     * The suggestion clears the oldest first, which is what both sides usually
     * assume when nothing is said — but it arrives pre-filled and editable,
     * because a customer may have meant a specific invoice, particularly if
     * one of them is disputed.
     */
    private function matchAction(): Action
    {
        return Action::make('match')
            ->label('Match')
            ->icon('heroicon-o-link')
            ->color('success')
            ->visible(fn (CustomerPayment $record) => $record->unallocatedBase()->isPositive()
                && ! $record->isRefund())
            ->modalHeading(fn (CustomerPayment $record) => "Match {$record->number}")
            ->modalDescription(fn (CustomerPayment $record) => sprintf(
                '%s unmatched. Oldest invoices are filled in first — change anything, or leave some as credit.',
                $record->unallocatedBase()->display(),
            ))
            ->schema(fn (CustomerPayment $record) => $this->matchFields($record))
            ->action(function (CustomerPayment $record, array $data) {
                $allocations = [];

                foreach ($data as $key => $value) {
                    if (str_starts_with($key, 'invoice_') && (float) $value > 0) {
                        $allocations[(int) substr($key, 8)] = $value;
                    }
                }

                if ($allocations === []) {
                    Notification::make()->title('Nothing matched — it stays as credit.')->warning()->send();

                    return;
                }

                try {
                    app(PaymentWriter::class)->allocate($record, $allocations);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                $left = $record->fresh()->unallocatedBase();

                Notification::make()
                    ->title('Payment matched')
                    ->body($left->isPositive() ? $left->display().' left as credit.' : null)
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, mixed> */
    private function matchFields(CustomerPayment $record): array
    {
        $suggestion = app(PaymentWriter::class)->suggestAllocation($record);

        $open = $record->customer->invoices()
            ->outstanding()
            ->with('allocations')
            ->orderBy('invoice_date')
            ->get()
            ->filter(fn ($invoice) => $invoice->outstandingBase()->isPositive());

        if ($open->isEmpty()) {
            return [
                Placeholder::make('none')
                    ->hiddenLabel()
                    ->content(new HtmlString(
                        '<p>This customer has nothing outstanding. The money stays as credit '
                        .'until there is something to put it against.</p>'
                    )),
            ];
        }

        return $open->map(fn ($invoice) => TextInput::make('invoice_'.$invoice->id)
            ->label($invoice->number.' — '.$invoice->invoice_date?->format('d M Y'))
            ->numeric()
            ->prefix('$')
            ->default(fn () => (float) ($suggestion[$invoice->id]?->amount ?? 0))
            ->helperText(sprintf(
                '%s outstanding of %s',
                $invoice->outstandingBase()->display(),
                number_format((float) $invoice->total, $invoice->currency === 'IQD' ? 0 : 2).' '.$invoice->currency,
            )))->all();
    }
}
