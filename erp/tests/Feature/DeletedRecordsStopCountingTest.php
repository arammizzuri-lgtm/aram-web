<?php

namespace Tests\Feature;

use App\Filament\Pages\RecentlyDeleted;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\DealPurchase;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Deals\SupplierPaymentWriter;
use App\Services\Deletion\DeletionImpact;
use App\Services\Reporting\BusinessMetrics;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Delete the paperwork and the figures must go with it.
 *
 * Deleting is reversible everywhere in this system, which is only safe if a
 * deleted record stops counting. Where it does not, deletion silently produces
 * a half-truth: the debt disappears and the payment against it does not, so a
 * supplier you have finished with reports a *negative* balance — the system
 * saying they owe you money you never lent them.
 *
 * That is what happened. Three suppliers sat at −$208.96, −$690.30 and
 * −$1,828.36 with no deals, no purchases and no invoices left in the system,
 * and the dashboard still reported $21.35 lost on transfers that no longer had
 * a purchase to belong to.
 *
 * The same shape of bug had already been fixed once, for customer payment
 * allocations. These tests are the general rule: nothing outlives its parent.
 */
class DeletedRecordsStopCountingTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

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

        $this->supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu Crystals', 'default_currency' => 'CNY',
        ]);

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
            'supplier_id' => $this->supplier->id,
            'description' => 'Crystal P07 20mm',
            'quantity' => 500,
            'unit_cost' => 12.50,
            'cost_currency' => 'CNY',
            'unit_price' => 28000,
        ]);

        $this->deal = app(DealWriter::class)->sync($this->deal->fresh());

        // ¥6,250 at 7.2 is $868.06; it really cost $890, so $21.94 went to the
        // exchange house. Both halves matter below.
        app(SupplierPaymentWriter::class)->record(
            purchase: $this->purchase(),
            amount: 6250,
            actualCostBase: 890,
        );
    }

    private function purchase(): DealPurchase
    {
        return $this->deal->purchases()->with(['lines', 'costs', 'payments'])->firstOrFail();
    }

    private function metrics(): BusinessMetrics
    {
        return app(BusinessMetrics::class);
    }

    private function losses(): float
    {
        return $this->metrics()->transferLosses(today()->subDays(30), today())->toFloat();
    }

    // ------------------------------------------------------------- the setup

    /** The figures are real before anything is deleted. */
    #[Test]
    public function the_books_balance_while_everything_is_there(): void
    {
        $this->assertSame(0.0, $this->supplier->fresh()->outstandingBalance(), 'ordered and paid cancel out');
        $this->assertSame(21.94, round($this->losses(), 2));
    }

    // -------------------------------------------------- deleting a purchase

    /**
     * The reported bug, in one assertion.
     *
     * Deleting the purchase takes the debt away. The payment against it stayed,
     * so what remained was a supplier who appeared to owe you $868.
     */
    #[Test]
    public function deleting_a_purchase_does_not_leave_the_supplier_owing_you_money(): void
    {
        $this->purchase()->delete();

        $balance = $this->supplier->fresh()->outstandingBalance();

        $this->assertSame(0.0, $balance, 'a deleted purchase settles to nothing, not to a credit');
        $this->assertGreaterThanOrEqual(0.0, $balance);
    }

    /** The other half of the screenshot: $21.35 with nothing left to explain it. */
    #[Test]
    public function a_deleted_purchase_takes_its_transfer_loss_out_of_the_reports(): void
    {
        $this->purchase()->delete();

        $this->assertSame(0.0, round($this->losses(), 2));
    }

    /**
     * Deleting is reversible, so the figures must come back with the record.
     *
     * This is why the payment is not deleted along with the purchase: it stops
     * counting rather than disappearing, exactly as soft deletion does
     * everywhere else.
     */
    #[Test]
    public function restoring_the_purchase_brings_its_money_back(): void
    {
        $purchase = $this->purchase();
        $purchase->delete();
        $purchase->restore();

        $this->assertSame(0.0, $this->supplier->fresh()->outstandingBalance());
        $this->assertSame(21.94, round($this->losses(), 2));
        $this->assertSame(868.06, round($this->purchase()->paidBase()->toFloat(), 2));
    }

    /** A payment that never had a purchase is not orphaned and keeps counting. */
    #[Test]
    public function a_payment_recorded_against_no_purchase_still_counts(): void
    {
        $this->purchase()->delete();

        SupplierPayment::create([
            'supplier_id' => $this->supplier->id,
            'deal_purchase_id' => null,
            'number' => 'SP-2026-9999',
            'amount' => 720,
            'currency' => 'CNY',
            'exchange_rate' => 7.2,
            'base_amount' => 100,
            'actual_cost_base' => 110,
            'method' => 'exchange',
            'paid_at' => today(),
        ]);

        $this->assertSame(-100.0, $this->supplier->fresh()->outstandingBalance());
        $this->assertSame(10.0, round($this->losses(), 2));
    }

    // ------------------------------------------------------ deleting a deal

    /**
     * A deal's delete dialog promises "its figures leave the reports".
     *
     * They did not. The purchases underneath it kept counting as money owed to
     * suppliers, so deleting a deal you never went through with left its costs
     * on the dashboard with nothing on any screen to trace them back to.
     */
    #[Test]
    public function deleting_a_deal_takes_its_purchases_out_of_the_reports(): void
    {
        $this->assertTrue($this->metrics()->payables()->toFloat() >= 0.0);

        $owedBefore = $this->metrics()->payables()->toFloat();
        $this->assertSame(0.0, round($owedBefore, 2), 'paid in full, so nothing is owed');

        // Something still owed, so the figure is not trivially zero.
        DealLine::create([
            'deal_id' => $this->deal->id,
            'supplier_id' => $this->supplier->id,
            'description' => 'Second lot',
            'quantity' => 100,
            'unit_cost' => 10,
            'cost_currency' => 'CNY',
            'unit_price' => 30000,
        ]);
        app(DealWriter::class)->sync($this->deal->fresh());

        $this->assertGreaterThan(0.0, $this->metrics()->payables()->toFloat());

        $this->deal->delete();

        $this->assertSame(
            0.0,
            round($this->metrics()->payables()->toFloat(), 2),
            'a deleted deal leaves nothing owed behind it',
        );
        $this->assertSame(0.0, round($this->losses(), 2));
        $this->assertSame(0.0, $this->supplier->fresh()->outstandingBalance());
    }

    #[Test]
    public function restoring_the_deal_brings_its_purchases_back(): void
    {
        $this->deal->delete();
        $this->deal->restore();

        $this->assertSame(1, $this->deal->purchases()->count());
        $this->assertSame(21.94, round($this->losses(), 2));
    }

    // -------------------------------------------------------- the way back

    /**
     * Hiding a row from the reports must never hide it from the bin.
     *
     * The scopes above are what make deletion honest; applied to Recently
     * deleted they would make it permanent. Delete a purchase and then its
     * deal, and the purchase would vanish from the one screen whose entire job
     * is to give it back.
     */
    #[Test]
    public function a_purchase_deleted_under_a_deleted_deal_is_still_in_the_bin(): void
    {
        $purchase = $this->purchase();
        $purchase->delete();
        $this->deal->delete();

        Livewire::test(RecentlyDeleted::class)
            ->assertOk()
            ->assertSee($purchase->number);
    }

    #[Test]
    public function and_it_can_still_be_restored_from_there(): void
    {
        $purchase = $this->purchase();
        $purchase->delete();
        $this->deal->delete();

        Livewire::test(RecentlyDeleted::class)
            ->callAction(TestAction::make('restore')->table(DealPurchase::class.':'.$purchase->id));

        $this->deal->restore();

        $this->assertSame(1, $this->deal->purchases()->count());
        $this->assertSame(21.94, round($this->losses(), 2));
    }

    // ------------------------------------------------------- foreign keys

    /**
     * A hidden row still holds its foreign key.
     *
     * `deal_purchases.supplier_id` is restrict-on-delete. Counted through the
     * scope, a supplier whose only purchase sits under a deleted deal reads as
     * having nothing behind it — so the dialog would offer "Delete permanently"
     * and the database would refuse.
     */
    #[Test]
    public function a_supplier_cannot_be_erased_while_a_hidden_purchase_points_at_it(): void
    {
        $this->deal->delete();

        $this->assertSame(0, $this->supplier->purchases()->count(), 'hidden, as intended');
        $this->assertFalse(app(DeletionImpact::class)->canBeErased($this->supplier->fresh()));
    }

    /**
     * The safety check must not depend on who is looking at it.
     *
     * `canBeErased()` is the same list of sentences the dialog prints, and one
     * of those sentences is filtered by `view_cost` — so on its own it would
     * make a deal un-erasable for you and erasable for an assistant. Erasing
     * cascades the purchases away and nulls the payments' link to them, leaving
     * orphans no scope can recognise afterwards.
     *
     * It holds today through a second, unfiltered line, which is a coincidence
     * of wording rather than a design. Pinned here so that rewording either one
     * cannot quietly open it.
     */
    #[Test]
    public function whether_a_deal_can_be_erased_does_not_depend_on_seeing_cost(): void
    {
        $impact = app(DeletionImpact::class);

        $this->assertFalse($impact->canBeErased($this->deal));

        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        $this->assertFalse($assistant->can('view_cost'), 'the premise of this test');
        $this->assertFalse($impact->canBeErased($this->deal->fresh()));
    }
}
