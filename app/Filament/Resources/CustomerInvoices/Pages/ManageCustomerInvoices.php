<?php

namespace App\Filament\Resources\CustomerInvoices\Pages;

use App\Filament\Resources\CustomerInvoices\CustomerInvoiceResource;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Services\Deals\InvoiceWriter;
use App\Services\Documents\PdfRenderer;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;
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
            ->label('PDF')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->action(function (CustomerInvoice $record) {
                $renderer = app(PdfRenderer::class);

                if (! $renderer->isAvailable()) {
                    Notification::make()
                        ->title('No browser available for rendering')
                        ->body('PDFs are drawn by headless Chromium. Set CHROME_PATH in .env.')
                        ->danger()
                        ->send();

                    return null;
                }

                $record->load(['lines', 'customer', 'deal']);

                $pdf = $renderer->make('pdf.customer-invoice', [
                    'invoice' => $record,
                    'company' => Company::current(),
                ]);

                return new StreamedResponse(
                    fn () => print ($pdf->base64()
                        ? base64_decode($pdf->base64())
                        : ''),
                    200,
                    [
                        'Content-Type' => 'application/pdf',
                        'Content-Disposition' => 'inline; filename="'.$record->number.'.pdf"',
                    ],
                );
            });
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
