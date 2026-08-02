<?php

namespace Tests\Feature;

use App\Filament\Pages\CataloguePriceList;
use App\Models\CatalogueItem;
use App\Models\CatalogueItemPrice;
use App\Models\PriceListSection;
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

class CataloguePriceListTest extends TestCase
{
    use RefreshDatabase;

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
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $user->assignRole('owner');
        $this->actingAs($user);
    }

    /** Each section declares its own fields, so one screen serves all three. */
    #[Test]
    public function every_section_carries_its_own_field_structure(): void
    {
        $expected = [
            'textile' => ['composition', 'width_cm', 'gsm', 'colour', 'finish'],
            'packaging' => ['material', 'length_cm', 'width_cm', 'height_cm', 'printing'],
            'furniture' => ['material', 'finish', 'dimensions', 'cbm', 'assembly'],
        ];

        foreach ($expected as $code => $keys) {
            $section = PriceListSection::where('code', $code)->firstOrFail();

            $this->assertSame($keys, array_column($section->attributes(), 'key'), "{$code} fields");
            $this->assertTrue($section->isImplemented(), "{$code} must be reachable");
        }
    }

    #[Test]
    public function each_section_states_the_unit_its_prices_are_quoted_in(): void
    {
        $this->assertSame('per metre', PriceListSection::where('code', 'textile')->value('price_unit'));
        $this->assertSame('per unit', PriceListSection::where('code', 'packaging')->value('price_unit'));
        $this->assertSame('per piece', PriceListSection::where('code', 'furniture')->value('price_unit'));
    }

    #[Test]
    public function all_three_sections_are_seeded_with_items_and_prices(): void
    {
        foreach (['textile', 'packaging', 'furniture'] as $code) {
            $section = PriceListSection::where('code', $code)->firstOrFail();

            $this->assertGreaterThan(0, CatalogueItem::forSection($section->id)->count(), $code);
        }

        $this->assertSame(12, CatalogueItem::count());
    }

    /** The largest applicable break wins — 6,000 metres gets the 3,000 rate. */
    #[Test]
    public function quantity_breaks_resolve_to_the_largest_applicable_price(): void
    {
        $item = CatalogueItem::where('code', 'TX-JAC-280')->with('prices')->firstOrFail();

        $this->assertSame('6.8000', $item->priceFor(1)->price);
        $this->assertSame('6.8000', $item->priceFor(499)->price);
        $this->assertSame('6.2000', $item->priceFor(500)->price);
        $this->assertSame('6.2000', $item->priceFor(2999)->price);
        $this->assertSame('5.6000', $item->priceFor(6000)->price);
    }

    /**
     * The column name collides with Eloquent's internal raw-attribute array,
     * so reading it inside the model has to go through getAttribute().
     */
    #[Test]
    public function section_attributes_read_back_correctly(): void
    {
        $fabric = CatalogueItem::where('code', 'TX-JAC-280')->firstOrFail();

        $this->assertSame('70% Polyester / 30% Cotton', $fabric->attribute('composition'));
        $this->assertSame('280', $fabric->attribute('width_cm'));
        $this->assertNull($fabric->attribute('nonexistent'));
        $this->assertArrayHasKey('gsm', $fabric->sectionAttributes());
    }

    #[Test]
    public function a_code_is_only_unique_within_a_supplier_and_section(): void
    {
        $section = PriceListSection::where('code', 'textile')->firstOrFail();
        $other = Supplier::create(['code' => 'SUP-X', 'name' => 'Other Mill', 'default_currency' => 'USD']);

        // The same code may mean something different from another supplier.
        CatalogueItem::create([
            'price_list_section_id' => $section->id,
            'supplier_id' => $other->id,
            'code' => 'TX-JAC-280',
            'name' => 'Their jacquard',
        ]);

        $this->assertSame(2, CatalogueItem::where('code', 'TX-JAC-280')->count());
    }

    #[Test]
    public function the_supplier_is_derived_from_the_item_if_omitted(): void
    {
        $item = CatalogueItem::where('code', 'FN-TAB-OAK')->firstOrFail();

        $price = CatalogueItemPrice::create([
            'catalogue_item_id' => $item->id,
            'min_quantity' => 999,
            'price' => 100,
        ]);

        $this->assertSame($item->supplier_id, $price->supplier_id);
    }

    // -------------------------------------------------------------------- page

    #[Test]
    public function the_page_renders_each_section_with_its_own_columns(): void
    {
        Livewire::test(CataloguePriceList::class)
            ->assertOk()
            ->assertSee('Composition')
            ->assertSee('Jacquard Upholstery 280cm')
            ->set('section', 'furniture')
            ->assertSee('Assembly')
            ->assertSee('Milano 3+2+1 Sofa Set')
            ->assertDontSee('Composition');
    }

    #[Test]
    public function crystals_is_excluded_because_it_has_its_own_matrix_screen(): void
    {
        $codes = Livewire::test(CataloguePriceList::class)->instance()->sections()->pluck('code');

        $this->assertNotContains('crystals', $codes->all());
        $this->assertSame(['textile', 'packaging', 'furniture'], $codes->all());
    }

    #[Test]
    public function a_price_can_be_entered_from_the_grid(): void
    {
        $item = CatalogueItem::where('code', 'PK-TAG-HAN')->firstOrFail();

        Livewire::test(CataloguePriceList::class)
            ->set('section', 'packaging')
            ->call('savePrice', $item->id, '50000', '0.045');

        $this->assertSame('0.0450', CatalogueItemPrice::query()
            ->where('catalogue_item_id', $item->id)
            ->where('min_quantity', 50000)
            ->value('price'));
    }

    /** An empty cell means "not quoted at that break", not "free". */
    #[Test]
    public function clearing_a_cell_removes_the_break_rather_than_zeroing_it(): void
    {
        $item = CatalogueItem::where('code', 'TX-VEL-150')->firstOrFail();
        $before = $item->prices()->count();

        Livewire::test(CataloguePriceList::class)
            ->set('section', 'textile')
            ->call('savePrice', $item->id, '3000', '');

        $this->assertSame($before - 1, $item->fresh()->prices()->count());
    }

    /**
     * Coverage answers "which lines can I not order?", not "which cells are blank".
     *
     * Two fabrics quoted at different tiers are both fully quoted even though
     * they fill half the union grid between them.
     */
    #[Test]
    public function coverage_counts_priced_lines_rather_than_filled_cells(): void
    {
        $page = Livewire::test(CataloguePriceList::class)->set('section', 'textile');

        // Every seeded fabric carries at least one price.
        $this->assertSame(100.0, (float) $page->instance()->coverage()['percent']);

        // Strip one line's prices entirely and it drops out of the count.
        CatalogueItem::where('code', 'TX-LAC-030')->firstOrFail()->prices()->delete();

        $coverage = Livewire::test(CataloguePriceList::class)
            ->set('section', 'textile')->instance()->coverage();

        $this->assertSame(3, $coverage['priced']);
        $this->assertSame(4, $coverage['total']);
        $this->assertSame(75.0, (float) $coverage['percent']);
    }

    /** Without this there is no cell to type a newly offered tier into. */
    #[Test]
    public function a_quantity_break_column_can_be_opened_for_a_tier_nobody_quotes(): void
    {
        $page = Livewire::test(CataloguePriceList::class)->set('section', 'textile');

        $this->assertNotContains(20000.0, $page->instance()->quantityBreaks());

        $page->set('newBreak', '20000')->call('addBreak');

        $this->assertContains(20000.0, $page->instance()->quantityBreaks());
        $this->assertSame('', $page->get('newBreak'));

        // Columns stay ascending so the grid reads left-to-right cheapest-last.
        $breaks = $page->instance()->quantityBreaks();
        $sorted = $breaks;
        sort($sorted);
        $this->assertSame($sorted, $breaks);
    }

    #[Test]
    public function a_break_that_already_exists_or_is_meaningless_is_ignored(): void
    {
        $page = Livewire::test(CataloguePriceList::class)->set('section', 'textile');
        $before = $page->instance()->quantityBreaks();

        foreach (['500', '1', '0', '-5'] as $rejected) {
            $page->set('newBreak', $rejected)->call('addBreak');
        }

        $this->assertSame($before, $page->instance()->quantityBreaks());
    }

    /** Break 1 is the base rate — it is always a column, even on an empty catalogue. */
    #[Test]
    public function the_base_rate_column_is_always_present(): void
    {
        $this->assertContains(1.0, Livewire::test(CataloguePriceList::class)
            ->set('section', 'packaging')->instance()->quantityBreaks());

        CatalogueItemPrice::query()->delete();

        $this->assertSame([1.0], Livewire::test(CataloguePriceList::class)
            ->set('section', 'packaging')->instance()->quantityBreaks());
    }

    /**
     * Searching must ignore case on every database this runs on.
     *
     * This assertion cannot fail on SQLite — its LIKE is case-insensitive for
     * ASCII whatever the query says. It is here for the PostgreSQL run: there,
     * a plain `where(..., 'like', ...)` is case-sensitive and typing "jacquard"
     * would return nothing. Run the suite against PostgreSQL before deploying
     * and this is the test that catches a regression to raw LIKE.
     */
    #[Test]
    public function searching_ignores_case_on_every_database(): void
    {
        foreach (['jacquard', 'JACQUARD', 'JaCqUaRd', 'tx-jac', 'TX-JAC'] as $term) {
            $this->assertSame(
                1,
                CatalogueItem::query()->search($term)->count(),
                "searching for {$term}",
            );
        }
    }

    #[Test]
    public function a_price_cannot_be_written_to_another_suppliers_item(): void
    {
        $furnitureItem = CatalogueItem::where('code', 'FN-TAB-OAK')->firstOrFail();

        // The page is on textile, whose selected supplier is a different one.
        Livewire::test(CataloguePriceList::class)
            ->set('section', 'textile')
            ->call('savePrice', $furnitureItem->id, '1', '999');

        $this->assertNull(CatalogueItemPrice::query()
            ->where('catalogue_item_id', $furnitureItem->id)
            ->where('price', 999)
            ->first());
    }
}
