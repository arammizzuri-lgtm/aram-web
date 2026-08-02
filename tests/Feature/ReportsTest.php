<?php

namespace Tests\Feature;

use App\Filament\Pages\ReportsPage;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\InvoiceWriter;
use App\Services\Deals\SupplierPaymentWriter;
use App\Services\Reporting\ReportBuilder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The reports, and who is allowed to run them.
 *
 * The access half matters as much as the arithmetic: a report is the easiest
 * place for a cost column to escape a screen that carefully hid it.
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Deal $deal;

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
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'IQD', 'is_active' => true,
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
            'description' => 'Crystal P07 20mm',
            'quantity' => 500,
            'unit_cost' => 12.50,
            'cost_currency' => 'CNY',
            'unit_price' => 28000,
        ]);

        $this->deal = app(DealWriter::class)->sync($this->deal->fresh());
    }

    private function builder(): ReportBuilder
    {
        return app(ReportBuilder::class);
    }

    private function rows(string $report)
    {
        return $this->builder()->rows($report, now()->subDays(90)->startOfDay(), now()->endOfDay());
    }

    // ---------------------------------------------------------------- content

    #[Test]
    public function profit_by_deal_reports_the_real_figures(): void
    {
        $row = $this->rows('profit_by_deal')->first();

        // 500 x 28,000 IQD / 1,470 = 9,523.81 ; 6,250 CNY / 7.2 = 868.06
        $this->assertSame('D-2026-0001', $row[0]);
        $this->assertSame('Ali Trading', $row[1]);
        $this->assertSame(9523.81, round($row[3], 2));
        $this->assertSame(868.06, round($row[4], 2));
        $this->assertSame(8655.75, round($row[5], 2));
    }

    #[Test]
    public function profit_by_customer_groups_the_deals(): void
    {
        $row = $this->rows('profit_by_customer')->first();

        $this->assertSame('Ali Trading', $row[0]);
        $this->assertSame(1, $row[1], 'one deal');
        $this->assertSame(8655.75, round($row[4], 2));
    }

    /**
     * Said plainly, not shown as a precise-looking number.
     *
     * A lump commission belongs to the deal, so any way of spreading it across
     * products is a guess. The report says which it is.
     */
    #[Test]
    public function profit_by_product_states_whether_it_is_exact(): void
    {
        $this->assertSame('Exact', $this->rows('profit_by_product')->first()[5]);

        $this->deal->update(['deal_commission' => 500, 'deal_commission_currency' => 'USD']);

        $this->assertSame('Approximate', $this->rows('profit_by_product')->first()[5]);
    }

    #[Test]
    public function receivables_lists_only_what_is_still_owed(): void
    {
        $this->assertCount(0, $this->rows('receivables'));

        app(InvoiceWriter::class)->issueGoods($this->deal);

        $row = $this->rows('receivables')->first();

        $this->assertSame('Ali Trading', $row[1]);
        $this->assertSame(9523.81, round($row[5], 2), 'still due');
    }

    #[Test]
    public function payables_lists_only_purchases_with_money_outstanding(): void
    {
        $this->assertCount(1, $this->rows('payables'));

        app(SupplierPaymentWriter::class)->record($this->deal->purchases()->first(), 6250, 'CNY');

        $this->assertCount(0, $this->rows('payables'), 'settled purchases drop off');
    }

    /** The figure that is invisible everywhere else. */
    #[Test]
    public function the_transfer_report_shows_what_the_exchange_took(): void
    {
        app(SupplierPaymentWriter::class)
            ->record($this->deal->purchases()->first(), 6250, 'CNY', actualCostBase: 890.00);

        $row = $this->rows('transfer_losses')->first();

        $this->assertSame('Yiwu', $row[1]);
        $this->assertSame(868.06, round($row[4], 2), 'at the quoted rate');
        $this->assertSame(890.0, round($row[5], 2), 'what it really cost');
        $this->assertSame(21.94, round($row[6], 2), 'the difference');
    }

    /** Nothing recorded means nothing to report — not a row of zeroes. */
    #[Test]
    public function payments_with_no_recorded_transfer_cost_are_left_out(): void
    {
        app(SupplierPaymentWriter::class)->record($this->deal->purchases()->first(), 6250, 'CNY');

        $this->assertCount(0, $this->rows('transfer_losses'));
    }

    // ----------------------------------------------------------------- access

    #[Test]
    public function the_owner_can_run_every_report(): void
    {
        $available = array_keys(Livewire::test(ReportsPage::class)->instance()->available());

        $this->assertContains('profit_by_deal', $available);
        $this->assertContains('payables', $available);
        $this->assertContains('transfer_losses', $available);
    }

    /**
     * Withheld, not blanked.
     *
     * A profit report with the money removed is a list of deal numbers, which
     * invites exactly the question the permission exists to prevent.
     */
    #[Test]
    public function the_assistant_is_offered_only_the_reports_without_cost_in_them(): void
    {
        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        $available = array_keys(Livewire::test(ReportsPage::class)->instance()->available());

        $this->assertSame(['receivables'], $available);
    }

    #[Test]
    public function the_assistant_lands_on_a_report_they_are_allowed_to_see(): void
    {
        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant2@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        // The default is a cost report, so mount must move them off it.
        $page = Livewire::test(ReportsPage::class);

        $this->assertSame('receivables', $page->get('report'));
        $page->assertOk();
    }

    // ----------------------------------------------------------------- export

    #[Test]
    public function the_export_carries_the_same_columns_as_the_table(): void
    {
        app(InvoiceWriter::class)->issueGoods($this->deal);

        $page = Livewire::test(ReportsPage::class)->set('report', 'receivables');

        $response = $page->instance()->export();
        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringContainsString('Invoice,Customer,Date,Total,Paid,"Still due",Days', $csv);
        $this->assertStringContainsString('Ali Trading', $csv);
    }

    // ----------------------------------------------------------------- screen

    #[Test]
    public function the_page_renders_each_report(): void
    {
        app(InvoiceWriter::class)->issueGoods($this->deal);

        Livewire::test(ReportsPage::class)
            ->assertOk()
            ->assertSee('Profit by deal')
            ->assertSee('D-2026-0001')
            ->set('report', 'receivables')
            ->assertSee('Who owes you')
            ->assertSee('Ali Trading');
    }

    #[Test]
    public function a_range_with_nothing_in_it_says_so(): void
    {
        Livewire::test(ReportsPage::class)
            ->set('from', '2020-01-01')
            ->set('to', '2020-12-31')
            ->assertSee('Nothing in this range');
    }
}
