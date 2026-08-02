<?php

namespace Tests\Feature;

use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\PurchaseCost;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\SupplierPaymentWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Paying suppliers, and the gap between the quoted rate and the real one.
 *
 * That gap is the reason this has its own tests. It is small on any single
 * payment, never looks like an error, and compounds into a permanent gap
 * between what the reports say you earned and what is actually in your hand.
 */
class SupplierPaymentTest extends TestCase
{
    use RefreshDatabase;

    private Deal $deal;

    private SupplierPaymentWriter $writer;

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

        $supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu Crystals',
            'default_currency' => 'CNY', 'deposit_percent' => 30,
        ]);

        $this->deal = Deal::create([
            'number' => 'D-2026-0001',
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        // ¥12.50 x 500 = ¥6,250, which is $868.06 at 7.2 to the dollar.
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
        $this->writer = app(SupplierPaymentWriter::class);
    }

    private function purchase()
    {
        return $this->deal->purchases()->with(['lines', 'costs', 'payments'])->first();
    }

    // ---------------------------------------------------------------- totals

    #[Test]
    public function a_purchase_totals_its_lines_in_the_suppliers_currency_and_in_dollars(): void
    {
        $purchase = $this->purchase();

        $this->assertSame('6250.0000', $purchase->goodsTotal()->amount);
        $this->assertSame(868.06, round($purchase->goodsTotalBase()->toFloat(), 2));
    }

    /** Extra costs sit apart so the goods figure stays comparable to the list. */
    #[Test]
    public function extra_purchase_costs_are_counted_but_kept_separate(): void
    {
        $purchase = $this->purchase();

        PurchaseCost::create([
            'deal_purchase_id' => $purchase->id,
            'description' => 'Inspection',
            'amount' => 360,
            'currency' => 'CNY',
            'base_amount' => 50,
        ]);

        $purchase = $this->purchase();

        $this->assertSame('6250.0000', $purchase->goodsTotal()->amount, 'goods unchanged');
        $this->assertSame('50.0000', $purchase->extraCostsBase()->amount);
        $this->assertSame(918.06, round($purchase->totalBase()->toFloat(), 2));
    }

    // -------------------------------------------------------------- payments

    #[Test]
    public function a_payment_is_valued_at_the_deals_frozen_rate(): void
    {
        $payment = $this->writer->record($this->purchase(), 1875, 'CNY');

        $this->assertSame('1875.0000', $payment->amount);
        $this->assertSame('CNY', $payment->currency);
        // 1,875 / 7.2 = 260.4167
        $this->assertSame(260.42, round((float) $payment->base_amount, 2));
    }

    #[Test]
    public function paying_in_instalments_moves_the_purchase_through_part_paid_to_paid(): void
    {
        $this->assertSame('draft', $this->purchase()->status);

        // 30% deposit.
        $this->writer->record($this->purchase(), 1875, 'CNY');
        $this->assertSame('paid_partial', $this->purchase()->status);
        $this->assertSame(607.64, round($this->purchase()->outstandingBase()->toFloat(), 2));

        // The balance.
        $this->writer->record($this->purchase(), 4375, 'CNY');
        $this->assertSame('paid', $this->purchase()->status);
        $this->assertTrue($this->purchase()->isFullyPaid());
    }

    #[Test]
    public function paying_in_full_in_one_go_settles_it_immediately(): void
    {
        $this->writer->record($this->purchase(), 6250, 'CNY');

        $this->assertSame('paid', $this->purchase()->status);
        $this->assertSame(0.0, round($this->purchase()->outstandingBase()->toFloat(), 2));
    }

    /** Goods received is a fact about goods; paying a bill does not undo it. */
    #[Test]
    public function a_received_purchase_does_not_have_its_status_overwritten_by_a_payment(): void
    {
        $this->purchase()->update(['status' => 'received']);

        $this->writer->record($this->purchase(), 6250, 'CNY');

        $this->assertSame('received', $this->purchase()->status);
    }

    // ------------------------------------------------------- the hidden cost

    /**
     * The worked example from the design.
     *
     *   Supplier invoice     ¥6,250
     *   At the quoted 7.20   looks like $868.06
     *   What it cost you              $890.00
     *                                 --------
     *   Never recorded before           $21.94
     */
    #[Test]
    public function the_exchange_houses_cut_is_recorded_rather_than_absorbed(): void
    {
        $payment = $this->writer->record($this->purchase(), 6250, 'CNY', actualCostBase: 890.00);

        $this->assertSame(868.06, round((float) $payment->base_amount, 2), 'what the supplier got');
        $this->assertSame(890.0, round((float) $payment->actual_cost_base, 2), 'what it cost you');
        $this->assertSame(21.94, round($payment->transferLossBase()->toFloat(), 2));
        $this->assertSame(890.0, round($payment->trueCostBase()->toFloat(), 2));
    }

    #[Test]
    public function the_loss_rolls_up_to_the_purchase(): void
    {
        $this->writer->record($this->purchase(), 1875, 'CNY', actualCostBase: 270.00);
        $this->writer->record($this->purchase(), 4375, 'CNY', actualCostBase: 620.00);

        // (270 - 260.42) + (620 - 607.64)
        $this->assertSame(21.94, round($this->purchase()->transferLossBase()->toFloat(), 2));
    }

    /** Not recording it is allowed; it simply means the gap is unknown, not zero-cost. */
    #[Test]
    public function an_unrecorded_transfer_cost_falls_back_to_the_converted_amount(): void
    {
        $payment = $this->writer->record($this->purchase(), 6250, 'CNY');

        $this->assertNull($payment->actual_cost_base);
        $this->assertTrue($payment->transferLossBase()->isZero());
        $this->assertSame(868.06, round($payment->trueCostBase()->toFloat(), 2));
    }

    /**
     * A transfer that cost less than the quoted rate is not a profit.
     *
     * It means the quoted rate was wrong, not that shipping money earned you
     * something. Treating it as a gain would flatter the deal.
     */
    #[Test]
    public function a_cheaper_than_quoted_transfer_is_never_counted_as_a_gain(): void
    {
        $payment = $this->writer->record($this->purchase(), 6250, 'CNY', actualCostBase: 850.00);

        $this->assertTrue($payment->transferLossBase()->isZero());
        $this->assertFalse($payment->transferLossBase()->isNegative());
    }

    /**
     * What you owe is settled by what reached the supplier.
     *
     * The exchange house's cut is your expense, not their credit — so it must
     * not reduce the outstanding balance.
     */
    #[Test]
    public function the_transfer_cost_does_not_reduce_what_the_supplier_is_owed(): void
    {
        $this->writer->record($this->purchase(), 3125, 'CNY', actualCostBase: 500.00);

        // Half of ¥6,250 paid; ¥3,125 = $434.03 still due, regardless of the $66 lost.
        $this->assertSame(434.03, round($this->purchase()->outstandingBase()->toFloat(), 2));
    }

    // -------------------------------------------------------------- deposits

    #[Test]
    public function the_suggested_deposit_follows_the_suppliers_own_terms(): void
    {
        $this->assertSame('1875.0000', $this->writer->suggestedDeposit($this->purchase())->amount);
    }

    /**
     * A supplier with nothing recorded gets the usual 30%.
     *
     * The column is NOT NULL and defaults to 0, so "not recorded" and "no
     * deposit required" look identical in the database. Reading 0 as "charge
     * them nothing up front" would be the wrong guess far more often — you can
     * always type over the suggestion.
     */
    #[Test]
    public function a_supplier_with_no_recorded_terms_falls_back_to_thirty_percent(): void
    {
        $this->purchase()->supplier->update(['deposit_percent' => 0]);

        $this->assertSame('1875.0000', $this->writer->suggestedDeposit($this->purchase()->fresh())->amount);
    }

    #[Test]
    public function a_suppliers_own_percentage_wins_over_the_default(): void
    {
        $this->purchase()->supplier->update(['deposit_percent' => 50]);

        $this->assertSame('3125.0000', $this->writer->suggestedDeposit($this->purchase()->fresh())->amount);
    }

    // ----------------------------------------------------------------- screen

    #[Test]
    public function the_purchases_screen_records_a_payment_with_its_true_cost(): void
    {
        Livewire::test(ManagePurchases::class)
            ->callTableAction('pay', $this->purchase(), [
                'amount' => 6250,
                'actual_cost_base' => 890,
                'method' => 'exchange',
                'paid_at' => today()->toDateString(),
            ])
            ->assertHasNoTableActionErrors();

        $purchase = $this->purchase();

        $this->assertSame('paid', $purchase->status);
        $this->assertSame(21.94, round($purchase->transferLossBase()->toFloat(), 2));
    }

    /** Nothing left to pay means nothing to offer. */
    #[Test]
    public function the_pay_button_disappears_once_the_purchase_is_settled(): void
    {
        Livewire::test(ManagePurchases::class)
            ->assertTableActionVisible('pay', $this->purchase());

        $this->writer->record($this->purchase(), 6250, 'CNY');

        Livewire::test(ManagePurchases::class)
            ->assertTableActionHidden('pay', $this->purchase());
    }

    /**
     * The assistant cannot open this screen at all.
     *
     * Everything on it is cost — what was paid, to whom, and what is still
     * owed — so there is no version of it with the sensitive parts removed.
     */
    #[Test]
    public function the_assistant_cannot_reach_the_purchases_screen(): void
    {
        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        $this->assertFalse(PurchaseResource::canViewAny());

        $this->get('/admin/purchases')->assertForbidden();
    }

    /** Goods bought for nobody is the thing this business never does. */
    #[Test]
    public function a_purchase_cannot_be_created_standing_alone(): void
    {
        $this->assertFalse(PurchaseResource::canCreate());
    }

    // -------------------------------------------------------------- balances

    #[Test]
    public function the_supplier_balance_reflects_what_is_ordered_less_what_is_paid(): void
    {
        $supplier = $this->purchase()->supplier;

        $this->assertSame(868.06, round($supplier->outstandingBalance(), 2));

        $this->writer->record($this->purchase(), 1875, 'CNY');

        $this->assertSame(607.64, round($supplier->fresh()->outstandingBalance(), 2));
    }
}
