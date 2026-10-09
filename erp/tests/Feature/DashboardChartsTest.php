<?php

namespace Tests\Feature;

use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Filament\Widgets\CategoryProfitChart;
use App\Filament\Widgets\CurrencyExposureWidget;
use App\Filament\Widgets\PipelineWidget;
use App\Filament\Widgets\ProfitByMonthChart;
use App\Filament\Widgets\SupplierProfitChart;
use App\Filament\Widgets\TopCustomersChart;
use App\Models\CatalogueItem;
use App\Models\CrystalProduct;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\PriceListSection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\SupplierPaymentWriter;
use App\Services\Reporting\BusinessMetrics;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Url;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tests\TestCase;

/**
 * The dashboard charts — the figures under them, and what they draw.
 *
 * The waterfall, the rankings, the category and currency views. Each test
 * builds real deals through DealWriter, the way the screens do, and reads back
 * what the owner would see: a step down for a losing month, a share of the
 * profit, a currency at its own amount, a click that opens the right list.
 */
class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Supplier $supplier;

    /** @var array<string, int> section code => id */
    private array $sections = [];

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

        $this->customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading', 'default_currency' => 'USD', 'is_active' => true,
        ]);

        $this->supplier = Supplier::create(['code' => 'SUP-A', 'name' => 'Yiwu Crystals', 'default_currency' => 'CNY']);

        foreach (['crystals' => 'Crystals', 'textile' => 'Textile', 'packaging' => 'Packaging', 'furniture' => 'Furniture'] as $code => $name) {
            $this->sections[$code] = PriceListSection::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'sort_order' => count($this->sections), 'is_active' => true],
            )->id;
        }
    }

    /**
     * A deal, synced the way the deal screen syncs it, then closed.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function deal(string $number, string $date, array $lines, string $currency = 'USD', ?Customer $customer = null): Deal
    {
        $deal = Deal::create([
            'number' => $number,
            'customer_id' => ($customer ?? $this->customer)->id,
            'deal_date' => $date,
            'sell_currency' => $currency,
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        foreach ($lines as $line) {
            DealLine::create([
                'deal_id' => $deal->id,
                'supplier_id' => $this->supplier->id,
                'description' => 'Goods',
                'quantity' => 10,
                'cost_currency' => 'USD',
                ...$line,
            ]);
        }

        app(DealWriter::class)->sync($deal->fresh());
        $deal->fresh()->update(['status' => 'closed']);

        return $deal->fresh();
    }

    /** One line: ten units, costing 10 each, sold at $price — so profit is 10 × ($price − 10). */
    private function line(float $price, array $extra = []): array
    {
        return ['unit_cost' => 10, 'unit_price' => $price, ...$extra];
    }

    // ------------------------------------------------------------ the figures

    /** A month with nothing in it is still a month: zero, not missing. */
    #[Test]
    public function months_are_grouped_and_an_empty_one_reads_zero(): void
    {
        $this->deal('D-1', '2026-08-10', [$this->line(15)]);   // +50
        $this->deal('D-2', '2026-10-05', [$this->line(8)]);    // −20

        $months = app(BusinessMetrics::class)->profitByMonth(
            Carbon::parse('2026-08-15')->startOfDay(),
            Carbon::parse('2026-10-31')->endOfDay(),
        );

        $this->assertSame(['Aug', 'Sep', 'Oct'], $months->pluck('label')->all());
        $this->assertSame([0.0, 0.0, -20.0], $months->pluck('profit')->all(),
            'the August deal falls before the window opens on the 15th');
        $this->assertSame(0, $months[1]['deals']);

        // The first month is clamped to the window, so its link opens only those days.
        $this->assertSame('2026-08-15', $months[0]['from']->toDateString());
        $this->assertSame('2026-08-31', $months[0]['to']->toDateString());
    }

    /**
     * A line belongs to the section it was picked from — a crystal, a catalogue
     * item, a product — and a line typed by hand belongs to none.
     */
    #[Test]
    public function a_line_belongs_to_the_section_it_was_picked_from(): void
    {
        $crystal = CrystalProduct::create([
            'supplier_id' => $this->supplier->id, 'crystal_code' => 'P01', 'crystal_name' => 'Crystal', 'finish' => 'plain',
        ]);
        $fabric = CatalogueItem::create([
            'price_list_section_id' => $this->sections['textile'], 'supplier_id' => $this->supplier->id,
            'code' => 'T-1', 'name' => 'Fabric roll',
        ]);
        $box = Product::create([
            'sku' => 'PKG-1', 'name' => 'Gift box', 'price_list_section_id' => $this->sections['packaging'],
        ]);

        $this->deal('D-1', today()->toDateString(), [
            $this->line(15, ['crystal_product_id' => $crystal->id]),
            $this->line(15, ['catalogue_item_id' => $fabric->id]),
            $this->line(15, ['product_id' => $box->id]),
            $this->line(15, ['description' => 'Pearl strings, typed by hand']),
        ]);

        $categories = app(BusinessMetrics::class)
            ->profitByCategory(now()->subDays(30), now()->endOfDay())
            ->keyBy('category');

        $this->assertEqualsCanonicalizing(
            ['Crystals', 'Textile', 'Packaging', 'Not linked to a product'],
            $categories->keys()->all(),
        );
        $this->assertSame(50.0, $categories['Crystals']['profit']);
        $this->assertSame($this->sections['textile'], $categories['Textile']['section_id']);
        $this->assertNull($categories['Not linked to a product']['section_id']);
    }

    /** Converting quietly is how a yuan exposure reads as a dollar one — so it does not. */
    #[Test]
    public function exposure_keeps_each_currency_at_its_own_amount(): void
    {
        // Sold in dollars, bought in yuan: 10 × ¥72 = ¥720, which is $100 at 7.2.
        $this->deal('D-1', today()->toDateString(), [['unit_cost' => 72, 'cost_currency' => 'CNY', 'unit_price' => 15]]);
        // Sold in dinars: 10 × 22,050 = 220,500 IQD, which is $150 at 1,470.
        $this->deal('D-2', today()->toDateString(), [['unit_cost' => 10, 'unit_price' => 22050]], 'IQD');

        $exposure = app(BusinessMetrics::class)->currencyExposure(now()->subDays(30), now()->endOfDay());

        $this->assertSame(['USD', 'IQD'], $exposure['sold']->pluck('currency')->all());
        $this->assertSame(220500.0, $exposure['sold'][1]['original']);
        $this->assertSame(150.0, $exposure['sold'][1]['base']);
        $this->assertSame(50.0, $exposure['sold'][1]['share']);

        // Fixed order, so a currency sits in the same place — and keeps its colour — on both bars.
        $this->assertSame(['USD', 'CNY'], $exposure['bought']->pluck('currency')->all());
        $this->assertSame(720.0, $exposure['bought'][1]['original']);
        $this->assertSame(100.0, $exposure['bought'][1]['base']);
    }

    /**
     * Profit by product, with more than one deal in the period.
     *
     * It asked each line's deal whether it carried a discount, and that reads
     * the deal's purchases — not loaded, so with lazy loading off the report
     * threw the moment a period held two deals. Its own test only ever had one.
     */
    #[Test]
    public function profit_by_product_survives_a_period_with_several_deals(): void
    {
        $this->deal('D-1', today()->toDateString(), [$this->line(15)]);
        $this->deal('D-2', today()->toDateString(), [$this->line(20)]);

        $rows = app(BusinessMetrics::class)->profitByProduct(now()->subDays(30), now()->endOfDay());

        $this->assertSame(150.0, $rows->sum('profit'));
    }

    // ----------------------------------------------------------- the waterfall

    /** Each month stands where the last ended; a loss steps down; the last column is the total. */
    #[Test]
    public function the_waterfall_steps_down_for_a_losing_month_and_ends_on_the_total(): void
    {
        $this->deal('D-1', '2026-08-10', [$this->line(15)]);   // +50
        $this->deal('D-2', '2026-10-05', [$this->line(8)]);    // −20

        $chart = Livewire::test(ProfitByMonthChart::class)
            ->set('widgetRange', 'custom')
            ->set('widgetRangeStart', '2026-08-01')
            ->set('widgetRangeEnd', '2026-10-31')
            ->assertSee('How the profit was built')
            ->instance()
            ->chart();

        [$august, $september, $october, $total] = $chart['columns']->all();

        $this->assertTrue($august['up']);
        $this->assertTrue($september['empty'], 'a month with no deals is a flat stretch, not a bar');
        $this->assertFalse($october['up']);
        $this->assertSame('var(--erp-critical)', $october['colour']);

        $this->assertSame('total', $total['kind']);
        $this->assertSame(30.0, $chart['total']);
        // The run ends exactly where the total stands.
        $this->assertSame($october['level'], $total['top']);
    }

    /** Click August and you get August's deals — the only answer to "why that month?". */
    #[Test]
    public function a_month_opens_the_deals_struck_in_it(): void
    {
        $august = $this->deal('D-1', '2026-08-10', [$this->line(15)]);
        $october = $this->deal('D-2', '2026-10-05', [$this->line(8)]);

        $url = Livewire::test(ProfitByMonthChart::class)
            ->set('widgetRange', 'custom')
            ->set('widgetRangeStart', '2026-08-01')
            ->set('widgetRangeEnd', '2026-10-31')
            ->instance()
            ->chart()['columns'][0]['url'];

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame(['from' => '2026-08-01', 'until' => '2026-08-31'], $query['filters']['deal_date']);

        Livewire::test(ListDeals::class)
            ->filterTable('deal_date', ['from' => '2026-08-01', 'until' => '2026-08-31'])
            ->assertCanSeeTableRecords([$august])
            ->assertCanNotSeeTableRecords([$october]);
    }

    /**
     * A link into a list uses the key that list actually reads.
     *
     * Filament v4 binds a list's filters to `?filters=…`. The pipeline linked
     * with `?tableFilters=…`, which nothing reads — so every stage opened every
     * deal, and nothing said so. Read off the page class itself, so a future
     * rename fails here rather than quietly in the browser.
     */
    #[Test]
    public function dashboard_links_use_the_query_key_the_lists_read(): void
    {
        $key = (new ReflectionProperty(ListRecords::class, 'tableFilters'))
            ->getAttributes(Url::class)[0]->getArguments()['as'];

        $this->deal('D-1', today()->toDateString(), [$this->line(15)]);

        $links = [
            Livewire::test(PipelineWidget::class)->instance()->stages()->first()['url'],
            Livewire::test(ProfitByMonthChart::class)->instance()->chart()['columns'][0]['url'],
            Livewire::test(SupplierProfitChart::class)->instance()->ranking()['rows'][0]['url'],
        ];

        foreach ($links as $link) {
            parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
            $this->assertArrayHasKey($key, $query, $link);
        }
    }

    // ---------------------------------------------------------------- rankings

    /** The strip says how concentrated the profit is, in words as well as shape. */
    #[Test]
    public function the_customer_strip_says_how_concentrated_the_profit_is(): void
    {
        $other = Customer::create(['code' => 'C-002', 'name' => 'Noor Design', 'default_currency' => 'USD', 'is_active' => true]);

        $this->deal('D-1', today()->toDateString(), [$this->line(40)]);                   // revenue 400, profit 300
        $this->deal('D-2', today()->toDateString(), [$this->line(20)], 'USD', $other);    // revenue 200, profit 100

        $ranking = Livewire::test(TopCustomersChart::class)->instance()->ranking();

        $this->assertSame('Ali Trading brings 75% of the profit.', $ranking['strip']['caption']);
        $this->assertSame([75.0, 25.0], array_column($ranking['strip']['segments'], 'width'));
        $this->assertSame('var(--erp-series-1)', $ranking['strip']['segments'][0]['colour'], 'the leader is picked out');

        // Profit drawn inside revenue, both against the widest figure in the list.
        $this->assertSame([100.0, 75.0], array_column($ranking['rows'][0]['track'], 'width'));
        $this->assertSame([50.0, 25.0], array_column($ranking['rows'][1]['track'], 'width'));
    }

    /** The slice the exchange house took is drawn at the end of the margin, not hidden in a footnote. */
    #[Test]
    public function a_supplier_bar_marks_what_the_transfer_took(): void
    {
        $deal = $this->deal('D-1', today()->toDateString(), [['unit_cost' => 10, 'unit_price' => 40]]); // margin 300

        // Sent them ¥720 — $100 at the deal's 7.2 — and it really cost $130 to get there.
        app(SupplierPaymentWriter::class)->record(
            purchase: $deal->purchases()->firstOrFail(),
            amount: 720,
            actualCostBase: 130,
            paidAt: today()->toDateString(),
        );

        $row = Livewire::test(SupplierProfitChart::class)->instance()->ranking()['rows'][0];

        $this->assertSame('$270.00', $row['valueText']);
        $this->assertSame(
            [['width' => 100.0, 'colour' => 'var(--erp-serious)'], ['width' => 90.0, 'colour' => 'var(--erp-good)']],
            $row['track'],
        );
        $this->assertStringContainsString('$30.00 lost on transfers', $row['detail']);
    }

    // ------------------------------------------------------------ new charts

    #[Test]
    public function the_category_and_currency_charts_render_with_a_table_behind_them(): void
    {
        $crystal = CrystalProduct::create([
            'supplier_id' => $this->supplier->id, 'crystal_code' => 'P01', 'crystal_name' => 'Crystal', 'finish' => 'plain',
        ]);
        $this->deal('D-1', today()->toDateString(), [
            ['unit_cost' => 72, 'cost_currency' => 'CNY', 'unit_price' => 15, 'crystal_product_id' => $crystal->id],
        ]);

        Livewire::test(CategoryProfitChart::class)
            ->assertOk()
            ->assertSee('Profit by category')
            ->assertSee('Crystals')
            // The chart's own Chart/Table switch, and the table behind it.
            ->assertSeeHtml('aria-label="Show as"')
            ->assertSeeHtml('<th scope="col"');

        Livewire::test(CurrencyExposureWidget::class)
            ->assertOk()
            ->assertSee('Currency exposure')
            ->assertSee('RMB')
            ->assertSee('¥720.00');
    }

    #[Test]
    public function the_new_charts_say_so_when_there_is_nothing_to_draw(): void
    {
        Livewire::test(CategoryProfitChart::class)->assertOk()->assertSee('Nothing sold in this window');
        Livewire::test(CurrencyExposureWidget::class)->assertOk()->assertSee('Nothing sold or bought in this window');
    }

    /** Both are cost from end to end. */
    #[Test]
    public function the_new_charts_are_owner_only(): void
    {
        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        $this->assertFalse(CategoryProfitChart::canView());
        $this->assertFalse(CurrencyExposureWidget::canView());
    }
}
