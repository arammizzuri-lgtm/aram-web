<?php

namespace Tests\Feature;

use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\InvoiceWriter;
use App\Services\Deals\QuotationWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Discounts given once the list of items is finished.
 *
 * Two of them, and the whole point of the feature is that they behave
 * differently: the supplier's comes off what you PAY and by default makes you
 * money, while your own comes off what the customer pays and can only cost you.
 * Every test here checks both sides of the deal, because a discount that moves
 * only one of them is the bug worth catching.
 *
 * The arithmetic is deliberately in dollars throughout the first few, so the
 * figures can be checked in your head. The exchange rates get their own test.
 */
class DealDiscountTest extends TestCase
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
            'code' => 'C-001', 'name' => 'Ali Trading', 'default_currency' => 'USD',
            'is_active' => true,
        ]);

        $this->supplierA = Supplier::create(['code' => 'SUP-A', 'name' => 'Yiwu Crystals', 'default_currency' => 'CNY']);
        $this->supplierB = Supplier::create(['code' => 'SUP-B', 'name' => 'Shaoxing Textiles', 'default_currency' => 'CNY']);
    }

    /**
     * Ten pieces at $100, sold at $150. Cost $1,000, revenue $1,500, profit $500.
     *
     * Everything is in dollars so that no exchange rate stands between the
     * numbers and the reader. A discount that is wrong by a rounding step here
     * is wrong by arithmetic, not by conversion.
     */
    private function plainDeal(array $discounts = []): Deal
    {
        $deal = Deal::create([
            'number' => 'D-2026-'.str_pad((string) (Deal::count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->customer->id,
            'deal_date' => '2026-08-23',
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
            ...$discounts,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplierA->id,
            'description' => 'Glass vase',
            'quantity' => 10,
            'unit_cost' => 100,
            'cost_currency' => 'USD',
            'unit_price' => 150,
            'pricing_method' => 'manual',
        ]);

        return app(DealWriter::class)->sync($deal->refresh());
    }

    /**
     * Read a deal back with everything its totals actually touch.
     *
     * `costBase()` reaches for the purchases' extra costs, the freight share and
     * the expenses, and lazy loading is off — so a bare `fresh()` throws the
     * moment a deal has more than one purchase, which is when Laravel arms the
     * guard. Every screen that shows these figures eager-loads the same set.
     */
    private function reload(Deal $deal): Deal
    {
        return $deal->fresh([
            'lines', 'purchases.lines', 'purchases.costs', 'purchases.payments',
            'consignments', 'expenses',
        ]);
    }

    #[Test]
    public function a_deal_with_no_discount_is_left_exactly_as_it_was(): void
    {
        $deal = $this->plainDeal();

        $this->assertSame(1000.0, $deal->costBase()->toFloat());
        $this->assertSame(1500.0, $deal->revenueBase()->toFloat());
        $this->assertSame(500.0, $deal->profitBase()->toFloat());
        $this->assertFalse($deal->hasDiscount());
    }

    /**
     * The supplier knocks 10% off and you keep it.
     *
     * The customer's side must not move by a cent — they agreed $1,500 and that
     * is what they owe. The whole $100 lands in profit, which is the point of
     * the "keep it" default.
     */
    #[Test]
    public function a_supplier_discount_you_keep_lowers_your_cost_and_raises_your_profit(): void
    {
        $deal = $this->plainDeal([
            'supplier_discount_percent' => 10,
            'supplier_discount_currency' => 'USD',
        ]);

        $this->assertSame(100.0, $deal->supplierDiscountBase()->toFloat());
        $this->assertSame(900.0, $deal->costBase()->toFloat());

        $this->assertSame(1500.0, $deal->revenueBase()->toFloat(), 'the customer must not be touched');
        $this->assertSame(0.0, $deal->customerDiscount()->toFloat());

        $this->assertSame(600.0, $deal->profitBase()->toFloat());
    }

    /**
     * The same discount, handed on.
     *
     * The margin percentage is what has to survive: you have passed the
     * concession through rather than absorbed it or profited by it. 33.3% before
     * and 33.3% after, on smaller figures both sides.
     */
    #[Test]
    public function a_supplier_discount_passed_on_moves_both_sides_and_keeps_the_margin(): void
    {
        $deal = $this->plainDeal([
            'supplier_discount_percent' => 10,
            'supplier_discount_currency' => 'USD',
            'pass_supplier_discount_on' => true,
        ]);

        $this->assertSame(900.0, $deal->costBase()->toFloat());
        $this->assertSame(150.0, $deal->customerDiscount()->toFloat());
        $this->assertSame(1350.0, $deal->revenueBase()->toFloat());
        $this->assertSame(450.0, $deal->profitBase()->toFloat());

        $this->assertSame(33.33, $deal->marginPercent());
    }

    /**
     * A share of what the items earn, given away.
     *
     * Nothing on the supplier's side moves: the goods go on costing $1,000, and
     * the $100 comes out of the $500 the items were making.
     */
    #[Test]
    public function a_profit_discount_by_percentage_comes_out_of_your_margin_alone(): void
    {
        $deal = $this->plainDeal(['profit_discount_percent' => 20]);

        $this->assertSame(1000.0, $deal->costBase()->toFloat(), 'no supplier price may move');
        $this->assertSame(100.0, $deal->customerDiscount()->toFloat());
        $this->assertSame(1400.0, $deal->revenueBase()->toFloat());
        $this->assertSame(400.0, $deal->profitBase()->toFloat());
    }

    #[Test]
    public function a_profit_discount_can_be_a_flat_amount_instead(): void
    {
        $deal = $this->plainDeal(['profit_discount_amount' => 75]);

        $this->assertSame(1000.0, $deal->costBase()->toFloat());
        $this->assertSame(75.0, $deal->customerDiscount()->toFloat());
        $this->assertSame(425.0, $deal->profitBase()->toFloat());
    }

    /** Both boxes filled in are one concession in two parts, not a choice. */
    #[Test]
    public function a_percentage_and_a_typed_amount_are_added_together(): void
    {
        $deal = $this->plainDeal([
            'profit_discount_percent' => 20,
            'profit_discount_amount' => 75,
        ]);

        $this->assertSame(175.0, $deal->customerDiscount()->toFloat());
        $this->assertSame(325.0, $deal->profitBase()->toFloat());
    }

    /**
     * The two discounts used together, which is allowed.
     *
     * Order matters and is checked here: the supplier's 10% lands first and
     * lifts the item profit to $600, so a fifth of it is $120 rather than the
     * $100 it would have been against the undiscounted margin.
     */
    #[Test]
    public function both_discounts_can_run_on_one_deal(): void
    {
        $deal = $this->plainDeal([
            'supplier_discount_percent' => 10,
            'supplier_discount_currency' => 'USD',
            'profit_discount_percent' => 20,
        ]);

        $this->assertSame(900.0, $deal->costBase()->toFloat());
        $this->assertSame(120.0, $deal->customerDiscount()->toFloat());
        $this->assertSame(1380.0, $deal->revenueBase()->toFloat());
        $this->assertSame(480.0, $deal->profitBase()->toFloat());
    }

    /**
     * A concession typed against one supplier stays with that supplier.
     *
     * The deal is bought from two, and only one of them gave anything. What you
     * owe each is settled separately, so the untouched purchase must still show
     * its full figure.
     */
    #[Test]
    public function a_discount_on_one_supplier_leaves_the_other_alone(): void
    {
        $deal = Deal::create([
            'number' => 'D-2026-0100',
            'customer_id' => $this->customer->id,
            'deal_date' => '2026-08-23',
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
        ]);

        foreach ([[$this->supplierA, 720], [$this->supplierB, 1440]] as [$supplier, $cost]) {
            DealLine::create([
                'deal_id' => $deal->id,
                'supplier_id' => $supplier->id,
                'description' => 'Item from '.$supplier->name,
                'quantity' => 1,
                'unit_cost' => $cost,          // yuan
                'cost_currency' => 'CNY',
                'unit_price' => 400,           // dollars
                'pricing_method' => 'manual',
            ]);
        }

        $deal = $this->reload(app(DealWriter::class)->sync($deal->refresh()));

        // ¥720 = $100 and ¥1,440 = $200 at 7.2. Cost $300, revenue $800.
        $this->assertSame(300.0, $deal->costBase()->toFloat());

        $fromA = $deal->purchases->firstWhere('supplier_id', $this->supplierA->id);
        $fromB = $deal->purchases->firstWhere('supplier_id', $this->supplierB->id);

        $fromA->update(['discount_percent' => 25]);

        $deal = $this->reload($deal);

        // A quarter of ¥720 is ¥180, which is $25.
        $this->assertSame(25.0, $deal->supplierDiscountBase()->toFloat());
        $this->assertSame(275.0, $deal->costBase()->toFloat());
        $this->assertSame(525.0, $deal->profitBase()->toFloat());

        $this->assertSame(75.0, $fromA->fresh(['lines', 'costs'])->totalBase()->toFloat(), 'A is discounted');
        $this->assertSame(200.0, $fromB->fresh(['lines', 'costs'])->totalBase()->toFloat(), 'B is untouched');
    }

    /**
     * What you owe a supplier has to fall when they discount you.
     *
     * Left out, the concession would sit on their balance as money owing
     * forever — the screen that answers "are we square?" could never say yes.
     */
    #[Test]
    public function a_supplier_discount_reduces_what_that_supplier_is_owed(): void
    {
        $deal = $this->plainDeal();
        $purchase = $deal->purchases->first();

        $this->assertSame(1000.0, $this->supplierA->fresh()->outstandingBalance());

        $purchase->update(['discount_percent' => 10, 'currency' => 'USD']);

        $this->assertSame(900.0, $this->supplierA->fresh()->outstandingBalance());
    }

    /**
     * Editing the purchase must reach the deal.
     *
     * The purchase is saved on its own screen and never goes near the deal
     * form, so without a write-back the deal would go on reporting the profit
     * it had before the supplier knocked anything off.
     */
    #[Test]
    public function a_discount_typed_on_a_purchase_reaches_the_deal_without_reopening_it(): void
    {
        $deal = $this->plainDeal();

        $deal->purchases->first()->update(['discount_percent' => 10, 'currency' => 'USD']);

        $this->assertSame(600.0, $this->reload($deal)->profitBase()->toFloat());
    }

    /**
     * The customer's documents carry the discount as its own row.
     *
     * Not spread back into the unit prices: they agreed $150 a piece and both
     * documents have to go on saying $150, or the invoice stops reconciling
     * against the quotation they approved.
     */
    #[Test]
    public function the_quotation_and_the_invoice_both_show_subtotal_discount_and_total(): void
    {
        $deal = $this->plainDeal(['profit_discount_percent' => 20]);

        $quotation = app(QuotationWriter::class)->build($deal);

        $this->assertSame('1500.0000', $quotation->subtotal);
        $this->assertSame('100.0000', $quotation->discount);
        $this->assertSame('1400.0000', $quotation->total);
        $this->assertSame(150.0, (float) $quotation->lines->first()->unit_price);

        $invoice = app(InvoiceWriter::class)->issueGoods($this->reload($deal));

        $this->assertSame('1500.0000', $invoice->subtotal);
        $this->assertSame('100.0000', $invoice->discount);
        $this->assertSame('1400.0000', $invoice->total);
        $this->assertSame(150.0, (float) $invoice->lines->first()->unit_price);
    }

    /** The freight bill is billed separately and no discount applies to it. */
    #[Test]
    public function a_shipping_invoice_carries_no_discount(): void
    {
        $deal = $this->plainDeal(['profit_discount_percent' => 20]);

        $invoice = app(InvoiceWriter::class)->issueShipping($deal, 250);

        $this->assertSame('0.0000', $invoice->discount);
        $this->assertSame('250.0000', $invoice->total);
    }

    /**
     * Giving away more than the deal earns is reported, not prevented.
     *
     * Selling at a loss to keep a customer is a real decision, and the system is
     * in no position to overrule it — but it must never be something that is
     * only discovered from a report a month later.
     */
    #[Test]
    public function a_discount_larger_than_the_profit_is_allowed_and_flagged(): void
    {
        $deal = $this->plainDeal(['profit_discount_amount' => 600]);

        $this->assertTrue($deal->computeDiscounts()->exceedsProfit($deal));

        $this->assertSame(900.0, $deal->revenueBase()->toFloat());
        $this->assertSame(-100.0, $deal->profitBase()->toFloat());
    }

    /** A percentage of a loss is nothing, never a surcharge. */
    #[Test]
    public function a_share_of_profit_on_a_losing_deal_is_zero(): void
    {
        $deal = Deal::create([
            'number' => 'D-2026-0200',
            'customer_id' => $this->customer->id,
            'deal_date' => '2026-08-23',
            'sell_currency' => 'USD',
            'profit_discount_percent' => 20,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'description' => 'Sold under cost',
            'quantity' => 1,
            'unit_cost' => 500,
            'cost_currency' => 'USD',
            'unit_price' => 300,
            'pricing_method' => 'manual',
        ]);

        $deal = app(DealWriter::class)->sync($deal->refresh());

        $this->assertSame(0.0, $deal->customerDiscount()->toFloat());
        $this->assertSame(-200.0, $deal->profitBase()->toFloat());
    }

    /**
     * The boxes actually appear, and the totals beside them move.
     *
     * The model tests above prove the arithmetic; this proves the screen can be
     * driven. Both discount sections decide whether to open by asking the form
     * what has been typed into them, and a closure that cannot be evaluated
     * takes the whole deal screen down rather than degrading — so it is worth
     * one test that renders it for real.
     */
    #[Test]
    public function the_deal_screen_takes_both_discounts_and_saves_them(): void
    {
        $page = Livewire::test(CreateDeal::class)
            ->fillForm([
                'customer_id' => $this->customer->id,
                'deal_date' => today(),
                'sell_currency' => 'USD',
                'rmb_usd_rate' => 7.2,
                'supplier_discount_percent' => 10,
                'supplier_discount_currency' => 'USD',
                'profit_discount_percent' => 20,
                'lines' => [
                    [
                        'description' => 'Glass vase',
                        'quantity' => 10,
                        'unit' => 'pcs',
                        'unit_cost' => 100,
                        'cost_currency' => 'USD',
                        'pricing_method' => 'manual',
                        'unit_price' => 150,
                    ],
                ],
            ])
            ->assertHasNoFormErrors();

        $page->call('create')->assertHasNoFormErrors();

        $deal = $this->reload(Deal::query()->latest('id')->first());

        $this->assertSame(900.0, $deal->costBase()->toFloat());
        $this->assertSame(120.0, $deal->customerDiscount()->toFloat());
        $this->assertSame(480.0, $deal->profitBase()->toFloat());
    }

    /**
     * An assistant never sees either box.
     *
     * Both are commercial decisions made against cost — what the supplier
     * conceded and what your margin will bear — and the deal screen's whole
     * arrangement is that the money half of it is invisible to anyone without
     * `view_cost`, rather than living on a second screen that could drift.
     */
    #[Test]
    public function the_discount_boxes_are_hidden_from_anyone_who_cannot_see_cost(): void
    {
        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        Livewire::test(CreateDeal::class)
            ->assertFormFieldDoesNotExist('supplier_discount_percent')
            ->assertFormFieldDoesNotExist('profit_discount_percent');
    }

    /**
     * The same feature across three currencies.
     *
     * Bought in yuan, billed in dinars, measured in dollars — the case the whole
     * system exists for. Checked with a tolerance of a cent because a percentage
     * of a converted figure cannot land on a round number, and pretending
     * otherwise would make this test a description of the rounding rather than
     * of the discount.
     */
    #[Test]
    public function discounts_survive_the_trip_through_three_currencies(): void
    {
        $deal = Deal::create([
            'number' => 'D-2026-0300',
            'customer_id' => $this->customer->id,
            'deal_date' => '2026-08-23',
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
            'supplier_discount_percent' => 10,
            'supplier_discount_currency' => 'CNY',
            'profit_discount_percent' => 25,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplierA->id,
            'description' => 'Crystal set',
            'quantity' => 500,
            'unit_cost' => 12.50,           // ¥6,250 → $868.06
            'cost_currency' => 'CNY',
            'unit_price' => 3000,           // 1,500,000 IQD → $1,020.41
            'pricing_method' => 'manual',
        ]);

        $deal = app(DealWriter::class)->sync($deal->refresh());

        // ¥6,250 ÷ 7.2 = $868.0556; a tenth of it is $86.81.
        $this->assertEqualsWithDelta(86.81, $deal->supplierDiscountBase()->toFloat(), 0.01);
        $this->assertEqualsWithDelta(781.25, $deal->costBase()->toFloat(), 0.01);

        // Items earn $1,020.41 − $781.25 = $239.16; a quarter is $59.79,
        // which is 87,890 dinars at 1,470.
        $this->assertEqualsWithDelta(87_890.0, $deal->customerDiscount()->toFloat(), 2.0);
        $this->assertEqualsWithDelta(179.37, $deal->profitBase()->toFloat(), 0.02);

        // The customer is still billed 3,000 dinars a piece — the discount is a
        // row of its own, never folded back into the price they agreed.
        $this->assertSame(3000.0, (float) $deal->lines->first()->unit_price);
    }
}
