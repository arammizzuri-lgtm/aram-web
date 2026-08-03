<?php

namespace Tests\Feature;

use App\Models\PriceListSection;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The catalogue as it is actually shaped.
 *
 * Crystal holds Flat Crystal holds P13, and only P13 is a thing anyone buys —
 * in sizes, each with its own cost. The depth varies by section, the tree
 * belongs to one supplier from the top down, and a product is added long before
 * anyone knows what it costs.
 */
class ProductTreeTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private PriceListSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->supplier = Supplier::create(['code' => 'SUP-A', 'name' => 'Supplier A']);
        $this->section = PriceListSection::create(['code' => 'crystals', 'name' => 'Crystals']);
    }

    private function product(string $name, ?Product $parent = null): Product
    {
        return Product::create([
            'name' => $name,
            'parent_id' => $parent?->id,
            'supplier_id' => $this->supplier->id,
            'price_list_section_id' => $this->section->id,
        ]);
    }

    #[Test]
    public function a_product_nests_as_deep_as_the_catalogue_needs(): void
    {
        $crystal = $this->product('Crystal');
        $flat = $this->product('Flat Crystal', $crystal);
        $p13 = $this->product('P13', $flat);

        $this->assertSame('Crystal › Flat Crystal › P13', $p13->pathLabel());
        $this->assertTrue($crystal->isShelf());
        $this->assertFalse($p13->isShelf());

        $this->assertEqualsCanonicalizing(
            ['Crystal'],
            Product::roots()->pluck('name')->all()
        );
    }

    #[Test]
    public function each_supplier_keeps_its_own_tree(): void
    {
        $other = Supplier::create(['code' => 'SUP-B', 'name' => 'Supplier B']);

        $this->product('Crystal');
        Product::create([
            'name' => 'Crystal',
            'supplier_id' => $other->id,
            'price_list_section_id' => $this->section->id,
        ]);

        $this->assertSame(1, Product::where('supplier_id', $this->supplier->id)->count());
        $this->assertSame(1, Product::where('supplier_id', $other->id)->count());
    }

    #[Test]
    public function a_sku_is_generated_when_none_is_given(): void
    {
        $this->assertSame('CRYS-P13', $this->product('P13')->sku);
    }

    #[Test]
    public function a_sku_that_was_typed_is_kept(): void
    {
        $product = Product::create([
            'name' => 'P13',
            'sku' => 'SUPPLIER-OWN-CODE',
            'supplier_id' => $this->supplier->id,
            'price_list_section_id' => $this->section->id,
        ]);

        $this->assertSame('SUPPLIER-OWN-CODE', $product->sku);
    }

    #[Test]
    public function two_products_with_the_same_name_do_not_collide(): void
    {
        $first = $this->product('P13');
        $second = $this->product('P13');

        $this->assertSame('CRYS-P13', $first->sku);
        $this->assertSame('CRYS-P13-1', $second->sku);
    }

    #[Test]
    public function sizes_start_unpriced_and_are_priced_later(): void
    {
        $p13 = $this->product('P13');

        $size = ProductSize::create(['product_id' => $p13->id, 'label' => '10mm']);

        $this->assertNull($size->cost_price);
        $this->assertFalse($size->isPriced());
        $this->assertSame(0, ProductSize::priced()->count());

        $size->update(['cost_price' => 0.45]);

        $this->assertTrue($size->fresh()->isPriced());
        $this->assertSame(1, ProductSize::priced()->count());
    }

    #[Test]
    public function a_products_sizes_are_its_own(): void
    {
        $p13 = $this->product('P13');
        $p21 = $this->product('P21');

        ProductSize::create(['product_id' => $p13->id, 'label' => '10mm']);
        ProductSize::create(['product_id' => $p13->id, 'label' => '20mm']);
        ProductSize::create(['product_id' => $p21->id, 'label' => '150cm wide']);

        $this->assertSame(2, $p13->sizes()->count());
        $this->assertSame(['150cm wide'], $p21->sizes()->pluck('label')->all());
    }

    #[Test]
    public function sizes_go_when_the_product_is_really_gone(): void
    {
        $p13 = $this->product('P13');
        ProductSize::create(['product_id' => $p13->id, 'label' => '10mm']);

        $p13->forceDelete();

        $this->assertSame(0, ProductSize::count());
    }
}
