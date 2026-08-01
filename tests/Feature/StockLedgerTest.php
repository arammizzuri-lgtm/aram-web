<?php

namespace Tests\Feature;

use App\Enums\StockMovementType;
use App\Models\Currency;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\Inventory\StockLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    private StockLedger $ledger;

    private Product $product;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_base' => true]);

        $this->ledger = app(StockLedger::class);
        $this->warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main']);
        $this->product = Product::create([
            'sku' => 'TEST-001',
            'name' => 'Test Product',
            'product_category_id' => ProductCategory::create(['name' => 'Test', 'slug' => 'test'])->id,
            'cost_price' => 10,
            'selling_price' => 20,
        ]);
    }

    private function level(): StockLevel
    {
        return StockLevel::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->firstOrFail();
    }

    #[Test]
    public function receiving_stock_sets_the_quantity_and_cost(): void
    {
        $this->ledger->receive($this->product, $this->warehouse->id, 100, 107.6865);

        $level = $this->level();
        $this->assertSame('100.0000', $level->quantity);
        $this->assertSame('107.6865', $level->average_cost);
        $this->assertSame('10768.6500', $level->total_value);
    }

    /**
     * Two containers of the same product at different landed costs blend into
     * one running average — the figure every later margin is measured against.
     */
    #[Test]
    public function a_second_receipt_blends_into_a_weighted_average(): void
    {
        $this->ledger->receive($this->product, $this->warehouse->id, 100, 100.00);
        $this->ledger->receive($this->product, $this->warehouse->id, 300, 120.00);

        // (100×100 + 300×120) / 400 = 115.00
        $this->assertSame('115.0000', $this->level()->average_cost);
        $this->assertSame('400.0000', $this->level()->quantity);
        $this->assertSame('115.0000', $this->product->fresh()->average_cost);
    }

    #[Test]
    public function issuing_stock_leaves_the_average_alone_and_is_valued_at_it(): void
    {
        $this->ledger->receive($this->product, $this->warehouse->id, 100, 100.00);
        $this->ledger->receive($this->product, $this->warehouse->id, 300, 120.00);

        $movement = $this->ledger->issue($this->product, $this->warehouse->id, 50);

        $this->assertSame('115.0000', $movement->unit_cost, 'COGS is taken at the running average');
        $this->assertSame('-50.0000', $movement->quantity);
        $this->assertSame('115.0000', $this->level()->average_cost, 'issuing must not move the average');
        $this->assertSame('350.0000', $this->level()->quantity);
    }

    #[Test]
    public function the_running_balance_is_recorded_on_every_movement(): void
    {
        $this->ledger->receive($this->product, $this->warehouse->id, 100, 10);
        $this->ledger->issue($this->product, $this->warehouse->id, 30);
        $this->ledger->receive($this->product, $this->warehouse->id, 50, 12);

        $balances = StockMovement::orderBy('id')->pluck('balance_after')->all();

        $this->assertSame(['100.0000', '70.0000', '120.0000'], $balances);
    }

    /** A revaluation restates value without moving any stock. */
    #[Test]
    public function revaluing_changes_cost_but_not_quantity(): void
    {
        $this->ledger->receive($this->product, $this->warehouse->id, 40, 107.6865);
        $movement = $this->ledger->revalue($this->product, $this->warehouse->id, 111.4023);

        $this->assertSame('0.0000', $movement->quantity);
        $this->assertTrue($movement->is_revaluation);
        $this->assertSame('40.0000', $this->level()->quantity);
        $this->assertSame('111.4023', $this->level()->average_cost);

        // 40 × (111.4023 − 107.6865) = 148.632
        $this->assertEqualsWithDelta(148.632, (float) $movement->total_cost, 0.001);
    }

    #[Test]
    public function revaluing_nothing_on_hand_is_a_no_op(): void
    {
        $this->assertNull($this->ledger->revalue($this->product, $this->warehouse->id, 50));
    }

    #[Test]
    public function the_shipment_is_recorded_so_a_unit_can_be_traced_to_its_container(): void
    {
        $movement = $this->ledger->receive(
            $this->product, $this->warehouse->id, 10, 25.00,
            StockMovementType::PurchaseReceipt, shipmentId: null,
        );

        $this->assertSame(StockMovementType::PurchaseReceipt, $movement->type);
        $this->assertNotNull($movement->occurred_at);
    }

    #[Test]
    public function levels_always_reconcile_against_the_ledger(): void
    {
        $this->ledger->receive($this->product, $this->warehouse->id, 100, 10);
        $this->ledger->issue($this->product, $this->warehouse->id, 40);
        $this->ledger->receive($this->product, $this->warehouse->id, 25, 11);

        $this->assertSame([], $this->ledger->reconcile());
    }

    #[Test]
    public function the_reconciliation_check_detects_a_tampered_level(): void
    {
        $this->ledger->receive($this->product, $this->warehouse->id, 100, 10);

        // Simulate someone bypassing the ledger and writing the level directly.
        $this->level()->forceFill(['quantity' => 90])->save();

        $discrepancies = $this->ledger->reconcile();

        $this->assertCount(1, $discrepancies);
        $this->assertSame(90.0, $discrepancies[0]['level']);
        $this->assertSame(100.0, $discrepancies[0]['ledger']);
    }

    #[Test]
    public function negative_quantities_are_rejected(): void
    {
        $this->expectException(RuntimeException::class);

        $this->ledger->receive($this->product, $this->warehouse->id, -5, 10);
    }
}
