<?php

namespace Tests\Feature;

use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Models\CollectionPoint;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealProgress;
use App\Services\Deals\DealWriter;
use App\Services\Shipping\ConsignmentWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A deal that can get to the end of its own life.
 *
 * Nine states were declared and three were reachable: a quotation moved a deal
 * to `quoted`, approval to `approved`, and there it stopped forever. Nothing
 * became `purchasing`, `shipping`, `arrived`, `delivered` or `closed`, so the
 * status filter offered choices that matched nothing, the dashboard's
 * "delivered but not invoiced" alert could never once fire, and no deal you
 * ever did could be finished with.
 */
class DealProgressTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Supplier $supplier;

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
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'USD', 'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY',
        ]);
    }

    private function deal(string $status = 'draft'): Deal
    {
        return Deal::create([
            'number' => 'D-2026-'.str_pad((string) (Deal::count() + 1), 4, '0', STR_PAD_LEFT),
            'customer_id' => $this->customer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
            'status' => $status,
        ]);
    }

    private function lineOn(Deal $deal, ?int $supplierId = null): DealLine
    {
        return DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $supplierId,
            'description' => 'Crystal P07',
            'quantity' => 10,
            'unit_cost' => 12.5,
            'cost_currency' => 'CNY',
            'unit_price' => 3,
        ]);
    }

    private function consignment(string $status, Deal ...$deals): Consignment
    {
        $consignment = Consignment::create([
            'tracking_number' => '169'.Consignment::count().'0',
            'mode' => 'sea',
            'collection_point_id' => CollectionPoint::query()->value('id'),
            'boxes' => 1,
            'gross_weight_kg' => 18.5,
            'cbm' => 0.11,
            'status' => $status,
        ]);

        $consignment->deals()->attach(collect($deals)->pluck('id')->all());

        return $consignment;
    }

    // ------------------------------------------------------- moving forward

    #[Test]
    public function naming_a_supplier_starts_the_deal_buying(): void
    {
        $deal = $this->deal('approved');
        $this->lineOn($deal, $this->supplier->id);

        app(DealWriter::class)->sync($deal->fresh());

        $this->assertSame('purchasing', $deal->fresh()->status);
    }

    /** A line with nobody to buy it from is not yet a purchase. */
    #[Test]
    public function a_deal_with_no_supplier_named_stays_where_it_was(): void
    {
        $deal = $this->deal('quoted');
        $this->lineOn($deal, null);

        app(DealWriter::class)->sync($deal->fresh());

        $this->assertSame('quoted', $deal->fresh()->status);
    }

    #[Test]
    public function goods_in_transfer_put_the_deal_into_shipping(): void
    {
        $deal = $this->deal('purchasing');

        app(ConsignmentWriter::class)->syncDealStatuses($this->consignment('in_transfer', $deal));

        $this->assertSame('shipping', $deal->fresh()->status);
    }

    #[Test]
    public function arrived_and_delivered_goods_carry_the_deal_with_them(): void
    {
        $arriving = $this->deal('shipping');
        app(ConsignmentWriter::class)->syncDealStatuses($this->consignment('arrived', $arriving));
        $this->assertSame('arrived', $arriving->fresh()->status);

        $delivering = $this->deal('shipping');
        app(ConsignmentWriter::class)->syncDealStatuses($this->consignment('delivered', $delivering));
        $this->assertSame('delivered', $delivering->fresh()->status);
    }

    /** One tracking number can carry several customers' goods. */
    #[Test]
    public function every_deal_on_a_consignment_moves_together(): void
    {
        $first = $this->deal('purchasing');
        $second = $this->deal('approved');

        app(ConsignmentWriter::class)->syncDealStatuses($this->consignment('in_transfer', $first, $second));

        $this->assertSame('shipping', $first->fresh()->status);
        $this->assertSame('shipping', $second->fresh()->status);
    }

    /** Logging a tracking number before the goods move says nothing new. */
    #[Test]
    public function a_consignment_merely_awaiting_collection_moves_nothing(): void
    {
        $deal = $this->deal('purchasing');

        app(ConsignmentWriter::class)->syncDealStatuses($this->consignment('awaiting', $deal));

        $this->assertSame('purchasing', $deal->fresh()->status);
    }

    // ----------------------------------------------------- never backwards

    /**
     * These calls recur — every save re-syncs the purchases, every consignment
     * edit re-checks its status. If they could move a deal backwards, adding a
     * line to a delivered deal would drag it back to purchasing, and the status
     * would describe the last thing you touched rather than where the deal is.
     */
    #[Test]
    public function a_deal_that_has_moved_on_is_never_dragged_back(): void
    {
        $deal = $this->deal('delivered');
        $this->lineOn($deal, $this->supplier->id);

        app(DealWriter::class)->sync($deal->fresh());

        $this->assertSame('delivered', $deal->fresh()->status);
    }

    #[Test]
    public function a_cancelled_deal_is_left_entirely_alone(): void
    {
        $deal = $this->deal('cancelled');
        $this->lineOn($deal, $this->supplier->id);

        app(DealWriter::class)->sync($deal->fresh());
        app(ConsignmentWriter::class)->syncDealStatuses($this->consignment('arrived', $deal));

        $this->assertSame('cancelled', $deal->fresh()->status);
    }

    /** An ending a background event can undo is not an ending. */
    #[Test]
    public function a_closed_deal_stays_closed(): void
    {
        $deal = $this->deal('closed');

        app(ConsignmentWriter::class)->syncDealStatuses($this->consignment('in_transfer', $deal));

        $this->assertSame('closed', $deal->fresh()->status);
    }

    #[Test]
    public function cancelling_is_a_decision_and_never_happens_by_itself(): void
    {
        $deal = $this->deal('draft');

        app(DealProgress::class)->advanceTo($deal, 'cancelled');

        $this->assertSame('draft', $deal->fresh()->status);
    }

    // ------------------------------------------------------------ by hand

    /**
     * The system does not see the phone call telling you the goods were handed
     * over, so the stage has to be settable — otherwise a deal that is finished
     * with stays on your list forever.
     */
    #[Test]
    public function the_stage_can_be_settled_by_hand_on_the_deal_screen(): void
    {
        $deal = $this->deal('arrived');
        $this->lineOn($deal, $this->supplier->id);

        Livewire::test(EditDeal::class, ['record' => $deal->getRouteKey()])
            ->fillForm(['status' => 'closed'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('closed', $deal->fresh()->status);
    }

    /** Every state in the list is a state a deal can actually be in. */
    #[Test]
    public function the_declared_states_and_the_reachable_ones_are_the_same_set(): void
    {
        $declared = array_keys(Deal::STATUSES);
        $reachable = array_merge(DealProgress::ORDER, ['cancelled']);

        sort($declared);
        sort($reachable);

        $this->assertSame($declared, $reachable);
    }
}
