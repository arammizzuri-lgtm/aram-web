<?php

namespace Tests\Feature;

use App\Models\PriceListSection;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Deals\CatalogueLookup;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A deal line picks a size, and gets a cost to mark up.
 *
 * Nothing is sold at a stored price now, so the catalogue's job on this screen
 * has narrowed: say what the supplier charges, say who they are, and leave what
 * the customer pays to the person in front of the customer.
 */
class DealSizePricingTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Product $p13;

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

        $this->supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Supplier A', 'default_currency' => 'CNY',
        ]);
        $section = PriceListSection::create(['code' => 'crystals', 'name' => 'Crystals']);

        $crystal = Product::create([
            'name' => 'Crystal', 'supplier_id' => $this->supplier->id,
            'price_list_section_id' => $section->id,
        ]);

        $this->p13 = Product::create([
            'name' => 'P13',
            'parent_id' => $crystal->id,
            'supplier_id' => $this->supplier->id,
            'price_list_section_id' => $section->id,
            'unit_id' => Unit::query()->value('id'),
            'is_active' => true,
        ]);
    }

    private function lookup(): CatalogueLookup
    {
        return app(CatalogueLookup::class);
    }

    private function pricedSize(string $label = '20mm', float $cost = 0.9): ProductSize
    {
        return ProductSize::create([
            'product_id' => $this->p13->id,
            'label' => $label,
            'cost_price' => $cost,
            'currency' => 'CNY',
        ]);
    }

    #[Test]
    public function picking_a_size_fills_the_cost_and_the_supplier(): void
    {
        $size = $this->pricedSize();

        $found = $this->lookup()->resolve("size:{$size->id}");

        $this->assertSame(0.9, $found['unit_cost']);
        $this->assertSame('CNY', $found['cost_currency']);
        $this->assertSame($this->supplier->id, $found['supplier_id']);
        $this->assertSame($size->id, $found['product_size_id']);
        $this->assertSame($this->p13->id, $found['product_id']);
    }

    #[Test]
    public function the_line_is_described_by_its_whole_trail_not_just_the_size(): void
    {
        $size = $this->pricedSize();

        // "20mm" on an invoice is not a description of anything.
        $this->assertSame(
            'Crystal P13 · 20mm',
            $this->lookup()->resolve("size:{$size->id}")['description']
        );
    }

    #[Test]
    public function no_selling_price_comes_back_because_none_is_stored(): void
    {
        $size = $this->pricedSize();

        $this->assertNull($this->lookup()->resolve("size:{$size->id}")['list_price']);
    }

    #[Test]
    public function a_size_nobody_has_priced_is_not_offered_for_picking(): void
    {
        $this->pricedSize('20mm', 0.9);
        ProductSize::create(['product_id' => $this->p13->id, 'label' => '10mm']);

        $results = $this->lookup()->search('P13');
        $labels = implode(' ', $results);

        $this->assertStringContainsString('20mm', $labels);
        $this->assertStringNotContainsString('10mm', $labels);
    }

    #[Test]
    public function a_size_can_be_found_by_the_products_name_or_by_the_size_itself(): void
    {
        $size = $this->pricedSize();

        $this->assertArrayHasKey("size:{$size->id}", $this->lookup()->search('P13'));
        $this->assertArrayHasKey("size:{$size->id}", $this->lookup()->search('20mm'));
    }

    #[Test]
    public function an_already_picked_size_still_shows_what_it_is(): void
    {
        $size = $this->pricedSize();

        $label = $this->lookup()->label("size:{$size->id}");

        $this->assertStringContainsString('P13', $label);
        $this->assertStringContainsString('20mm', $label);
        $this->assertStringContainsString('Supplier A', $label);
    }

    #[Test]
    public function a_size_that_has_been_deleted_resolves_to_nothing(): void
    {
        $size = $this->pricedSize();
        $size->delete();

        $this->assertNull($this->lookup()->resolve("size:{$size->id}"));
        $this->assertNull($this->lookup()->label("size:{$size->id}"));
    }
}
