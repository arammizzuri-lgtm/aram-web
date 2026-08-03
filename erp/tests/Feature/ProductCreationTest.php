<?php

namespace Tests\Feature;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A product has to be addable before its cost is known.
 *
 * cost_price is NOT NULL DEFAULT 0, and a column default only applies when the
 * column is left out of the insert entirely. An empty "Typical cost" box sent
 * an explicit null instead, so the save died on an integrity constraint —
 * which is exactly what happens when you are adding something you have a
 * selling price for and have not agreed a cost on yet.
 */
class ProductCreationTest extends TestCase
{
    use RefreshDatabase;

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
    }

    #[Test]
    public function a_product_saves_with_the_cost_left_blank(): void
    {
        $category = ProductCategory::create(['name' => 'Lighting']);
        $unit = Unit::first();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku' => 'LULU WHITE',
                'name' => 'LULU WHITE',
                'product_category_id' => $category->id,
                'unit_id' => $unit->id,
                'cost_price' => null,
                'selling_price' => 15,
                'sellPrices' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::where('sku', 'LULU WHITE')->firstOrFail();

        $this->assertSame('0.0000', (string) $product->cost_price);
        $this->assertSame('15.0000', (string) $product->selling_price);
    }

    #[Test]
    public function a_cost_that_is_given_is_kept(): void
    {
        $category = ProductCategory::create(['name' => 'Lighting']);
        $unit = Unit::first();

        Livewire::test(CreateProduct::class)
            ->fillForm([
                'sku' => 'LULU AMBER',
                'name' => 'LULU AMBER',
                'product_category_id' => $category->id,
                'unit_id' => $unit->id,
                'cost_price' => 9.25,
                'selling_price' => 15,
                'sellPrices' => [],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(
            '9.2500',
            (string) Product::where('sku', 'LULU AMBER')->firstOrFail()->cost_price
        );
    }
}
