<?php

namespace Tests\Feature;

use App\Actions\Sales\ConfirmSalesOrder;
use App\Actions\Sales\CreditLimitExceeded;
use App\Filament\Resources\SalesOrders\Pages\ListSalesOrders;
use App\Filament\Resources\SalesOrders\SalesOrderResource;
use App\Models\Customer;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\StockLevel;
use App\Models\StockReservation;
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

class SalesOrderBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

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

        $this->signIn('owner');

        $this->customer = Customer::where('code', 'CUS-0001')->firstOrFail();
        $this->crystal = Product::where('sku', 'CRY-0042')->firstOrFail();
        $this->warehouse = Warehouse::where('code', 'MAIN')->firstOrFail();
    }

    private function signIn(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => "{$role}-".uniqid().'@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    private function order(float $quantity = 5, float $price = 155, ?Customer $customer = null): SalesOrder
    {
        $order = SalesOrder::create([
            'customer_id' => ($customer ?? $this->customer)->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => today(),
            'status' => 'draft',
            'currency' => 'USD',
        ]);

        SalesOrderItem::create([
            'sales_order_id' => $order->id,
            'product_id' => $this->crystal->id,
            'quantity' => $quantity,
            'unit_price' => $price,
        ]);

        $order->recalculateTotals();

        return $order->fresh(['items.product', 'customer']);
    }

    // ----------------------------------------------------------- reservations

    #[Test]
    public function confirming_reserves_the_stock(): void
    {
        $order = $this->order(quantity: 5);

        app(ConfirmSalesOrder::class)->handle($order);

        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertTrue((bool) $order->fresh()->is_reserved);

        $level = StockLevel::where('product_id', $this->crystal->id)->firstOrFail();
        $this->assertSame('5.0000', $level->reserved_quantity);
    }

    /** Available is on hand minus reserved — that is what Sales may promise. */
    #[Test]
    public function reserved_stock_is_no_longer_available(): void
    {
        $before = $this->crystal->stockAvailable($this->warehouse->id);

        app(ConfirmSalesOrder::class)->handle($this->order(quantity: 5));

        $this->assertSame($before - 5, $this->crystal->fresh()->stockAvailable($this->warehouse->id));
    }

    #[Test]
    public function releasing_puts_the_stock_back(): void
    {
        $order = $this->order(quantity: 5);
        $action = app(ConfirmSalesOrder::class);

        $action->handle($order);
        $action->release($order->fresh(['items']));

        $this->assertSame('0.0000', StockLevel::where('product_id', $this->crystal->id)->value('reserved_quantity'));
        $this->assertSame('released', StockReservation::first()->status);
    }

    #[Test]
    public function an_order_cannot_reserve_more_than_is_available(): void
    {
        $available = $this->crystal->stockAvailable($this->warehouse->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ordered');

        app(ConfirmSalesOrder::class)->handle($this->order(quantity: $available + 10));
    }

    /** Two salespeople must not be able to promise the same boxes. */
    #[Test]
    public function stock_already_reserved_cannot_be_reserved_again(): void
    {
        $available = $this->crystal->stockAvailable($this->warehouse->id);
        $action = app(ConfirmSalesOrder::class);

        $action->handle($this->order(quantity: $available));

        $this->expectException(RuntimeException::class);

        $action->handle($this->order(quantity: 1));
    }

    #[Test]
    public function an_empty_order_cannot_be_confirmed(): void
    {
        $order = SalesOrder::create([
            'customer_id' => $this->customer->id,
            'warehouse_id' => $this->warehouse->id,
            'order_date' => today(),
            'status' => 'draft',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no lines');

        app(ConfirmSalesOrder::class)->handle($order);
    }

    // ------------------------------------------------------------------ credit

    #[Test]
    public function an_order_beyond_the_credit_limit_is_blocked(): void
    {
        $this->customer->update(['credit_limit' => 100]);

        $this->expectException(CreditLimitExceeded::class);
        $this->expectExceptionMessage('manager must approve');

        app(ConfirmSalesOrder::class)->handle($this->order(quantity: 5, price: 155));
    }

    #[Test]
    public function a_manager_can_approve_past_the_limit_and_it_is_recorded(): void
    {
        $this->customer->update(['credit_limit' => 100]);
        $manager = $this->signIn('manager');

        $order = app(ConfirmSalesOrder::class)->handle($this->order(quantity: 5), creditApproved: true);

        $this->assertSame('confirmed', $order->status);
        $this->assertSame($manager->id, $order->credit_approved_by);
        $this->assertNotNull($order->credit_approved_at);
    }

    #[Test]
    public function a_blocked_customer_cannot_order_at_all(): void
    {
        $this->customer->update(['is_blocked' => true, 'blocked_reason' => 'Payment dispute']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment dispute');

        app(ConfirmSalesOrder::class)->handle($this->order());
    }

    #[Test]
    public function an_order_within_the_limit_needs_no_approval(): void
    {
        $this->customer->update(['credit_limit' => 100000]);

        $order = app(ConfirmSalesOrder::class)->handle($this->order());

        $this->assertNull($order->credit_approved_by);
        $this->assertSame('confirmed', $order->status);
    }

    #[Test]
    public function a_confirmed_order_cannot_be_confirmed_twice(): void
    {
        $order = $this->order();
        $action = app(ConfirmSalesOrder::class);
        $action->handle($order);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already');

        $action->handle($order->fresh(['items.product', 'customer']));
    }

    // ----------------------------------------------------------------- pricing

    #[Test]
    public function a_tier_price_beats_the_list_price(): void
    {
        $tier = PriceTier::where('code', 'VIP')->firstOrFail();

        ProductPrice::create([
            'product_id' => $this->crystal->id,
            'price_tier_id' => $tier->id,
            'currency' => 'USD',
            'price' => 140,
            'min_quantity' => 1,
        ]);

        $this->assertSame(140.0, SalesOrderResource::priceFor($this->crystal, $tier->id));
        $this->assertSame(155.0, SalesOrderResource::priceFor($this->crystal, null));
    }

    #[Test]
    public function quantity_breaks_pick_the_right_tier_price(): void
    {
        $tier = PriceTier::where('code', 'VIP')->firstOrFail();

        foreach ([[1, 150], [50, 135]] as [$min, $price]) {
            ProductPrice::create([
                'product_id' => $this->crystal->id,
                'price_tier_id' => $tier->id,
                'currency' => 'USD',
                'price' => $price,
                'min_quantity' => $min,
            ]);
        }

        // inForce orders by min_quantity desc, so the largest applicable break wins.
        $this->assertSame('135.0000', ProductPrice::query()
            ->where('product_id', $this->crystal->id)
            ->inForce($tier->id, 60)
            ->value('price'));

        $this->assertSame('150.0000', ProductPrice::query()
            ->where('product_id', $this->crystal->id)
            ->inForce($tier->id, 5)
            ->value('price'));
    }

    // -------------------------------------------------------------------- page

    #[Test]
    public function the_order_list_renders(): void
    {
        $this->order();

        Livewire::test(ListSalesOrders::class)->assertOk();
    }
}
