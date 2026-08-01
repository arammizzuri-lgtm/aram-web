<?php

namespace Tests\Feature;

use App\Actions\Sales\GenerateInvoicePdf;
use App\Models\Company;
use App\Models\Invoice;
use App\Services\Documents\PdfRenderer;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
        ]);

        $this->invoice = Invoice::with(['items.product', 'customer'])->firstOrFail();
    }

    private function html(): string
    {
        return view('pdf.invoice', [
            'invoice' => $this->invoice,
            'company' => Company::current(),
            'bankDetails' => ['bank' => 'Byblos Bank', 'account' => '0011-2233-4455'],
        ])->render();
    }

    #[Test]
    public function the_template_renders_the_document_and_its_lines(): void
    {
        $html = $this->html();

        $this->assertStringContainsString($this->invoice->number, $html);
        $this->assertStringContainsString($this->invoice->customer->name, $html);

        foreach ($this->invoice->items as $item) {
            $this->assertStringContainsString($item->product->sku, $html);
        }
    }

    /** The whole reason for using a browser engine rather than a PHP PDF library. */
    #[Test]
    public function the_template_is_bilingual(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('فاتورة', $html, 'Arabic document title');
        $this->assertStringContainsString('الإجمالي', $html, 'Arabic total label');
        $this->assertStringContainsString('المتبقي', $html, 'Arabic balance-due label');
        $this->assertStringContainsString('direction: rtl', $html, 'RTL shaping is declared');
    }

    #[Test]
    public function the_totals_block_reconciles(): void
    {
        $html = $this->html();

        $this->assertStringContainsString(number_format((float) $this->invoice->total, 2), $html);
        $this->assertStringContainsString('Balance due', $html);
    }

    #[Test]
    public function a_credit_note_is_titled_differently(): void
    {
        $this->invoice->update(['invoice_type' => 'credit_note']);

        $html = view('pdf.invoice', [
            'invoice' => $this->invoice->fresh(['items.product', 'customer']),
            'company' => Company::current(),
            'bankDetails' => null,
        ])->render();

        $this->assertStringContainsString('CREDIT NOTE', $html);
        $this->assertStringContainsString('إشعار دائن', $html);
        $this->assertStringNotContainsString('>INVOICE<', $html);
    }

    #[Test]
    public function bank_details_only_appear_when_supplied(): void
    {
        $this->assertStringContainsString('Byblos Bank', $this->html());

        $without = view('pdf.invoice', [
            'invoice' => $this->invoice,
            'company' => Company::current(),
            'bankDetails' => null,
        ])->render();

        $this->assertStringNotContainsString('Bank details', $without);
    }

    /**
     * A missing browser must say so in words somebody can act on, rather than
     * surfacing a node module-not-found stack trace to whoever clicked Download.
     */
    #[Test]
    public function a_missing_browser_produces_an_actionable_error(): void
    {
        config()->set('services.chrome.path', 'Z:\\nowhere\\chrome.exe');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('No Chrome or Chromium was found');

        app(GenerateInvoicePdf::class)->download($this->invoice);
    }

    #[Test]
    public function a_browser_is_discovered_on_this_machine(): void
    {
        config()->set('services.chrome.path', null);

        $this->assertTrue(app(PdfRenderer::class)->isAvailable());
    }
}
