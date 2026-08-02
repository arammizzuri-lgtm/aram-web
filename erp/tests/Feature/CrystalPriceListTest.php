<?php

namespace Tests\Feature;

use App\Filament\Pages\CrystalPriceList;
use App\Models\CrystalPrice;
use App\Models\CrystalPriceHistory;
use App\Models\CrystalProduct;
use App\Models\CrystalSize;
use App\Models\PriceListSection;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\CrystalCatalogueSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CrystalPriceListTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplierA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            CrystalCatalogueSeeder::class,
        ]);

        $user = User::create([
            'name' => 'Owner',
            'email' => 'owner@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->assignRole('owner');
        $this->actingAs($user);

        $this->supplierA = Supplier::where('code', 'SUP-A')->firstOrFail();
    }

    #[Test]
    public function the_four_sections_exist_as_data_not_code(): void
    {
        $this->assertSame(
            ['crystals', 'textile', 'packaging', 'furniture'],
            PriceListSection::ordered()->pluck('code')->all(),
        );

        // All four are now built — Crystals on its matrix screen, the rest on
        // the shared catalogue screen. Anything added later starts unimplemented.
        foreach (['crystals', 'textile', 'packaging', 'furniture'] as $code) {
            $this->assertTrue(PriceListSection::where('code', $code)->first()->isImplemented(), $code);
        }
    }

    #[Test]
    public function the_whole_colour_chart_is_loaded(): void
    {
        $this->assertSame(90, CrystalProduct::forSupplier($this->supplierA->id)->count());

        foreach (['plain', 'ab', 'special'] as $finish) {
            $this->assertSame(30, CrystalProduct::where('finish', $finish)->count());
        }

        $this->assertDatabaseHas('crystal_products', ['crystal_code' => 'P01', 'crystal_name' => 'Crystal']);
        $this->assertDatabaseHas('crystal_products', ['crystal_code' => 'P02', 'crystal_name' => 'Jet Black']);
        $this->assertDatabaseHas('crystal_products', ['crystal_code' => 'P03', 'crystal_name' => 'Black Diamond']);
        $this->assertDatabaseHas('crystal_products', ['crystal_code' => 'P112', 'crystal_name' => 'Glow Hyacinth AB']);
    }

    #[Test]
    public function the_sizes_are_stored_rather_than_hardcoded(): void
    {
        $this->assertSame(
            ['10', '12', '16', '20', '30', '40', '50'],
            CrystalSize::ordered()->get()->map(fn ($s) => rtrim(rtrim($s->size_mm, '0'), '.'))->all(),
        );

        // A supplier with an extra size adds a row, not a migration.
        CrystalSize::create(['size_mm' => 60, 'display_order' => 7]);

        $this->assertSame(8, CrystalSize::active()->count());
    }

    #[Test]
    public function a_code_is_only_unique_within_its_supplier(): void
    {
        $supplierB = Supplier::create(['code' => 'SUP-B', 'name' => 'Supplier B', 'default_currency' => 'CNY']);

        // Supplier B may use P01 for something entirely different.
        $bs = CrystalProduct::create([
            'supplier_id' => $supplierB->id,
            'crystal_code' => 'P01',
            'crystal_name' => 'Clear Stone',
        ]);

        $this->assertSame(2, CrystalProduct::where('crystal_code', 'P01')->count());
        $this->assertNotSame(
            $bs->crystal_name,
            CrystalProduct::forSupplier($this->supplierA->id)->where('crystal_code', 'P01')->value('crystal_name'),
        );
    }

    /** The point of the whole structure: a price means nothing without its supplier. */
    #[Test]
    public function the_same_colour_and_size_carries_a_different_price_per_supplier(): void
    {
        $supplierB = Supplier::create(['code' => 'SUP-B', 'name' => 'Supplier B', 'default_currency' => 'CNY']);
        $size = CrystalSize::where('size_mm', 10)->firstOrFail();

        $a = CrystalProduct::forSupplier($this->supplierA->id)->where('crystal_code', 'P01')->firstOrFail();
        $b = CrystalProduct::create([
            'supplier_id' => $supplierB->id, 'crystal_code' => 'P01', 'crystal_name' => 'Crystal',
        ]);

        CrystalPrice::create([
            'crystal_product_id' => $a->id, 'crystal_size_id' => $size->id, 'price' => 120, 'currency' => 'CNY',
        ]);
        CrystalPrice::create([
            'crystal_product_id' => $b->id, 'crystal_size_id' => $size->id, 'price' => 105, 'currency' => 'CNY',
        ]);

        $this->assertSame('120.0000', CrystalPrice::forSupplier($this->supplierA->id)->value('price'));
        $this->assertSame('105.0000', CrystalPrice::forSupplier($supplierB->id)->value('price'));
    }

    #[Test]
    public function the_supplier_is_derived_from_the_product_if_omitted(): void
    {
        $crystal = CrystalProduct::forSupplier($this->supplierA->id)->first();
        $size = CrystalSize::first();

        $price = CrystalPrice::create([
            'crystal_product_id' => $crystal->id,
            'crystal_size_id' => $size->id,
            'price' => 99,
        ]);

        $this->assertSame($this->supplierA->id, $price->supplier_id);
    }

    #[Test]
    public function changing_a_price_records_history(): void
    {
        $crystal = CrystalProduct::forSupplier($this->supplierA->id)->first();
        $size = CrystalSize::first();

        $price = CrystalPrice::create([
            'crystal_product_id' => $crystal->id, 'crystal_size_id' => $size->id,
            'price' => 120, 'currency' => 'CNY',
        ]);

        $this->assertTrue($price->updatePrice(145));
        $this->assertFalse($price->updatePrice(145), 'an unchanged price writes no history');

        $entry = CrystalPriceHistory::where('crystal_price_id', $price->id)->firstOrFail();

        $this->assertSame('145.0000', $entry->price);
        $this->assertSame('120.0000', $entry->previous_price);
        $this->assertSame('20.83', $entry->change_percent);
    }

    // ------------------------------------------------------------------ page

    #[Test]
    public function the_price_grid_loads_the_selected_suppliers_catalogue(): void
    {
        Livewire::test(CrystalPriceList::class)
            ->assertOk()
            ->assertSet('supplierId', $this->supplierA->id)
            ->assertSee('P01')
            ->assertSee('Jet Black')
            ->assertSee('Glow Hyacinth AB');
    }

    #[Test]
    public function the_grid_can_be_filtered_by_code_name_and_finish(): void
    {
        Livewire::test(CrystalPriceList::class)
            ->set('search', 'Siam')
            ->assertSee('Lt. Siam')
            ->assertDontSee('Jet Black')
            ->set('search', '')
            ->set('finish', 'ab')
            ->assertSee('Crystal AB')
            ->assertDontSee('Jet Hematite');
    }

    #[Test]
    public function entering_prices_in_the_grid_saves_them(): void
    {
        $crystal = CrystalProduct::forSupplier($this->supplierA->id)->where('crystal_code', 'P01')->firstOrFail();
        $sizes = CrystalSize::ordered()->take(3)->get();

        Livewire::test(CrystalPriceList::class)
            ->set("prices.{$crystal->id}-{$sizes[0]->id}", '120')
            ->set("prices.{$crystal->id}-{$sizes[1]->id}", '145')
            ->set("prices.{$crystal->id}-{$sizes[2]->id}", '180')
            ->call('savePrices')
            ->assertOk();

        $this->assertSame(3, CrystalPrice::where('crystal_product_id', $crystal->id)->count());
        $this->assertSame('CNY', CrystalPrice::where('crystal_product_id', $crystal->id)->value('currency'));
        $this->assertSame('145.0000', CrystalPrice::where('crystal_size_id', $sizes[1]->id)->value('price'));
    }

    /** An empty cell means "not offered in that size", not "free". */
    #[Test]
    public function clearing_a_cell_removes_the_price_rather_than_zeroing_it(): void
    {
        $crystal = CrystalProduct::forSupplier($this->supplierA->id)->first();
        $size = CrystalSize::first();

        CrystalPrice::create([
            'crystal_product_id' => $crystal->id, 'crystal_size_id' => $size->id,
            'price' => 120, 'currency' => 'CNY',
        ]);

        Livewire::test(CrystalPriceList::class)
            ->set("prices.{$crystal->id}-{$size->id}", '')
            ->call('savePrices');

        $this->assertSame(0, CrystalPrice::where('crystal_product_id', $crystal->id)->count());
    }

    /**
     * All seven sizes stay on screen whatever has been priced.
     *
     * Showing only priced sizes made the grid impossible to finish — the columns
     * still to be filled in were exactly the ones being hidden.
     */
    #[Test]
    public function every_size_stays_a_column_even_before_it_is_priced(): void
    {
        $crystal = CrystalProduct::forSupplier($this->supplierA->id)->first();
        $sizes = CrystalSize::ordered()->take(2)->get();

        foreach ($sizes as $size) {
            CrystalPrice::create([
                'crystal_product_id' => $crystal->id, 'crystal_size_id' => $size->id,
                'price' => 100, 'currency' => 'CNY',
            ]);
        }

        $page = Livewire::test(CrystalPriceList::class)->instance();

        $this->assertSame(7, $page->sizes()->count(), 'all seven sizes must remain enterable');
        $this->assertSame(2, $page->quotedSizeCount(), 'quoted count is a statistic, not a filter');
    }

    // --------------------------------------------------------------- ordering

    /** Alphabetically P100 sorts between P10 and P11; naturally it comes last. */
    #[Test]
    public function codes_sort_naturally_rather_than_alphabetically(): void
    {
        $codes = Livewire::test(CrystalPriceList::class)
            ->set('sort', 'code')
            ->instance()
            ->crystals()
            ->pluck('crystal_code');

        $this->assertSame('P01', $codes->first());
        $this->assertSame('P112', $codes->last());
        $this->assertLessThan(
            $codes->search('P100'),
            $codes->search('P96'),
            'P96 must precede P100'
        );
    }

    #[Test]
    public function the_catalogue_order_matches_the_printed_chart_by_default(): void
    {
        $codes = Livewire::test(CrystalPriceList::class)->instance()->crystals()->pluck('crystal_code');

        // The chart runs P01, P02, P03 down its first column.
        $this->assertSame(['P01', 'P02', 'P03'], $codes->take(3)->all());
        // …and its second column opens with the AB colours.
        $this->assertSame('P56', $codes[30]);
    }

    #[Test]
    public function sorting_can_be_saved_as_the_new_catalogue_order(): void
    {
        Livewire::test(CrystalPriceList::class)
            ->set('sort', 'code')
            ->call('applySortPermanently')
            ->assertSet('sort', 'catalogue');

        $stored = CrystalProduct::forSupplier($this->supplierA->id)
            ->orderBy('display_order')
            ->pluck('crystal_code');

        $this->assertSame('P01', $stored->first());
        $this->assertSame('P112', $stored->last());
    }

    #[Test]
    public function saving_the_order_is_a_no_op_while_in_catalogue_order(): void
    {
        $before = CrystalProduct::forSupplier($this->supplierA->id)
            ->orderBy('display_order')->pluck('crystal_code');

        Livewire::test(CrystalPriceList::class)->call('applySortPermanently');

        $after = CrystalProduct::forSupplier($this->supplierA->id)
            ->orderBy('display_order')->pluck('crystal_code');

        $this->assertSame($before->all(), $after->all());
    }

    #[Test]
    public function colours_can_be_ordered_by_name_and_by_finish(): void
    {
        $byName = Livewire::test(CrystalPriceList::class)
            ->set('sort', 'name')->instance()->crystals()->pluck('crystal_name');

        $this->assertSame('Air Rose', $byName->first());

        $byFinish = Livewire::test(CrystalPriceList::class)
            ->set('sort', 'finish')->instance()->crystals();

        $this->assertSame('plain', $byFinish->first()->finish);
        $this->assertSame('special', $byFinish->last()->finish);
    }

    #[Test]
    public function a_catalogue_entry_can_be_promoted_to_a_stocked_product(): void
    {
        $crystal = CrystalProduct::forSupplier($this->supplierA->id)->first();

        $this->assertFalse($crystal->isStocked());

        $product = Product::create([
            'sku' => 'CRY-P01-10', 'name' => 'Crystal P01 10mm', 'cost_price' => 12, 'selling_price' => 20,
        ]);

        $crystal->update(['product_id' => $product->id]);

        $this->assertTrue($crystal->fresh()->isStocked());
    }
}
