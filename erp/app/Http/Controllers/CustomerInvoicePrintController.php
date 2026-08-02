<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CustomerInvoice;
use Illuminate\Contracts\View\View;

/**
 * The customer's copy, opened in the browser to be printed or saved as a PDF.
 *
 * The ERP renders invoices through headless Chromium, because bidirectional
 * text is what a browser engine is for: a PHP PDF library reverses Sorani and
 * disconnects its letterforms. But this system is deployed on shared hosting
 * with no shell, no Node and no way to install a browser — so the server has no
 * engine to render with, and the PDF button could only ever apologise.
 *
 * Whoever clicked it, however, is sitting in front of one. The same template is
 * served to their browser, which lays it out exactly as Chromium would have —
 * same flex layout, same A4 page box, same letter joining — and prints it or
 * writes the PDF from the print dialog.
 *
 * Server-side rendering is untouched and still used where there is nobody to
 * press a button: attaching an invoice to a record, or emailing one.
 */
class CustomerInvoicePrintController extends Controller
{
    public function __invoke(CustomerInvoice $invoice): View
    {
        $invoice->load(['lines', 'customer', 'deal']);

        return view('pdf.customer-invoice', [
            'invoice' => $invoice,
            'company' => Company::current(),
            'autoPrint' => true,
        ]);
    }
}
