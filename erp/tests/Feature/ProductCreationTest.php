<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\PriceListSection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Adding a product does not ask what it costs.
 *
 * You add what a supplier sells long before you have agreed a price for it, so
 * the add screen collects the shape of the thing — where it sits, whose it is,
 * what sizes it comes in — and nothing else. Prices are typed later on the
 * Price Lists screen, and can still be argued with on the deal itself.
 */
class ProductCreationTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private PriceListSection $section;

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

        $this->supplier = Supplier::create(['code' => 'SUP-A', 'name' => 'Supplier A']);
        $this->section = PriceListSection::create(['code' => 'crystals', 'name' => 'Crystals']);
    }

    #[Test]
    public function a_product_is_added_without_any_price(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'price_list_section_id' => $this->section->id,
                'supplier_id' => $this->supplier->id,
                'name' => 'P13',
                'sizes' => [
                    ['label' => '10mm'],
                    ['label' => '20mm'],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('name', 'P13')->firstOrFail();

        $this->assertSame($this->supplier->id, $product->supplier_id);
        $this->assertSame($this->section->id, $product->price_list_section_id);

        // Every size arrives unpriced. That is the state the Price Lists screen
        // is there to fill in, and it has to survive the save to be findable.
        $this->assertSame(['10mm', '20mm'], $product->sizes->pluck('label')->all());
        $this->assertTrue($product->sizes->every(fn ($size) => $size->cost_price === null));
    }

    #[Test]
    public function the_add_screen_asks_for_no_prices_and_no_sku(): void
    {
        Livewire::test(CreateProduct::class)
            ->assertFormFieldExists('name')
            ->assertFormFieldExists('supplier_id')
            ->assertFormFieldExists('price_list_section_id')
            ->assertFormFieldDoesNotExist('sku')
            ->assertFormFieldDoesNotExist('cost_price')
            ->assertFormFieldDoesNotExist('selling_price')
            ->assertFormFieldDoesNotExist('product_category_id');
    }

    #[Test]
    public function a_product_hangs_under_the_one_above_it(): void
    {
        $crystal = Product::create([
            'name' => 'Crystal',
            'supplier_id' => $this->supplier->id,
            'price_list_section_id' => $this->section->id,
        ]);

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'price_list_section_id' => $this->section->id,
                'supplier_id' => $this->supplier->id,
                'parent_id' => $crystal->id,
                'name' => 'Flat Crystal',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            'Crystal › Flat Crystal',
            Product::where('name', 'Flat Crystal')->firstOrFail()->pathLabel()
        );
    }

    #[Test]
    public function the_supplier_who_owns_the_tree_is_the_one_it_is_reordered_from(): void
    {
        Livewire::test(CreateProduct::class)
            ->fillForm([
                'price_list_section_id' => $this->section->id,
                'supplier_id' => $this->supplier->id,
                'name' => 'P13',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Purchasing reads default_supplier_id; the form only asks once.
        $this->assertSame(
            $this->supplier->id,
            Product::where('name', 'P13')->firstOrFail()->default_supplier_id
        );
    }
}
