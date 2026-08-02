<?php

namespace App\Filament\Resources\CustomerInvoices\Pages;

use App\Filament\Actions\RecordDeletion;
use App\Filament\Resources\CustomerInvoices\CustomerInvoiceResource;
use App\Models\CustomerInvoice;
use App\Services\Deals\InvoiceWriter;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Throwable;

class ManageCustomerInvoices extends ManageRecords
{
    protected static string $resource = CustomerInvoiceResource::class;

    protected function getTableActions(): array
    {
        return [
            $this->printAction(),
            EditAction::make(),
            $this->cancelAction(),
            ...RecordDeletion::actions(),
        ];
    }

    /**
     * Render the customer's copy.
     *
     * The language comes from the invoice, which took it from the customer, so
     * printing never asks a question that was already answered when the
     * customer was set up.
     */
    private function printAction(): Action
    {
        return Action::make('print')
            ->label('Print')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->tooltip('Opens the invoice to print, or to save as a PDF')
            /*
             * Opened in a tab rather than rendered on the server.
             *
             * The invoice is drawn by a browser engine because that is what
             * lays out Sorani correctly, and this host has none: no shell, no
             * Node, nowhere to install Chromium. It could only ever have
             * apologised — which is what it did.
             *
             * Whoever clicks this is sitting in front of a browser, though, and
             * the print dialog it raises writes the same PDF from the same
             * template. See CustomerInvoicePrintController.
             */
            ->url(fn (CustomerInvoice $record) => route('filament.admin.invoices.print', $record), shouldOpenInNewTab: true);
    }

    /**
     * Withdraw an invoice, keeping the document and the reason.
     *
     * Deleting would leave a gap in the numbering and no account of why — and
     * the customer still has their copy either way.
     */
    private function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-x-circle')
            ->color('danger')
            ->visible(fn (CustomerInvoice $record) => $record->status !== 'cancelled')
            ->modalHeading(fn (CustomerInvoice $record) => "Cancel {$record->number}")
            ->modalDescription(
                'The document stays on record, marked cancelled. It stops counting '
                .'towards what the customer owes.'
            )
            ->schema([
                Textarea::make('reason')
                    ->label('Why?')
                    ->placeholder('e.g. wrong quantity agreed, reissuing')
                    ->rows(2)
                    ->required(),
            ])
            ->action(function (CustomerInvoice $record, array $data) {
                try {
                    app(InvoiceWriter::class)->cancel($record, $data['reason']);
                } catch (Throwable $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title("{$record->number} cancelled")->success()->send();
            });
    }
}
