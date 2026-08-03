<?php

namespace App\Filament\Resources\Deals\Pages;

use App\Filament\Actions\RecordApproval;
use App\Filament\Actions\RecordDeletion;
use App\Filament\Resources\Deals\DealResource;
use App\Services\Deals\DealWriter;
use App\Services\Deals\InvoiceWriter;
use App\Services\Deals\QuotationWriter;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditDeal extends EditRecord
{
    protected static string $resource = DealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->quoteAction(),

            /*
             * Was hidden until a quotation existed, which made a document the
             * price of recording a fact. Most approvals arrive on WhatsApp
             * against a photo; the quotation, where there is one, still carries
             * the approval.
             */
            RecordApproval::make()->record(fn () => $this->record),

            $this->invoiceGoodsAction(),
            $this->invoiceShippingAction(),

            ActionGroup::make([
                $this->cancelAction(),

                /*
                 * Deleting is a warning here, not a wall — as approval is.
                 *
                 * It used to vanish entirely the moment anything was invoiced,
                 * with no explanation on the screen and no alternative offered,
                 * so a deal you needed gone was simply stuck. What it costs is
                 * now spelled out for this particular deal and the decision is
                 * left where it belongs. The delete is soft and the deals list
                 * has a Deleted filter to bring it back from.
                 */
                RecordDeletion::delete(),
            ])->hiddenLabel(),
        ];
    }

    /**
     * The deal that did not happen.
     *
     * The alternative the delete button has always pointed at without ever
     * offering: cancelling keeps the deal, its quotations and its history
     * visible and searchable, and takes it off everything that counts open
     * work. Deleting hides all of that. Most of the time this is the one you
     * want, which is why it sits above the other.
     */
    private function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel this deal')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn () => $this->record->status !== 'cancelled')
            ->requiresConfirmation()
            ->modalHeading(fn () => "Cancel {$this->record->number}?")
            ->modalDescription(
                'The deal stays where it is, marked cancelled, with everything on it '
                .'intact. It stops counting as open work.'
            )
            ->schema([
                Textarea::make('reason')
                    ->label('Why?')
                    ->placeholder('e.g. customer went elsewhere, supplier could not make the deadline')
                    ->rows(2),
            ])
            ->action(function (array $data) {
                $reason = trim((string) ($data['reason'] ?? ''));

                $this->record->update([
                    'status' => 'cancelled',
                    // Appended rather than replacing: the notes are the record of
                    // what happened, and this is one more thing that happened.
                    'internal_notes' => trim(implode("\n\n", array_filter([
                        $this->record->internal_notes,
                        $reason === '' ? null : 'Cancelled: '.$reason,
                    ]))) ?: null,
                ]);

                Notification::make()
                    ->title("{$this->record->number} cancelled")
                    ->body('It stays on file. Delete it only if it should not be there at all.')
                    ->success()
                    ->send();

                $this->refreshFormData(['status', 'internal_notes']);
            });
    }

    /**
     * Freeze the current lines into a new quotation version.
     *
     * Never edits the previous one: superseding leaves a visible trail of what
     * changed and when, which is the entire reason to keep versions at all.
     */
    private function quoteAction(): Action
    {
        return Action::make('quote')
            ->label('Create quotation')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Create a quotation')
            ->modalDescription(
                'This takes a copy of the items, prices and photos as they stand now. '
                .'Any earlier draft is marked superseded rather than changed.'
            )
            ->action(function () {
                try {
                    $quotation = app(QuotationWriter::class)->build($this->record);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("Quotation {$quotation->number} created")
                    ->body('Version '.$quotation->version)
                    ->success()
                    ->send();
            });
    }

    /**
     * Bill the goods.
     *
     * Offered whether or not the customer approved, because approval is a
     * warning here rather than a wall — but hidden once a goods invoice exists,
     * since billing the same order twice is nearly always a mistake.
     */
    private function invoiceGoodsAction(): Action
    {
        return Action::make('invoiceGoods')
            ->label('Invoice goods')
            ->icon('heroicon-o-document-currency-dollar')
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading('Invoice the goods')
            ->modalDescription(
                'This takes a copy of the items and prices as they stand. The invoice '
                .'will not change afterwards, even if the deal does.'
            )
            ->visible(fn () => ! $this->record->invoices()
                ->where('type', 'goods')
                ->whereNot('status', 'cancelled')
                ->exists())
            ->action(function () {
                try {
                    $invoice = app(InvoiceWriter::class)->issueGoods($this->record);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title("Invoice {$invoice->number} issued")
                    ->body('Printing in '.($invoice->language === 'ckb' ? 'Kurdish' : 'English').'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Bill the shipping, once the freight bill has arrived.
     *
     * A second document rather than a line on the first, because at the time
     * the goods were billed this number did not exist yet — and a document
     * already sent must not change.
     */
    private function invoiceShippingAction(): Action
    {
        return Action::make('invoiceShipping')
            ->label('Invoice shipping')
            ->icon('heroicon-o-truck')
            ->color('gray')
            // Only once freight has actually been recorded against the deal.
            ->visible(fn () => $this->record->consignments()
                ->wherePivot('freight_share_base', '>', 0)
                ->exists())
            ->schema([
                TextInput::make('amount')
                    ->label('Charge the customer')
                    ->numeric()
                    ->required()
                    ->suffix($this->record->sell_currency)
                    ->default(fn () => $this->suggestedShippingCharge())
                    ->helperText('Defaults to what the freight cost you. Charge more if you are marking it up.'),
            ])
            ->action(function (array $data) {
                try {
                    $invoice = app(InvoiceWriter::class)
                        ->issueShipping($this->record, $data['amount']);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title("Shipping invoice {$invoice->number} issued")->success()->send();
            });
    }

    /** The deal's freight cost, expressed in what the customer is billed in. */
    private function suggestedShippingCharge(): float
    {
        $deal = $this->record->load('consignments');

        $base = Money::of(
            $deal->consignments->sum(fn ($c) => (float) $c->pivot->freight_share_base),
            'USD',
        );

        return $deal->sell_currency === 'USD'
            ? $base->toFloat()
            : (float) $base->times($deal->rateFor($deal->sell_currency))->amount;
    }

    /**
     * Re-derive purchases and frozen USD values from the lines.
     *
     * Runs after every save because the operator only ever states intent —
     * "this line comes from Supplier A" — and everything downstream of that is
     * this system's job, not theirs.
     */
    protected function afterSave(): void
    {
        app(DealWriter::class)->sync($this->record);
    }
}
