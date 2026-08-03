<?php

namespace Tests\Feature;

use App\Filament\Resources\Purchases\Pages\ManagePurchases;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\DealPurchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\QuotationWriter;
use App\Services\Reporting\BusinessMetrics;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "At risk" must mean one thing, in one place, everywhere it is shown.
 *
 * It did not. `bought_at_risk` records that a purchase was *made* before the
 * customer approved and never changes afterwards — a true fact, and the wrong
 * question. The dashboard asked the right one (is the deal still unapproved?)
 * while the purchases screen showed the frozen flag, so the headline could read
 * "everything is approved · $0.00" above three rows each carrying a warning.
 *
 * There is now one rule, on the model, and these tests hold the screens and the
 * dashboard to it.
 */
class AtRiskTest extends TestCase
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

        $this->deal = Deal::create([
            'number' => 'D-2026-0001',
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        // Two suppliers, so approving from one row has to clear the other too.
        foreach ([['SUP-A', 'Yiwu Crystals', 12.50], ['SUP-B', 'Shaoxing', 20.00]] as [$code, $name, $cost]) {
            $supplier = Supplier::create(['code' => $code, 'name' => $name, 'default_currency' => 'CNY']);

            DealLine::create([
                'deal_id' => $this->deal->id,
                'supplier_id' => $supplier->id,
                'description' => $name.' goods',
                'quantity' => 100,
                'unit_cost' => $cost,
                'cost_currency' => 'CNY',
                'unit_price' => 50000,
            ]);
        }

        $this->deal = app(DealWriter::class)->sync($this->deal->fresh());
    }

    /**
     * With the deal loaded, because `isAtRisk()` asks it a question.
     *
     * Deliberately not `loadMissing` inside the model: a missing eager-load is
     * an N+1 in a table of a hundred rows, and the lazy-loading guard is there
     * to make that fail loudly rather than quietly cost.
     */
    private function purchases()
    {
        return $this->deal->purchases()->with('deal')->orderBy('id')->get();
    }

    // ------------------------------------------------------------------ rule

    /** The flag says it happened; the question is whether it is still true. */
    #[Test]
    public function a_purchase_stops_being_at_risk_the_moment_the_deal_is_approved(): void
    {
        $purchase = $this->purchases()->first();

        $this->assertTrue($purchase->isAtRisk());

        app(QuotationWriter::class)->recordApproval($this->deal, 'Ali Hassan');

        $purchase = $purchase->fresh()->load('deal');

        $this->assertTrue((bool) $purchase->bought_at_risk, 'the historical fact stands');
        $this->assertFalse($purchase->isAtRisk(), 'but the risk is settled');
    }

    /** A cancelled deal is not money you are carrying — nobody will buy it. */
    #[Test]
    public function a_cancelled_deal_is_not_at_risk(): void
    {
        $this->deal->update(['status' => 'cancelled']);

        $this->assertFalse($this->purchases()->first()->fresh()->load('deal')->isAtRisk());
    }

    #[Test]
    public function a_cancelled_purchase_is_not_at_risk(): void
    {
        $purchase = $this->purchases()->first();
        $purchase->update(['status' => 'cancelled']);

        $this->assertFalse($purchase->fresh()->load('deal')->isAtRisk());
    }

    /**
     * The query and the method are the same rule written twice, which is exactly
     * how the last one drifted. Checked against each other rather than trusted.
     */
    #[Test]
    public function the_query_scope_agrees_with_the_model(): void
    {
        $expected = fn () => DealPurchase::with('deal')->get()
            ->filter(fn (DealPurchase $p) => $p->isAtRisk())
            ->pluck('id')->sort()->values()->all();

        $actual = fn () => DealPurchase::query()->atRisk()->pluck('id')->sort()->values()->all();

        $this->assertSame($expected(), $actual());
        $this->assertCount(2, $actual());

        app(QuotationWriter::class)->recordApproval($this->deal, 'Ali Hassan');

        $this->assertSame($expected(), $actual());
        $this->assertSame([], $actual());
    }

    // ------------------------------------------------------------- dashboard

    /**
     * The screen and the headline read the same rows.
     *
     * This is the disagreement that started all of it: three warnings on the
     * purchases list under a dashboard reporting nothing at risk.
     */
    #[Test]
    public function the_dashboard_total_covers_exactly_the_flagged_rows(): void
    {
        $metrics = app(BusinessMetrics::class);

        $flagged = $this->deal->purchases()->with(['deal', 'lines', 'costs'])->get()
            ->filter(fn (DealPurchase $p) => $p->isAtRisk());

        $this->assertCount(2, $flagged);
        $this->assertSame(
            round($flagged->sum(fn (DealPurchase $p) => $p->totalBase()->toFloat()), 2),
            round($metrics->boughtAtRisk()->toFloat(), 2),
        );
        $this->assertTrue($metrics->boughtAtRisk()->isPositive());

        app(QuotationWriter::class)->recordApproval($this->deal, 'Ali Hassan');

        $this->assertSame(0.0, $metrics->boughtAtRisk()->toFloat());
        $this->assertSame(
            [],
            DealPurchase::query()->atRisk()->pluck('id')->all(),
            'nothing is flagged once nothing is totalled',
        );
    }

    // ---------------------------------------------------------------- screen

    /** Offered on the rows that are complaining, and nowhere else. */
    #[Test]
    public function the_purchases_screen_offers_approval_only_while_the_risk_is_open(): void
    {
        $purchase = $this->purchases()->first();

        Livewire::test(ManagePurchases::class)
            ->assertTableActionVisible('approve', $purchase);

        app(QuotationWriter::class)->recordApproval($this->deal, 'Ali Hassan');

        Livewire::test(ManagePurchases::class)
            ->assertTableActionHidden('approve', $purchase);
    }

    /**
     * Approval belongs to the customer's request, not to one supplier's share of
     * it — so clearing it from one row clears every purchase on the deal.
     */
    #[Test]
    public function approving_from_one_purchase_clears_every_purchase_on_the_deal(): void
    {
        Livewire::test(ManagePurchases::class)
            ->callTableAction('approve', $this->purchases()->first(), [
                'approved_by_name' => 'Ali Hassan',
                'approval_channel' => 'whatsapp',
                'approval_note' => 'Confirmed the gold finish',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertTrue($this->deal->fresh()->isApproved());

        foreach ($this->purchases() as $purchase) {
            $this->assertFalse($purchase->load('deal')->isAtRisk(), "{$purchase->number} is still flagged");
        }
    }

    /** Who agreed is the whole record, so it cannot be left blank here either. */
    #[Test]
    public function approving_from_the_purchases_screen_still_requires_a_name(): void
    {
        Livewire::test(ManagePurchases::class)
            ->callTableAction('approve', $this->purchases()->first(), ['approved_by_name' => ''])
            ->assertHasTableActionErrors(['approved_by_name']);

        $this->assertFalse($this->deal->fresh()->isApproved());
    }

    /** The filter is the column's question asked of the whole list. */
    #[Test]
    public function the_filter_shows_the_rows_the_column_flags(): void
    {
        $screen = Livewire::test(ManagePurchases::class)
            ->filterTable('at_risk')
            ->assertCanSeeTableRecords($this->purchases());

        app(QuotationWriter::class)->recordApproval($this->deal, 'Ali Hassan');

        $screen->filterTable('at_risk')
            ->assertCanNotSeeTableRecords($this->purchases());
    }
}
