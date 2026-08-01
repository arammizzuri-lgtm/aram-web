<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Actions\Sales\AllocatePayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Invoice;
use App\Models\Payment;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Throwable;

class ManagePayments extends ManageRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Record payment')
                ->mutateDataUsing(function (array $data): array {
                    $data['created_by'] ??= auth()->id();
                    $data['exchange_rate'] ??= 1;
                    $data['base_amount'] = (float) $data['amount'] * (float) ($data['exchange_rate'] ?? 1);
                    // Everything starts unallocated; it is applied deliberately.
                    $data['unallocated_amount'] = $data['amount'];

                    return $data;
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return parent::table($table)->recordActions([
            Action::make('allocate')
                ->label('Allocate')
                ->icon('heroicon-m-squares-plus')
                ->modalHeading(fn (Payment $record) => "Allocate {$record->number}")
                ->modalDescription(fn (Payment $record) => new HtmlString(sprintf(
                    'Spread $%s across this customer\'s open invoices. Anything left over stays as credit.',
                    number_format((float) $record->amount, 2),
                )))
                ->modalSubmitActionLabel('Save allocation')
                ->fillForm(fn (Payment $record) => $this->currentPlan($record))
                ->schema(fn (Payment $record) => $this->allocationFields($record))
                ->extraModalFooterActions([
                    // Oldest first, which is what keeps the aging buckets honest.
                    Action::make('auto')
                        ->label('Auto-allocate oldest first')
                        ->color('gray')
                        ->action(function (Payment $record, $livewire) {
                            $plan = app(AllocatePayment::class)->autoAllocate($record);

                            $livewire->mountedTableActionsData[0] = collect($plan)
                                ->mapWithKeys(fn ($amount, $id) => ["invoice_{$id}" => $amount])
                                ->all();
                        })
                        ->cancelParentActions(false),
                ])
                ->action(function (Payment $record, array $data) {
                    try {
                        $plan = collect($data)
                            ->filter(fn ($value, $key) => str_starts_with($key, 'invoice_') && (float) $value > 0)
                            ->mapWithKeys(fn ($value, $key) => [(int) str_replace('invoice_', '', $key) => (float) $value])
                            ->all();

                        $payment = app(AllocatePayment::class)->handle($record, $plan);

                        Notification::make()
                            ->title('Payment allocated')
                            ->body($payment->unallocated() > 0.005
                                ? '$'.number_format($payment->unallocated(), 2).' left as customer credit.'
                                : 'Fully allocated.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Could not allocate this payment')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('clear')
                ->label('Clear')
                ->icon('heroicon-m-arrow-uturn-left')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Removes this payment from every invoice it was applied to.')
                ->visible(fn (Payment $record) => $record->allocations()->exists())
                ->action(function (Payment $record) {
                    app(AllocatePayment::class)->clear($record);

                    Notification::make()->title('Allocation cleared')->success()->send();
                }),
        ]);
    }

    /** @return array<string, float> */
    private function currentPlan(Payment $payment): array
    {
        return $payment->allocations()
            ->get()
            ->mapWithKeys(fn ($a) => ["invoice_{$a->invoice_id}" => (float) $a->amount])
            ->all();
    }

    /** One numeric field per open invoice, with what it is owed alongside. */
    private function allocationFields(Payment $payment): array
    {
        $invoices = Invoice::query()
            ->where('customer_id', $payment->customer_id)
            ->where(fn ($q) => $q->outstanding()->orWhereHas(
                'allocations', fn ($a) => $a->where('payment_id', $payment->id)
            ))
            ->orderBy('due_date')
            ->get();

        if ($invoices->isEmpty()) {
            return [Placeholder::make('none')->label('')->content('This customer has no open invoices.')];
        }

        return $invoices->map(function (Invoice $invoice) use ($payment) {
            $alreadyHere = (float) $invoice->allocations()->where('payment_id', $payment->id)->sum('amount');
            $due = round($invoice->amountDue() + $alreadyHere, 2);

            return Grid::make(2)->schema([
                Placeholder::make("label_{$invoice->id}")
                    ->label($invoice->number)
                    ->content(new HtmlString(sprintf(
                        '<span class="text-sm">$%s due%s</span>',
                        number_format($due, 2),
                        $invoice->isOverdue() ? ' · <strong>'.$invoice->daysOverdue().' days overdue</strong>' : '',
                    ))),

                TextInput::make("invoice_{$invoice->id}")
                    ->label('Allocate')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue($due)
                    ->prefix('$')
                    ->default(0),
            ]);
        })->all();
    }
}
