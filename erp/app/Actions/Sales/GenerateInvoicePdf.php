<?php

namespace App\Actions\Sales;

use App\Models\Company;
use App\Models\Invoice;
use App\Services\Documents\PdfRenderer;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GenerateInvoicePdf
{
    public function __construct(private readonly PdfRenderer $renderer) {}

    public function download(Invoice $invoice): Response
    {
        $this->guard();

        return $this->builder($invoice)->download($this->filename($invoice));
    }

    /** Writes to disk — used for emailing and for attaching to the record. */
    public function store(Invoice $invoice, ?string $path = null): string
    {
        $this->guard();

        $path ??= 'invoices/'.$this->filename($invoice);
        $absolute = storage_path('app/'.$path);

        if (! is_dir(dirname($absolute))) {
            mkdir(dirname($absolute), 0775, true);
        }

        $this->builder($invoice)->save($absolute);

        return $path;
    }

    private function builder($invoice)
    {
        $invoice->loadMissing(['items.product', 'customer']);

        return $this->renderer->make('pdf.invoice', [
            'invoice' => $invoice,
            'company' => Company::current(),
            'bankDetails' => Company::current()?->settings['bank_details'] ?? null,
        ]);
    }

    private function filename(Invoice $invoice): string
    {
        return $invoice->number.'.pdf';
    }

    /**
     * Fail with something actionable.
     *
     * Without a browser the underlying library throws a stack trace about a
     * missing node module, which tells whoever clicked Download nothing at all.
     */
    private function guard(): void
    {
        if (! $this->renderer->isAvailable()) {
            throw new RuntimeException(
                'No Chrome or Chromium was found for PDF rendering. Install one, or set '
                .'services.chrome.path to its location.'
            );
        }
    }
}
