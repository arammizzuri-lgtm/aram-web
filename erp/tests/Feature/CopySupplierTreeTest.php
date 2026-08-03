<?php

namespace Tests\Feature;

use App\Actions\Catalog\CopySupplierTree;
use App\Models\PriceListSection;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Starting a second supplier without retyping the first.
 *
 * Each supplier keeps their own tree, so onboarding another crystal supplier
 * would otherwise begin with retyping Crystal, Flat Crystal and every shelf
 * under them. Two suppliers of the same goods stock the same shapes — what
 * differs is what they charge, and that is the one thing that must not come
 * across.
 */
class CopySupplierTreeTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $a;

    private Supplier $b;

    private PriceListSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Supplier::create(['code' => 'SUP-A', 'name' => 'Supplier A']);
        $this->b = Supplier::create(['code' => 'SUP-B', 'name' => 'Supplier B']);
        $this->section = PriceListSection::create(['code' => 'crystals', 'name' => 'Crystals']);

        $crystal = $this->make('Crystal', null, $this->a);
        $flat = $this->make('Flat Crystal', $crystal, $this->a);
        $p13 = $this->make('P13', $flat, $this->a);

        ProductSize::create(['product_id' => $p13->id, 'label' => '10mm', 'cost_price' => 0.45]);
        ProductSize::create(['product_id' => $p13->id, 'label' => '20mm', 'cost_price' => 0.90]);
    }

    private function make(string $name, ?Product $parent, Supplier $supplier): Product
    {
        return Product::create([
            'name' => $name,
            'parent_id' => $parent?->id,
            'supplier_id' => $supplier->id,
            'price_list_section_id' => $this->section->id,
        ]);
    }

    private function copy(bool $withSizes = true): int
    {
        return app(CopySupplierTree::class)
            ->copy($this->a->id, $this->b->id, $this->section->id, $withSizes);
    }

    #[Test]
    public function the_whole_shape_comes_across(): void
    {
        $this->assertSame(3, $this->copy());

        $p13 = Product::where('supplier_id', $this->b->id)->where('name', 'P13')->firstOrFail();

        $this->assertSame('Crystal › Flat Crystal › P13', $p13->pathLabel());
    }

    #[Test]
    public function the_prices_do_not(): void
    {
        $this->copy();

        $p13 = Product::where('supplier_id', $this->b->id)->where('name', 'P13')->firstOrFail();

        $this->assertSame(['10mm', '20mm'], $p13->sizes->pluck('label')->all());

        // Supplier B has quoted nothing yet. A filled-in price list here would
        // be Supplier A's numbers wearing Supplier B's name.
        $this->assertTrue($p13->sizes->every(fn (ProductSize $s) => $s->cost_price === null));
    }

    #[Test]
    public function the_original_is_left_exactly_as_it_was(): void
    {
        $this->copy();

        $original = Product::where('supplier_id', $this->a->id)->where('name', 'P13')->firstOrFail();

        $this->assertSame('0.4500', (string) $original->sizes->firstWhere('label', '10mm')->cost_price);
        $this->assertSame(3, Product::where('supplier_id', $this->a->id)->count());
    }

    #[Test]
    public function the_sizes_can_be_left_behind_too(): void
    {
        $this->copy(withSizes: false);

        $p13 = Product::where('supplier_id', $this->b->id)->where('name', 'P13')->firstOrFail();

        $this->assertCount(0, $p13->sizes);
    }

    #[Test]
    public function copying_a_supplier_onto_itself_does_nothing(): void
    {
        $copied = app(CopySupplierTree::class)
            ->copy($this->a->id, $this->a->id, $this->section->id);

        $this->assertSame(0, $copied);
        $this->assertSame(3, Product::where('supplier_id', $this->a->id)->count());
    }

    #[Test]
    public function copying_from_an_empty_supplier_creates_nothing(): void
    {
        $empty = Supplier::create(['code' => 'SUP-C', 'name' => 'Supplier C']);

        $copied = app(CopySupplierTree::class)
            ->copy($empty->id, $this->b->id, $this->section->id);

        $this->assertSame(0, $copied);
        $this->assertSame(0, Product::where('supplier_id', $this->b->id)->count());
    }

    #[Test]
    public function each_copy_gets_its_own_sku(): void
    {
        $this->copy();

        $skus = Product::pluck('sku');

        $this->assertSame($skus->count(), $skus->unique()->count());
    }
}
