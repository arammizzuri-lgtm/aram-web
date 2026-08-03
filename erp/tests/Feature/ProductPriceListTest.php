<?php

namespace Tests\Feature;

use App\Filament\Pages\ProductPriceList;
use App\Models\PriceListSection;
use App\Models\Product;
use App\Models\ProductSize;
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
 * Where prices are actually typed.
 *
 * Products arrive here unpriced from the Products screen. This is the one place
 * a cost is entered, and the distinction it has to keep is between a size
 * nobody has quoted yet and a size quoted at nothing.
 */
class ProductPriceListTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private PriceListSection $section;

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
        $this->section = PriceListSection::create([
            'code' => 'crystals', 'name' => 'Crystals', 'is_active' => true, 'sort_order' => 1,
        ]);

        $crystal = $this->make('Crystal');
        $flat = $this->make('Flat Crystal', $crystal);
        $this->p13 = $this->make('P13', $flat);

        ProductSize::create(['product_id' => $this->p13->id, 'label' => '10mm']);
        ProductSize::create(['product_id' => $this->p13->id, 'label' => '20mm']);
    }

    private function make(string $name, ?Product $parent = null): Product
    {
        return Product::create([
            'name' => $name,
            'parent_id' => $parent?->id,
            'supplier_id' => $this->supplier->id,
            'price_list_section_id' => $this->section->id,
        ]);
    }

    #[Test]
    public function the_tree_is_listed_parents_before_children(): void
    {
        $rows = Livewire::test(ProductPriceList::class)->instance()->rows();

        $this->assertSame(
            ['Crystal', 'Flat Crystal', 'P13'],
            $rows->map(fn (array $r) => $r['product']->name)->all()
        );

        $this->assertSame([0, 1, 2], $rows->map(fn (array $r) => $r['depth'])->all());
    }

    #[Test]
    public function typing_a_price_stores_it_against_the_size(): void
    {
        $sizes = $this->p13->sizes->keyBy('label');

        Livewire::test(ProductPriceList::class)
            ->set("prices.{$sizes['10mm']->id}", '0.45')
            ->call('savePrices');

        $stored = $sizes['10mm']->fresh();

        $this->assertSame('0.4500', (string) $stored->cost_price);
        $this->assertSame('CNY', $stored->currency);
        $this->assertSame(today()->toDateString(), $stored->effective_date->toDateString());

        // The size nobody typed into is still waiting, not zero.
        $this->assertNull($sizes['20mm']->fresh()->cost_price);
    }

    #[Test]
    public function clearing_a_price_returns_the_size_to_unquoted(): void
    {
        $size = $this->p13->sizes->first();
        $size->update(['cost_price' => 0.45, 'effective_date' => today()]);

        Livewire::test(ProductPriceList::class)
            ->set("prices.{$size->id}", '')
            ->call('savePrices');

        $this->assertNull($size->fresh()->cost_price);
    }

    #[Test]
    public function a_price_of_zero_is_kept_as_a_real_price(): void
    {
        $size = $this->p13->sizes->first();

        Livewire::test(ProductPriceList::class)
            ->set("prices.{$size->id}", '0')
            ->call('savePrices');

        // Free is a price a supplier can quote; unquoted is the absence of one.
        $this->assertSame('0.0000', (string) $size->fresh()->cost_price);
        $this->assertTrue($size->fresh()->isPriced());
    }

    #[Test]
    public function coverage_counts_what_is_still_unpriced(): void
    {
        $page = Livewire::test(ProductPriceList::class);

        $this->assertSame(['priced' => 0, 'total' => 2, 'percent' => 0.0], $page->instance()->coverage());

        $this->p13->sizes->first()->update(['cost_price' => 0.45]);

        $this->assertSame(
            ['priced' => 1, 'total' => 2, 'percent' => 50.0],
            Livewire::test(ProductPriceList::class)->instance()->coverage()
        );
    }

    #[Test]
    public function searching_shows_the_match_itself_not_the_shelves_above_it(): void
    {
        $rows = Livewire::test(ProductPriceList::class)
            ->set('search', 'P13')
            ->instance()
            ->rows();

        $this->assertSame(['P13'], $rows->map(fn (array $r) => $r['product']->name)->all());
    }

    #[Test]
    public function only_suppliers_with_something_in_this_list_are_offered(): void
    {
        Supplier::create(['code' => 'SUP-B', 'name' => 'Empty Supplier']);

        $suppliers = Livewire::test(ProductPriceList::class)->instance()->suppliers();

        $this->assertSame(['Supplier A'], $suppliers->values()->all());
    }

    #[Test]
    public function the_screen_renders_with_a_priced_tree_on_it(): void
    {
        $this->p13->sizes->first()->update(['cost_price' => 0.45]);

        Livewire::test(ProductPriceList::class)
            ->call('expandAll')
            ->assertOk()
            ->assertSee('Flat Crystal')
            ->assertSee('10mm');
    }

    #[Test]
    public function a_section_named_in_the_url_is_the_one_that_opens(): void
    {
        PriceListSection::create([
            'code' => 'textile', 'name' => 'Textile', 'is_active' => true, 'sort_order' => 2,
        ]);

        // The Price Lists module links to each section with ?section=<code>.
        // Crystals sorts first, so landing on textile proves the URL was read.
        $this->get('/erp/product-price-list?section=textile')->assertOk();

        $page = Livewire::withQueryParams(['section' => 'textile'])->test(ProductPriceList::class);

        $this->assertSame('textile', $page->instance()->section);
    }

    #[Test]
    public function a_section_that_does_not_exist_falls_back_to_the_first(): void
    {
        $page = Livewire::withQueryParams(['section' => 'nonsense'])->test(ProductPriceList::class);

        $this->assertSame('crystals', $page->instance()->section);
    }

    #[Test]
    public function staff_who_cannot_see_cost_cannot_open_the_screen(): void
    {
        $staff = User::create([
            'name' => 'Sales', 'email' => 'sales@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $staff->assignRole('assistant');
        $this->actingAs($staff);

        $this->assertFalse(ProductPriceList::canAccess());
    }
}
