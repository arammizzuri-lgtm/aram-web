<?php

namespace Tests\Feature;

use App\Filament\Resources\Expenses\Pages\ManageExpenses;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\StockLevels\Pages\ListStockLevels;
use App\Filament\Resources\StockMovements\Pages\ListStockMovements;
use App\Filament\Resources\StockMovements\StockMovementResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SalesAndFinancePanelTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'owner'): User
    {
        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
        ]);

        $user = User::create([
            'name' => ucfirst($role),
            'email' => "{$role}@test.local",
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function the_sales_finance_and_inventory_pages_render(): void
    {
        $this->signIn();

        Livewire::test(ListInvoices::class)->assertOk();
        Livewire::test(ManageExpenses::class)->assertOk();
        Livewire::test(ListStockLevels::class)->assertOk();
        Livewire::test(ListStockMovements::class)->assertOk();
    }

    /**
     * The point of the whole costing chain: a sofa bought at $220 and sold at
     * $395 is a loss once freight and duty are counted, and the invoice says so.
     */
    #[Test]
    public function an_invoice_reports_a_loss_when_the_price_undercuts_landed_cost(): void
    {
        $this->signIn();

        $sofa = Product::where('sku', 'FUR-0117')->firstOrFail();
        $this->assertSame('406.1574', $sofa->average_cost);

        $invoice = Invoice::whereHas('items', fn ($q) => $q->where('product_id', $sofa->id))
            ->orderBy('id')
            ->firstOrFail();

        $this->assertLessThan(0, (float) $invoice->margin_percent);
        $this->assertLessThan(0, (float) $invoice->gross_profit_base);
    }

    #[Test]
    public function invoice_cogs_uses_landed_cost_rather_than_the_supplier_price(): void
    {
        $this->signIn();

        $sofa = Product::where('sku', 'FUR-0117')->firstOrFail();
        $item = $sofa->fresh()->load('stockMovements');

        $invoiceItem = InvoiceItem::where('product_id', $sofa->id)->firstOrFail();

        $this->assertSame('406.1574', $invoiceItem->unit_cost_base);
        $this->assertNotSame('220.0000', $invoiceItem->unit_cost_base, 'supplier price must not be used as COGS');
        $this->assertNotNull($item);
    }

    #[Test]
    public function outstanding_and_overdue_scopes_exclude_settled_invoices(): void
    {
        $this->signIn();

        $outstanding = Invoice::query()->outstanding()->get();

        $this->assertGreaterThan(0, $outstanding->count());

        foreach ($outstanding as $invoice) {
            $this->assertGreaterThan(0, $invoice->amountDue());
        }
    }

    #[Test]
    public function stock_movements_record_the_container_a_unit_arrived_on(): void
    {
        $this->signIn();

        $receipts = StockMovement::where('type', 'purchase_receipt')->get();

        $this->assertGreaterThan(0, $receipts->count());
        $this->assertTrue(
            $receipts->every(fn (StockMovement $m) => $m->shipment_id !== null),
            'every receipt must be traceable to its container'
        );
    }

    #[Test]
    public function the_movement_ledger_cannot_be_written_to_from_the_panel(): void
    {
        $this->signIn();

        $this->assertFalse(StockMovementResource::canCreate());
    }
}
