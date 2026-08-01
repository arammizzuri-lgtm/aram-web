<?php

namespace Tests\Feature;

use App\Actions\Purchasing\ConfirmPurchaseOrder;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Pages\ListPurchaseOrders;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\StockLevel;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PurchaseOrderBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Product $crystal;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
        ]);

        $user = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $user->assignRole('owner');
        $this->actingAs($user);

        $this->supplier = Supplier::where('code', 'SUP-NBL')->firstOrFail();
        $this->crystal = Product::where('sku', 'CRY-0042')->firstOrFail();
        $this->warehouse = Warehouse::where('code', 'MAIN')->firstOrFail();
    }

    private function order(float $cartons = 10, float $packSize = 2, float $price = 85): PurchaseOrder
    {
        $order = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => today(),
            'status' => 'draft',
            'currency' => 'USD',
            'deposit_percent' => 30,
        ]);

        PurchaseOrderItem::create([
            'purchase_order_id' => $order->id,
            'product_id' => $this->crystal->id,
            'order_quantity' => $cartons,
            'pack_size' => $packSize,
            'quantity' => $cartons * $packSize,
            'unit_price' => $price,
        ]);

        $order->recalculateTotals();

        return $order->fresh(['items.product', 'supplier']);
    }

    // ------------------------------------------------------- carton conversion

    /** Ordered in cartons, stocked in pieces — the conversion has to be explicit. */
    #[Test]
    public function cartons_convert_to_pieces_on_the_line(): void
    {
        $order = $this->order(cartons: 10, packSize: 24);

        $item = $order->items->first();

        $this->assertSame('10.0000', $item->order_quantity);
        $this->assertSame('24.0000', $item->pack_size);
        $this->assertSame('240.0000', $item->quantity, '10 cartons of 24 is 240 pieces');
    }

    #[Test]
    public function the_line_total_is_priced_per_piece_not_per_carton(): void
    {
        $order = $this->order(cartons: 10, packSize: 24, price: 85);

        // 240 pieces × $85, not 10 × $85.
        $this->assertSame('20400.00', $order->items->first()->line_total);
        $this->assertSame('20400.00', $order->total);
    }

    // -------------------------------------------------------- incoming stock

    #[Test]
    public function confirming_books_the_quantities_as_incoming(): void
    {
        $before = (float) StockLevel::where('product_id', $this->crystal->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('incoming_quantity');

        app(ConfirmPurchaseOrder::class)->handle($this->order(cartons: 10, packSize: 2));

        $after = (float) StockLevel::where('product_id', $this->crystal->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->value('incoming_quantity');

        $this->assertSame($before + 20, $after);
    }

    #[Test]
    public function confirming_records_who_approved_it_and_when_money_is_due(): void
    {
        $order = app(ConfirmPurchaseOrder::class)->handle($this->order());

        $this->assertSame(PurchaseOrderStatus::Confirmed, $order->status);
        $this->assertNotNull($order->approved_by);
        $this->assertNotNull($order->deposit_due_date);
        $this->assertNotNull($order->balance_due_date);
    }

    #[Test]
    public function cancelling_takes_the_incoming_quantities_back_off(): void
    {
        $action = app(ConfirmPurchaseOrder::class);
        $order = $this->order(cartons: 10, packSize: 2);

        $action->handle($order);
        $confirmed = (float) StockLevel::where('product_id', $this->crystal->id)->value('incoming_quantity');

        $action->cancel($order->fresh(['items']));
        $cancelled = (float) StockLevel::where('product_id', $this->crystal->id)->value('incoming_quantity');

        $this->assertSame($confirmed - 20, $cancelled);
        $this->assertSame('cancelled', $order->fresh()->status->value);
    }

    #[Test]
    public function an_order_cannot_be_confirmed_twice(): void
    {
        $order = $this->order();
        $action = app(ConfirmPurchaseOrder::class);
        $action->handle($order);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already');

        $action->handle($order->fresh(['items.product', 'supplier']));
    }

    #[Test]
    public function an_empty_order_cannot_be_confirmed(): void
    {
        $order = PurchaseOrder::create([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => today(),
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no lines');

        app(ConfirmPurchaseOrder::class)->handle($order);
    }

    /** An unpriced line becomes a supplier invoice nobody can check. */
    #[Test]
    public function a_line_without_a_price_blocks_confirmation(): void
    {
        $order = $this->order(price: 0);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no price');

        app(ConfirmPurchaseOrder::class)->handle($order);
    }

    // -------------------------------------------------------------- reporting

    #[Test]
    public function received_progress_is_reported_per_order(): void
    {
        $order = $this->order(cartons: 10, packSize: 2);

        $this->assertSame(0.0, $order->receivedPercent());

        $order->items->first()->forceFill(['received_quantity' => 10])->save();

        $this->assertSame(50.0, $order->fresh()->receivedPercent());
    }

    #[Test]
    public function the_order_list_renders(): void
    {
        Livewire::test(ListPurchaseOrders::class)->assertOk();
    }
}
