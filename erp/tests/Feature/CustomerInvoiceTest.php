<?php

namespace Tests\Feature;

use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\CustomerPaymentAllocation;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\InvoiceWriter;
use App\Services\Shipping\ConsignmentWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The documents the customer receives.
 *
 * The thing worth testing hardest is that they do not move. A customer holding
 * a printed invoice and you looking at a screen must never see different
 * numbers, whatever has happened to the deal since.
 */
class CustomerInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Deal $deal;

    private InvoiceWriter $writer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $owner->assignRole('owner');
        $this->actingAs($owner);

        $customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading', 'name_ku' => 'عەلی',
            'default_currency' => 'IQD', 'document_language' => 'ckb', 'is_active' => true,
        ]);

        $supplier = Supplier::create(['code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY']);

        $this->deal = Deal::create([
            'number' => 'D-2026-0001',
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        DealLine::create([
            'deal_id' => $this->deal->id,
            'supplier_id' => $supplier->id,
            'description' => 'Crystal P07 20mm · Gold',
            'description_ku' => 'کریستاڵ ٢٠م.م',
            'quantity' => 500,
            'unit_cost' => 12.50,
            'cost_currency' => 'CNY',
            'unit_price' => 28000,
        ]);

        $this->deal = app(DealWriter::class)->sync($this->deal->fresh());
        $this->writer = app(InvoiceWriter::class);
    }

    // ----------------------------------------------------------------- goods

    #[Test]
    public function a_goods_invoice_copies_the_deals_lines_in_the_customers_currency(): void
    {
        $invoice = $this->writer->issueGoods($this->deal);

        $this->assertSame('goods', $invoice->type);
        $this->assertSame('issued', $invoice->status);
        $this->assertSame('IQD', $invoice->currency);
        $this->assertSame('14000000.0000', $invoice->total);
        // 14,000,000 / 1,470 = 9,523.8095
        $this->assertSame(9523.81, round((float) $invoice->total_base, 2));

        $line = $invoice->lines()->first();
        $this->assertSame('Crystal P07 20mm · Gold', $line->description);
        $this->assertSame('28000.0000', $line->unit_price);
    }

    /** A customer document must not carry cost, even in the row behind it. */
    #[Test]
    public function no_cost_appears_anywhere_on_an_invoice_line(): void
    {
        $invoice = $this->writer->issueGoods($this->deal);
        $columns = array_keys($invoice->lines()->first()->getAttributes());

        foreach (['unit_cost', 'cost_currency', 'cost_total_base', 'markup_percent'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns);
        }
    }

    #[Test]
    public function the_language_comes_from_the_customer_so_printing_never_asks(): void
    {
        $invoice = $this->writer->issueGoods($this->deal);

        $this->assertSame('ckb', $invoice->language);
        $this->assertTrue($invoice->isRightToLeft());
    }

    /** Billing the same goods twice is nearly always a mistake. */
    #[Test]
    public function a_second_goods_invoice_is_refused(): void
    {
        $this->writer->issueGoods($this->deal);

        $this->expectException(RuntimeException::class);
        $this->writer->issueGoods($this->deal->fresh());
    }

    #[Test]
    public function a_deal_with_no_items_cannot_be_invoiced(): void
    {
        $empty = Deal::create([
            'number' => 'D-2026-0002',
            'customer_id' => $this->deal->customer_id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'iqd_usd_rate' => 1470,
        ]);

        $this->expectException(RuntimeException::class);
        $this->writer->issueGoods($empty);
    }

    /**
     * The commission is charged as its own line.
     *
     * Spreading it across the goods would misstate every unit price on the
     * page — and the customer would notice the arithmetic not working.
     */
    #[Test]
    public function a_deal_commission_appears_as_its_own_line(): void
    {
        $this->deal->update(['deal_commission' => 500, 'deal_commission_currency' => 'USD']);

        $invoice = $this->writer->issueGoods($this->deal->fresh());

        $this->assertSame(2, $invoice->lines()->count());

        // The relation already orders by display_order, so take the last rather
        // than adding a second, ineffective ordering clause.
        $commission = $invoice->lines()->get()->last();

        $this->assertSame('Service & handling', $commission->description);
        // $500 at 1,470 to the dollar.
        $this->assertSame('735000.0000', $commission->unit_price);
        $this->assertSame('14735000.0000', $invoice->total);
    }

    // -------------------------------------------------------------- snapshot

    /**
     * The whole reason invoices are copies.
     *
     * Edit the deal afterwards — the document the customer holds must not move.
     */
    #[Test]
    public function editing_the_deal_afterwards_cannot_change_an_issued_invoice(): void
    {
        $invoice = $this->writer->issueGoods($this->deal);

        $this->deal->lines()->first()->update([
            'unit_price' => 35000,
            'description' => 'Crystal P07 20mm · Silver',
            'quantity' => 800,
        ]);

        $line = $invoice->fresh()->lines()->first();

        $this->assertSame('28000.0000', $line->unit_price);
        $this->assertSame('500.0000', $line->quantity);
        $this->assertSame('Crystal P07 20mm · Gold', $line->description);
        $this->assertSame('14000000.0000', $invoice->fresh()->total);
    }

    // ------------------------------------------------------------- shipping

    /**
     * Shipping is a second document, issued when the cost is finally known.
     *
     * Adding it to the goods invoice would mean changing a document already
     * sent, which is the one thing that must never happen.
     */
    #[Test]
    public function shipping_is_billed_separately_once_the_freight_is_known(): void
    {
        $this->writer->issueGoods($this->deal);

        $consignment = Consignment::create([
            'tracking_number' => '16940',
            'mode' => 'sea',
            'cbm' => 0.11,
            'status' => 'arrived',
            'freight_amount' => 600,
            'freight_currency' => 'USD',
        ]);
        $consignment->deals()->attach($this->deal->id);
        app(ConsignmentWriter::class)->applyWholeBillToSoleDeal($consignment->fresh());

        $invoice = $this->writer->issueShipping($this->deal->fresh());

        $this->assertSame('shipping', $invoice->type);
        // $600 at 1,470 to the dollar.
        $this->assertSame('882000.0000', $invoice->total);
        $this->assertStringContainsString('16940', $invoice->lines()->first()->specification);

        // Two documents, one deal.
        $this->assertSame(2, $this->deal->invoices()->count());
    }

    #[Test]
    public function a_shipping_charge_can_exceed_what_the_freight_cost_you(): void
    {
        $invoice = $this->writer->issueShipping($this->deal, amount: 1_000_000);

        $this->assertSame('1000000.0000', $invoice->total);
    }

    #[Test]
    public function shipping_cannot_be_billed_before_any_freight_is_recorded(): void
    {
        $this->expectException(RuntimeException::class);
        $this->writer->issueShipping($this->deal);
    }

    // ------------------------------------------------------------ cancelling

    #[Test]
    public function cancelling_keeps_the_document_and_records_why(): void
    {
        $invoice = $this->writer->issueGoods($this->deal);

        $cancelled = $this->writer->cancel($invoice, 'Wrong quantity agreed');

        $this->assertSame('cancelled', $cancelled->status);
        $this->assertSame('Wrong quantity agreed', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->cancelled_at);
        // Still there — the customer has their copy either way.
        $this->assertDatabaseHas('customer_invoices', ['id' => $invoice->id]);
    }

    #[Test]
    public function cancelling_frees_the_deal_to_be_invoiced_again(): void
    {
        $first = $this->writer->issueGoods($this->deal);
        $this->writer->cancel($first, 'Reissuing');

        $second = $this->writer->issueGoods($this->deal->fresh());

        $this->assertNotSame($first->number, $second->number);
        $this->assertSame('issued', $second->status);
    }

    /** Money already matched to it means the balance depends on it standing. */
    #[Test]
    public function an_invoice_with_money_matched_to_it_cannot_be_cancelled(): void
    {
        $invoice = $this->writer->issueGoods($this->deal);

        $payment = CustomerPayment::create([
            'customer_id' => $this->deal->customer_id,
            'number' => 'CP-2026-0001',
            'amount' => 14_000_000,
            'currency' => 'IQD',
            'exchange_rate' => 1470,
            'base_amount' => 9523.81,
            'paid_at' => today(),
        ]);

        CustomerPaymentAllocation::create([
            'customer_payment_id' => $payment->id,
            'customer_invoice_id' => $invoice->id,
            'amount' => 14_000_000,
            'base_amount' => 9523.81,
        ]);

        $this->expectException(RuntimeException::class);
        $this->writer->cancel($invoice->fresh());
    }

    // ------------------------------------------------------------------- pdf

    /**
     * Sorani is a mirrored layout, not a translation.
     *
     * Asserted on the rendered HTML rather than the PDF bytes: this is the
     * layer where getting it wrong is possible, and checking it here is both
     * precise and fast enough to run on every commit.
     */
    #[Test]
    public function a_kurdish_invoice_renders_right_to_left(): void
    {
        $invoice = $this->writer->issueGoods($this->deal)->load(['lines', 'customer', 'deal']);

        $html = view('pdf.customer-invoice', [
            'invoice' => $invoice,
            'company' => null,
        ])->render();

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('lang="ckb"', $html);
        $this->assertStringContainsString('پسوڵە', $html, 'the heading is in Kurdish');
        $this->assertStringContainsString('Noto Naskh Arabic', $html, 'an Arabic-script font is asked for');
    }

    #[Test]
    public function an_english_invoice_renders_left_to_right(): void
    {
        $this->deal->customer->update(['document_language' => 'en']);

        $invoice = $this->writer->issueGoods($this->deal->fresh())->load(['lines', 'customer', 'deal']);

        $html = view('pdf.customer-invoice', ['invoice' => $invoice, 'company' => null])->render();

        $this->assertStringContainsString('dir="ltr"', $html);
        $this->assertStringContainsString('INVOICE', $html);
        $this->assertStringNotContainsString('dir="rtl"', $html);
    }

    /**
     * Identifiers stay left-to-right even on a mirrored page.
     *
     * An invoice number reversed by the bidi algorithm is unreadable to
     * everyone, including the person who issued it.
     */
    #[Test]
    public function numbers_and_dates_stay_left_to_right_on_a_mirrored_page(): void
    {
        $invoice = $this->writer->issueGoods($this->deal)->load(['lines', 'customer', 'deal']);

        $html = view('pdf.customer-invoice', ['invoice' => $invoice, 'company' => null])->render();

        $this->assertMatchesRegularExpression(
            '/dir="ltr"[^>]*>\s*<div class="doc-title">/s',
            $html,
            'the document number block is forced LTR',
        );
    }

    /** The customer's own language wins wherever a translation exists. */
    #[Test]
    public function kurdish_names_are_preferred_on_a_kurdish_invoice(): void
    {
        $this->deal->lines()->first()->update(['description_ku' => 'کریستاڵ ٢٠م.م']);

        $invoice = $this->writer->issueGoods($this->deal->fresh())->load(['lines', 'customer', 'deal']);

        $html = view('pdf.customer-invoice', ['invoice' => $invoice, 'company' => null])->render();

        $this->assertStringContainsString('عەلی', $html, 'the customer name');
        $this->assertStringContainsString('کریستاڵ', $html, 'the item description');
    }

    /** No cost reaches the page, whatever the deal knows. */
    #[Test]
    public function a_rendered_invoice_contains_no_cost_figures(): void
    {
        $invoice = $this->writer->issueGoods($this->deal)->load(['lines', 'customer', 'deal']);

        $html = view('pdf.customer-invoice', ['invoice' => $invoice, 'company' => null])->render();

        // ¥12.50 cost, and the $868.06 it converts to. Neither belongs here.
        $this->assertStringNotContainsString('12.50', $html);
        $this->assertStringNotContainsString('868', $html);
        $this->assertStringContainsString('14,000,000', $html, 'but the price does');
    }

    #[Test]
    public function a_cancelled_invoice_says_so_on_the_page(): void
    {
        $invoice = $this->writer->issueGoods($this->deal);
        $this->writer->cancel($invoice, 'Reissuing');

        $html = view('pdf.customer-invoice', [
            'invoice' => $invoice->fresh()->load(['lines', 'customer', 'deal']),
            'company' => null,
        ])->render();

        $this->assertStringContainsString('هەڵوەشاوەتەوە', $html);
    }

    // -------------------------------------------------------------- balances

    #[Test]
    public function an_issued_invoice_becomes_what_the_customer_owes(): void
    {
        $customer = $this->deal->customer;

        $this->assertSame(0.0, $customer->outstandingBalance());

        $this->writer->issueGoods($this->deal);

        $this->assertSame(9523.81, $customer->fresh()->outstandingBalance());
    }

    #[Test]
    public function a_cancelled_invoice_stops_counting_towards_the_balance(): void
    {
        $invoice = $this->writer->issueGoods($this->deal);
        $customer = $this->deal->customer;

        $this->assertSame(9523.81, $customer->fresh()->outstandingBalance());

        $this->writer->cancel($invoice, 'Reissuing');

        $this->assertSame(0.0, $customer->fresh()->outstandingBalance());
    }
}
