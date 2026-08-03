<?php

namespace Tests\Feature;

use App\Filament\Pages\ReportsPage;
use App\Filament\Widgets\SupplierProfitChart;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\SupplierPaymentWriter;
use App\Services\Reporting\BusinessMetrics;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The comparisons a row-by-row report cannot make.
 *
 * Each of these answers a question about *how* to trade rather than what was
 * traded: which supplier is worth buying from once the cost of paying them is
 * counted, which way of setting a price earns more, whether air was worth it,
 * and which deals are thin enough to be losing money quietly.
 */
class TradingComparisonTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);

        $this->customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'USD', 'is_active' => true,
        ]);
    }

    private function supplier(string $code, string $name): Supplier
    {
        return Supplier::create([
            'code' => $code, 'name' => $name, 'default_currency' => 'CNY', 'is_active' => true,
        ]);
    }

    /**
     * A deal with one line, priced in dollars so the arithmetic is readable.
     */
    private function deal(string $number, Supplier $supplier, float $cost, float $price, string $method = 'markup'): Deal
    {
        $deal = Deal::create([
            'number' => $number,
            'customer_id' => $this->customer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 1,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $supplier->id,
            'description' => 'Crystal P07',
            'quantity' => 1,
            'unit_cost' => $cost,
            'cost_currency' => 'USD',
            'unit_price' => $price,
            'pricing_method' => $method,
        ]);

        return app(DealWriter::class)->sync($deal->fresh());
    }

    private function window(): array
    {
        return [now()->subDays(30)->startOfDay(), now()->endOfDay()];
    }

    // ------------------------------------------------------------- suppliers

    #[Test]
    public function a_supplier_is_ranked_on_the_margin_its_goods_earned(): void
    {
        $good = $this->supplier('SUP-A', 'Yiwu Crystals');
        $thin = $this->supplier('SUP-B', 'Shaoxing Textiles');

        $this->deal('D-2026-0001', $good, 100, 400);
        $this->deal('D-2026-0002', $thin, 100, 130);

        [$from, $to] = $this->window();
        $rows = app(BusinessMetrics::class)->profitBySupplier($from, $to);

        $this->assertSame('Yiwu Crystals', $rows[0]['supplier']);
        $this->assertSame(300.0, $rows[0]['goods_margin']);
        $this->assertSame(30.0, $rows[1]['goods_margin']);
    }

    /**
     * The cheap supplier who is expensive to pay.
     *
     * The quoted rate is not the rate you trade at, and the difference is a
     * cost of dealing with that supplier and nobody else — so it comes off
     * their profit rather than disappearing into a company-wide total.
     */
    #[Test]
    public function what_it_costs_to_pay_a_supplier_comes_off_their_profit(): void
    {
        $supplier = $this->supplier('SUP-A', 'Yiwu Crystals');
        $deal = $this->deal('D-2026-0001', $supplier, 100, 400);

        $purchase = $deal->purchases()->firstOrFail();

        // Sent them $100; it really cost $130 to get there.
        app(SupplierPaymentWriter::class)->record(
            purchase: $purchase,
            amount: 100,
            actualCostBase: 130,
            paidAt: today()->toDateString(),
        );

        [$from, $to] = $this->window();
        $row = app(BusinessMetrics::class)->profitBySupplier($from, $to)->first();

        $this->assertSame(300.0, $row['goods_margin']);
        $this->assertSame(30.0, $row['transfer_cost']);
        $this->assertSame(270.0, $row['profit'], 'the transfer is a cost of this supplier');
    }

    #[Test]
    public function the_supplier_chart_renders_and_is_owner_only(): void
    {
        $this->deal('D-2026-0001', $this->supplier('SUP-A', 'Yiwu Crystals'), 100, 400);

        Livewire::test(SupplierProfitChart::class)
            ->assertOk()
            ->assertSee('Profit by supplier')
            ->assertSee('Yiwu Crystals');

        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        $this->assertFalse(SupplierProfitChart::canView());
    }

    #[Test]
    public function the_supplier_chart_has_an_empty_state(): void
    {
        Livewire::test(SupplierProfitChart::class)
            ->assertOk()
            ->assertSee('Nothing bought in the last 90 days');
    }

    // --------------------------------------------------------- how you priced

    /**
     * Compared on margin, not on total.
     *
     * A method used on twice as many lines wins on volume every time, and that
     * says nothing whatever about which is the better way to set a price.
     */
    #[Test]
    public function pricing_methods_are_compared_on_margin_not_volume(): void
    {
        $supplier = $this->supplier('SUP-A', 'Yiwu');

        // Typed once, at a fat margin.
        $this->deal('D-2026-0001', $supplier, 100, 400, 'manual');

        // Marked up twice, earning more in total but less per dollar.
        $this->deal('D-2026-0002', $supplier, 100, 150, 'markup');
        $this->deal('D-2026-0003', $supplier, 100, 150, 'markup');

        [$from, $to] = $this->window();
        $rows = app(BusinessMetrics::class)->marginByPricingMethod($from, $to)->keyBy('method');

        $this->assertSame(75.0, $rows['manual']['margin_percent']);
        $this->assertSame(33.3, $rows['markup']['margin_percent']);

        // Markup earned more in total and still ranks second.
        $this->assertSame(100.0, $rows['markup']['profit']);
        $this->assertSame(300.0, $rows['manual']['profit']);
        $this->assertSame(
            'manual',
            app(BusinessMetrics::class)->marginByPricingMethod($from, $to)->first()['method'],
        );
    }

    // -------------------------------------------------------------- shipping

    #[Test]
    public function shipping_is_costed_per_kilo_and_per_cubic_metre(): void
    {
        Consignment::create([
            'tracking_number' => '16940', 'mode' => 'sea', 'status' => 'arrived',
            'gross_weight_kg' => 200, 'cbm' => 2, 'shipped_at' => today(),
            'freight_amount' => 400, 'freight_currency' => 'USD', 'freight_base' => 400,
        ]);

        Consignment::create([
            'tracking_number' => '16941', 'mode' => 'air_no_battery', 'status' => 'arrived',
            'gross_weight_kg' => 50, 'cbm' => 0.5, 'shipped_at' => today(),
            'freight_amount' => 500, 'freight_currency' => 'USD', 'freight_base' => 500,
        ]);

        [$from, $to] = $this->window();
        $modes = app(BusinessMetrics::class)->shippingEconomics($from, $to)->keyBy('mode');

        // Both units for both modes — which is what makes them comparable at all.
        $this->assertSame(2.0, $modes['sea']['per_kg']);
        $this->assertSame(200.0, $modes['sea']['per_cbm']);
        $this->assertSame(10.0, $modes['air_no_battery']['per_kg']);
        $this->assertSame(1000.0, $modes['air_no_battery']['per_cbm']);
    }

    // ----------------------------------------------------------- thin deals

    #[Test]
    public function the_thinnest_deals_come_first_however_large_they_are(): void
    {
        $supplier = $this->supplier('SUP-A', 'Yiwu');

        // Big and thin: the one a monthly total hides.
        $this->deal('D-2026-0001', $supplier, 9600, 10000);
        // Small and fat.
        $this->deal('D-2026-0002', $supplier, 100, 400);

        [$from, $to] = $this->window();
        $rows = app(BusinessMetrics::class)->thinnestDeals($from, $to);

        $this->assertSame('D-2026-0001', $rows[0]['deal']);
        $this->assertSame(4.0, $rows[0]['margin_percent']);
        $this->assertSame(75.0, $rows[1]['margin_percent']);
    }

    // ------------------------------------------------------------ the screen

    #[Test]
    public function the_reports_screen_carries_all_three_comparisons(): void
    {
        $supplier = $this->supplier('SUP-A', 'Yiwu');
        $this->deal('D-2026-0001', $supplier, 100, 400, 'manual');

        Livewire::test(ReportsPage::class)
            ->assertOk()
            ->assertSee('How you priced it')
            ->assertSee('What shipping really cost')
            ->assertSee('Deals earning least');
    }

    /** They answer for whatever window the report above them is set to. */
    #[Test]
    public function the_comparisons_follow_the_reports_date_range(): void
    {
        $supplier = $this->supplier('SUP-A', 'Yiwu');

        $old = $this->deal('D-2026-0001', $supplier, 100, 400);
        $old->update(['deal_date' => Carbon::now()->subYear()]);

        $page = Livewire::test(ReportsPage::class)
            ->set('from', Carbon::now()->subDays(7)->toDateString())
            ->set('to', Carbon::now()->toDateString());

        $this->assertCount(0, $page->instance()->comparisons()['thin']);

        $page->set('from', Carbon::now()->subYears(2)->toDateString());

        $this->assertCount(1, $page->instance()->comparisons()['thin']);
    }
}
