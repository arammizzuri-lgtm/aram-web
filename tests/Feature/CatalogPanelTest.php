<?php

namespace Tests\Feature;

use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Filament\Resources\ProductCategories\Pages\ManageProductCategories;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Shipments\Pages\ListShipments;
use App\Filament\Resources\Shipments\Pages\ViewShipmentCosting;
use App\Filament\Resources\Suppliers\Pages\ManageSuppliers;
use App\Models\Product;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CatalogPanelTest extends TestCase
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
    public function every_catalog_and_logistics_page_renders(): void
    {
        $this->signIn();

        Livewire::test(ListProducts::class)->assertOk();
        Livewire::test(ManageProductCategories::class)->assertOk();
        Livewire::test(ManageSuppliers::class)->assertOk();
        Livewire::test(ManageCustomers::class)->assertOk();
        Livewire::test(ListShipments::class)->assertOk();
    }

    #[Test]
    public function the_seeded_catalogue_covers_the_imported_product_lines(): void
    {
        $this->signIn();

        foreach (['Crystals', 'Furniture', 'Fabrics & Textiles', 'Home Decoration', 'Building Materials'] as $category) {
            $this->assertDatabaseHas('product_categories', ['name' => $category]);
        }

        // The Chinese name is what gets pasted into WeChat.
        $this->assertDatabaseHas('products', ['sku' => 'CRY-0042', 'name_zh' => '水晶吊灯 A-330']);
        $this->assertDatabaseHas('suppliers', ['name_zh' => '宁波照明有限公司']);
    }

    #[Test]
    public function the_seeded_container_carries_the_worked_example_landed_costs(): void
    {
        $this->signIn();

        // Costs the demo seeder applied via the real engine, not hardcoded values.
        $this->assertSame('107.6865', Product::where('sku', 'CRY-0042')->value('average_cost'));
        $this->assertSame('406.1574', Product::where('sku', 'FUR-0117')->value('average_cost'));
        $this->assertSame('25.1892', Product::where('sku', 'FAB-0233')->value('average_cost'));
    }

    #[Test]
    public function the_landed_cost_workbench_renders_for_the_seeded_container(): void
    {
        $this->signIn();

        $shipment = Shipment::where('container_number', 'TCLU8877661')->firstOrFail();

        Livewire::test(ViewShipmentCosting::class, ['record' => $shipment->getKey()])
            ->assertOk()
            ->assertSee('SHP-')
            ->assertSee('Crystal Chandelier A-330 · 8 Light Gold');
    }

    #[Test]
    public function recalculating_from_the_workbench_produces_a_new_run(): void
    {
        $this->signIn();

        $shipment = Shipment::where('container_number', 'TCLU8877661')->firstOrFail();
        $before = $shipment->landedCostRuns()->max('version');

        Livewire::test(ViewShipmentCosting::class, ['record' => $shipment->getKey()])
            ->callAction('recalculate');

        $this->assertSame($before + 1, $shipment->fresh()->landedCostRuns()->max('version'));
    }

    /**
     * Landed cost, supplier pricing and margin are what a departing employee could
     * hand to a competitor, so the restriction is a permission rather than a
     * convention — and it has to hold in the table, not just the navigation.
     */
    #[Test]
    public function sales_cannot_see_cost_or_margin_columns(): void
    {
        $sales = $this->signIn('sales');

        $this->assertFalse($sales->can('view_cost'));

        Livewire::test(ListProducts::class)
            ->assertOk()
            ->assertTableColumnHidden('average_cost')
            ->assertTableColumnHidden('margin')
            ->assertTableColumnVisible('selling_price');
    }

    #[Test]
    public function an_owner_does_see_cost_and_margin(): void
    {
        $this->signIn('owner');

        Livewire::test(ListProducts::class)
            ->assertOk()
            ->assertTableColumnVisible('average_cost')
            ->assertTableColumnVisible('margin');
    }
}
