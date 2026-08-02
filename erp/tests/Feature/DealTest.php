<?php

namespace Tests\Feature;

use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Support\Money;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DealTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Supplier $supplierA;

    private Supplier $supplierB;

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
            'code' => 'C-001', 'name' => 'Ali Trading', 'default_currency' => 'IQD',
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create(['code' => 'SUP-A', 'name' => 'Yiwu Crystals', 'default_currency' => 'CNY']);
        $this->supplierB = Supplier::create(['code' => 'SUP-B', 'name' => 'Shaoxing Textiles', 'default_currency' => 'CNY']);
    }

    /**
     * The worked example this whole design exists to get right.
     *
     * Buy from two suppliers in yuan, sell to one customer in dinars, and the
     * profit has to come out in dollars. Every number below is checked by hand.
     */
    private function workedDeal(): Deal
    {
        $deal = Deal::create([
            'number' => 'D-2026-0001',
            'customer_id' => $this->customer->id,
            'deal_date' => '2026-08-02',
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,      // yuan per dollar
            'iqd_usd_rate' => 1470,     // dinars per dollar
        ]);

        // Crystals: ¥12.50 each x 500 = ¥6,250 → $868.0556
        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplierA->id,
            'description' => 'Crystal P07 20mm',
            'quantity' => 500,
            'unit_cost' => 12.50,
            'cost_currency' => 'CNY',
            'unit_price' => 28000,      // IQD each → $19.0476
            'pricing_method' => 'manual',
        ]);

        // Fabric: ¥40 per metre x 100 = ¥4,000 → $555.5556
        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplierB->id,
            'description' => 'Jacquard 280cm',
            'quantity' => 100,
            'unit_cost' => 40,
            'cost_currency' => 'CNY',
            'unit_price' => 95000,      // IQD per metre → $64.6259
            'pricing_method' => 'manual',
        ]);

        return app(DealWriter::class)->sync($deal->fresh());
    }

    // ------------------------------------------------------------- currency

    #[Test]
    public function rates_convert_by_dividing_because_that_is_how_they_are_quoted(): void
    {
        $deal = $this->workedDeal();

        // ¥7,200 at 7.2 yuan to the dollar is $1,000 — not ¥51,840.
        $this->assertSame('1000.0000', $deal->toBase(Money::of(7200, 'CNY'))->amount);

        // 1,470,000 dinars at 1,470 to the dollar is $1,000.
        $this->assertSame('1000.0000', $deal->toBase(Money::of(1470000, 'IQD'))->amount);

        // Dollars pass straight through.
        $this->assertSame('250.0000', $deal->toBase(Money::of(250, 'USD'))->amount);
    }

    #[Test]
    public function a_deal_only_needs_the_rates_it_actually_uses(): void
    {
        $deal = Deal::create([
            'number' => 'D-2026-0002',
            'customer_id' => $this->customer->id,
            'deal_date' => '2026-08-02',
            'sell_currency' => 'USD',
        ]);

        $this->assertSame('1', $deal->rateFor('USD'));
        $this->assertSame('250.0000', $deal->toBase(Money::of(250, 'USD'))->amount);
    }

    // ---------------------------------------------------------------- money

    #[Test]
    public function cost_and_revenue_are_computed_in_dollars_from_two_currencies(): void
    {
        $deal = $this->workedDeal()->load('lines', 'purchases.costs', 'expenses', 'consignments');

        // 6250/7.2 = 868.0556 ; 4000/7.2 = 555.5556
        $this->assertSame(1423.61, round($deal->costBase()->toFloat(), 2));

        // (500 x 28000 + 100 x 95000) / 1470 = 23,500,000 / 1470 = 15,986.39
        $this->assertSame(15986.39, round($deal->revenueBase()->toFloat(), 2));

        $this->assertSame(14562.78, round($deal->profitBase()->toFloat(), 2));
    }

    /** A lump belongs to the deal, so per-product profit becomes an estimate. */
    #[Test]
    public function a_deal_commission_adds_to_revenue_and_flags_line_profit_as_approximate(): void
    {
        $deal = $this->workedDeal();

        $this->assertFalse($deal->perLineProfitIsApproximate());

        $deal->update(['deal_commission' => 500, 'deal_commission_currency' => 'USD']);
        $deal = $deal->fresh()->load('lines', 'purchases.costs', 'expenses', 'consignments');

        $this->assertSame(500.0, $deal->commissionBase()->toFloat());
        $this->assertSame(16486.39, round($deal->revenueBase()->toFloat(), 2));
        $this->assertTrue($deal->perLineProfitIsApproximate());
    }

    /**
     * Editing a rate must not move profit that has already been reported.
     *
     * This is the whole reason base amounts are stamped on save rather than
     * converted on read.
     */
    #[Test]
    public function changing_a_rate_later_does_not_rewrite_settled_profit(): void
    {
        $deal = $this->workedDeal()->load('lines', 'purchases.costs', 'expenses', 'consignments');
        $before = round($deal->profitBase()->toFloat(), 2);

        $deal->update(['iqd_usd_rate' => 1600, 'rmb_usd_rate' => 8.0]);

        $reloaded = $deal->fresh()->load('lines', 'purchases.costs', 'expenses', 'consignments');

        $this->assertSame($before, round($reloaded->profitBase()->toFloat(), 2));
    }

    // ------------------------------------------------------------ purchases

    #[Test]
    public function naming_a_supplier_on_a_line_creates_its_purchase_document(): void
    {
        $deal = $this->workedDeal();

        $this->assertSame(2, $deal->purchases()->count());
        $this->assertEqualsCanonicalizing(
            [$this->supplierA->id, $this->supplierB->id],
            $deal->purchases()->pluck('supplier_id')->all(),
        );

        // Every line is attached to the purchase for its own supplier.
        foreach ($deal->lines()->with('purchase')->get() as $line) {
            $this->assertSame($line->supplier_id, $line->purchase->supplier_id);
        }
    }

    #[Test]
    public function each_purchase_totals_only_its_own_suppliers_lines(): void
    {
        $deal = $this->workedDeal();

        $crystals = $deal->purchases()->where('supplier_id', $this->supplierA->id)->first()->load('lines', 'costs');
        $fabric = $deal->purchases()->where('supplier_id', $this->supplierB->id)->first()->load('lines', 'costs');

        $this->assertSame('6250.0000', $crystals->goodsTotal()->amount);
        $this->assertSame('4000.0000', $fabric->goodsTotal()->amount);
        $this->assertSame(868.06, round($crystals->goodsTotalBase()->toFloat(), 2));
    }

    #[Test]
    public function a_purchase_is_removed_when_its_last_line_moves_to_another_supplier(): void
    {
        $deal = $this->workedDeal();
        $this->assertSame(2, $deal->purchases()->count());

        $deal->lines()->where('supplier_id', $this->supplierB->id)
            ->update(['supplier_id' => $this->supplierA->id]);

        app(DealWriter::class)->sync($deal->fresh());

        $this->assertSame(1, $deal->purchases()->count());
        $this->assertSame($this->supplierA->id, $deal->purchases()->first()->supplier_id);
    }

    /** Buying before the customer commits is allowed, but it is recorded. */
    #[Test]
    public function purchases_made_before_approval_are_flagged_as_at_risk(): void
    {
        $deal = $this->workedDeal();

        $this->assertCount(2, $deal->atRiskPurchases());

        $approved = Deal::create([
            'number' => 'D-2026-0003',
            'customer_id' => $this->customer->id,
            'deal_date' => '2026-08-02',
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
            'approved_at' => now(),
        ]);

        DealLine::create([
            'deal_id' => $approved->id,
            'supplier_id' => $this->supplierA->id,
            'description' => 'Crystal P08 16mm',
            'quantity' => 10,
            'unit_cost' => 5,
            'cost_currency' => 'CNY',
            'unit_price' => 12000,
        ]);

        $synced = app(DealWriter::class)->sync($approved->fresh());

        $this->assertFalse((bool) $synced->purchases()->first()->bought_at_risk);
    }

    // ----------------------------------------------------------- line rules

    /**
     * Marking up ¥12.50 has no meaning in dinars until the yuan is valued, so
     * the markup runs through dollars rather than being applied to either
     * end directly.
     *
     * The result is 3,190.08 rather than the 3,190.10 exact arithmetic gives.
     * Everything pivots through USD held to four decimal places, so ¥12.50
     * becomes $1.7361 and the last fraction of a cent is gone before the dinar
     * rate is applied. That is a 0.0006% difference on a *suggested* price the
     * operator sees and can overwrite, in a currency whose smallest note is 250
     * dinars — so the asserted number here is the real one, deliberately, and
     * not a rounder figure the code was bent to produce.
     */
    #[Test]
    public function a_markup_price_is_derived_through_dollars_not_from_the_yuan_figure(): void
    {
        $deal = $this->workedDeal();
        $line = $deal->lines()->first();

        $line->update(['markup_percent' => 25]);

        $price = $line->fresh()->priceFromMarkup($deal);

        $this->assertSame('IQD', $price->currency);
        $this->assertSame(3190.08, round($price->toFloat(), 2));

        // Within a tenth of a dinar of exact arithmetic, which is what matters.
        $this->assertEqualsWithDelta(12.50 / 7.2 * 1.25 * 1470, $price->toFloat(), 0.1);
    }

    /** A markup on a USD sale needs no rate at all and stays exact. */
    #[Test]
    public function a_markup_on_a_dollar_deal_involves_no_conversion(): void
    {
        $deal = Deal::create([
            'number' => 'D-2026-0009',
            'customer_id' => $this->customer->id,
            'deal_date' => '2026-08-02',
            'sell_currency' => 'USD',
        ]);

        $line = DealLine::create([
            'deal_id' => $deal->id,
            'description' => 'Sample box',
            'quantity' => 1,
            'unit_cost' => 200,
            'cost_currency' => 'USD',
            'unit_price' => 0,
            'markup_percent' => 30,
        ]);

        $price = $line->priceFromMarkup($deal);

        $this->assertSame('USD', $price->currency);
        $this->assertSame('260.0000', $price->amount);
    }

    #[Test]
    public function a_line_with_no_catalogue_link_is_a_custom_product(): void
    {
        $line = $this->workedDeal()->lines()->first();

        $this->assertTrue($line->isCustom());
    }

    #[Test]
    public function battery_goods_are_visible_at_the_deal_level(): void
    {
        $deal = $this->workedDeal();

        $this->assertFalse($deal->load('lines')->hasBatteryGoods());

        $deal->lines()->first()->update(['contains_battery' => true]);

        $this->assertTrue($deal->fresh()->load('lines')->hasBatteryGoods());
    }

    // --------------------------------------------------- pricing on screen

    /*
     * The deal screen fills the selling price in from cost plus markup. It was
     * doing that on the raw cost figure, with no regard for the currency it was
     * in — so ¥50 marked up 75% became a price of 87.50, handed to a customer
     * paying dollars. Nearly seven times what the goods are worth, and it looks
     * like an extraordinarily profitable deal right up until it is quoted.
     */

    #[Test]
    public function a_yuan_cost_is_valued_in_dollars_before_the_markup_is_taken(): void
    {
        $deal = Deal::create([
            'number' => 'D-2026-0100',
            'customer_id' => $this->customer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 6.7,
        ]);

        $line = DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplierA->id,
            'description' => 'p01',
            'quantity' => 47,
            'unit_cost' => 50,
            'cost_currency' => 'CNY',
            'pricing_method' => 'markup',
            'markup_percent' => 75,
            'unit_price' => 0,
        ]);

        // ¥50 ÷ 6.7 = $7.4627, plus 75% = $13.0597
        $this->assertSame(13.06, round($line->priceFromMarkup($deal)->toFloat(), 2));
    }

    /**
     * The screen itself, not the model behind it.
     *
     * The model always priced this correctly; the deal form kept its own copy
     * of the arithmetic and did not, which is how the two came to disagree
     * without anything failing. Driving the form is the only place that catches
     * it.
     */
    #[Test]
    public function the_deal_screen_prices_a_yuan_cost_in_the_customers_currency(): void
    {
        $page = Livewire::test(CreateDeal::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'deal_date' => today(),
                'sell_currency' => 'USD',
                'rmb_usd_rate' => 6.7,
                'lines' => [
                    [
                        'description' => 'p01',
                        'quantity' => 47,
                        'unit' => 'pcs',
                        'unit_cost' => 50,
                        'cost_currency' => 'CNY',
                        'pricing_method' => 'markup',
                        'markup_percent' => 75,
                        'unit_price' => 0,
                    ],
                ],
            ]);

        $key = array_key_first($page->get('data.lines'));

        // Nudging the cost is what the operator does; the price follows it.
        $page->set("data.lines.{$key}.unit_cost", 50);

        // ¥50 ÷ 6.7 = $7.4627, plus 75% = $13.06 — not 87.50.
        $this->assertSame(13.06, round((float) $page->get("data.lines.{$key}.unit_price"), 2));
    }

    /** Selling in dinars: through dollars on the way, never around them. */
    #[Test]
    public function a_yuan_cost_priced_in_dinars_goes_through_dollars(): void
    {
        $deal = $this->workedDeal();

        $line = $deal->lines()->first();
        $line->update(['pricing_method' => 'markup', 'markup_percent' => 25]);

        // ¥12.50 ÷ 7.2 = $1.7361, plus 25% = $2.1701, x 1,470 = 3,190 IQD
        $this->assertSame(3190.0, round($line->fresh()->priceFromMarkup($deal)->toFloat(), 0));
    }
}
