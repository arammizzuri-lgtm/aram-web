<?php

namespace Tests\Feature;

use App\Filament\Resources\Deals\Pages\CreateDeal;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Models\CatalogueItem;
use App\Models\CatalogueItemSellPrice;
use App\Models\CrystalPrice;
use App\Models\CrystalProduct;
use App\Models\CrystalSellPrice;
use App\Models\CrystalSize;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductSellPrice;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Unit;
use App\Models\User;
use App\Services\Deals\CatalogueLookup;
use Database\Seeders\CrystalCatalogueSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * A deal line fetched from the price lists rather than typed from memory.
 *
 * The deal screen could not reach the catalogue at all: every line was typed by
 * hand and "From price list" was a pricing method that changed no number on the
 * screen. So the cost, the supplier, the Chinese name and the battery flag —
 * all of it already recorded — had to be looked up elsewhere and copied, which
 * is the exact re-entry the design exists to abolish.
 */
class DealCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private Customer $wholesaleCustomer;

    private Customer $regularCustomer;

    private Product $lamp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            CrystalCatalogueSeeder::class,
        ]);

        $owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $owner->assignRole('owner');
        $this->actingAs($owner);

        $this->supplier = Supplier::where('code', 'SUP-A')->firstOrFail();

        $wholesale = CustomerType::where('code', 'WHOLESALE')->firstOrFail();
        $regular = CustomerType::where('code', 'REGULAR')->firstOrFail();

        $this->wholesaleCustomer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading', 'default_currency' => 'IQD',
            'customer_type_id' => $wholesale->id, 'is_active' => true,
        ]);

        $this->regularCustomer = Customer::create([
            'code' => 'C-002', 'name' => 'Hemin Stores', 'default_currency' => 'IQD',
            'customer_type_id' => $regular->id, 'is_active' => true,
        ]);

        /*
         * A product priced on both sides: what the supplier charges you, and
         * what you charge each kind of customer. This is the shape the whole
         * redesign asked for and nothing was reading.
         */
        $this->lamp = Product::create([
            'sku' => 'LAMP-01',
            'name' => 'LED table lamp',
            'name_ku' => 'لامپی مێز',
            'name_zh' => '台灯',
            'contains_battery' => true,
            'is_active' => true,
            // Both required by the product screen, which one of these tests saves.
            'product_category_id' => ProductCategory::create([
                'name' => 'Lighting', 'slug' => 'lighting', 'is_active' => true,
            ])->id,
            'unit_id' => Unit::query()->value('id'),
        ]);

        SupplierProduct::create([
            'supplier_id' => $this->supplier->id,
            'product_id' => $this->lamp->id,
            'supplier_sku' => 'YW-8842',
            'currency' => 'CNY',
            'unit_price' => 36,
            'is_preferred' => true,
        ]);

        ProductSellPrice::create([
            'product_id' => $this->lamp->id,
            'customer_type_id' => $wholesale->id,
            'price' => 9, 'currency' => 'USD', 'min_quantity' => 1,
        ]);

        // The same product, cheaper by the hundred.
        ProductSellPrice::create([
            'product_id' => $this->lamp->id,
            'customer_type_id' => $wholesale->id,
            'price' => 8, 'currency' => 'USD', 'min_quantity' => 100,
        ]);

        ProductSellPrice::create([
            'product_id' => $this->lamp->id,
            'customer_type_id' => $regular->id,
            'price' => 12, 'currency' => 'USD', 'min_quantity' => 1,
        ]);
    }

    private function lookup(): CatalogueLookup
    {
        return app(CatalogueLookup::class);
    }

    private function customerTypeId(Customer $customer): ?int
    {
        return $customer->customer_type_id;
    }

    // ------------------------------------------------------------- searching

    #[Test]
    public function one_box_searches_every_price_list(): void
    {
        $products = $this->lookup()->search('lamp');
        $items = $this->lookup()->search('jacquard');

        $this->assertNotEmpty($products, 'a product should be findable');
        $this->assertStringStartsWith('product:', array_key_first($products));

        $this->assertNotEmpty($items, 'a catalogue item should be findable');
        $this->assertStringStartsWith('item:', array_key_first($items));
    }

    /**
     * A priced colour and size, as the matrix screen would leave it.
     *
     * The catalogue seeder loads the colour chart and the size pool but no
     * prices — those are typed on the matrix — so a test about picking one has
     * to price it first.
     */
    private function pricedCrystal(float $price = 0.42): CrystalPrice
    {
        $crystal = CrystalProduct::where('supplier_id', $this->supplier->id)->firstOrFail();
        $size = CrystalSize::ordered()->firstOrFail();

        return CrystalPrice::create([
            'supplier_id' => $this->supplier->id,
            'crystal_product_id' => $crystal->id,
            'crystal_size_id' => $size->id,
            'price' => $price,
            'currency' => 'CNY',
        ]);
    }

    /** A colour alone cannot be a line — 10mm and 20mm are different goods. */
    #[Test]
    public function crystals_are_offered_as_priced_colour_and_size_pairs(): void
    {
        $crystal = $this->pricedCrystal()->crystalProduct;

        $results = $this->lookup()->search($crystal->crystal_code);

        $this->assertNotEmpty($results);

        foreach (array_keys($results) as $key) {
            $this->assertMatchesRegularExpression('/^crystal:\d+:\d+$/', $key);
        }
    }

    /** Two characters is the floor: one would return the whole catalogue. */
    #[Test]
    public function a_search_of_nothing_much_returns_nothing(): void
    {
        $this->assertSame([], $this->lookup()->search(null));
        $this->assertSame([], $this->lookup()->search('a'));
    }

    // ------------------------------------------------------------- resolving

    #[Test]
    public function picking_a_product_fills_both_sides_of_the_line(): void
    {
        $found = $this->lookup()->resolve(
            "product:{$this->lamp->id}",
            quantity: 1,
            customerTypeId: $this->customerTypeId($this->wholesaleCustomer),
        );

        $this->assertSame('LED table lamp', $found['description']);
        $this->assertSame('台灯', $found['description_zh']);

        // Cost, from the preferred supplier's own price list.
        $this->assertSame(36.0, $found['unit_cost']);
        $this->assertSame('CNY', $found['cost_currency']);
        $this->assertSame($this->supplier->id, $found['supplier_id']);

        // Sell, from the customer type's price list.
        $this->assertSame(9.0, $found['list_price']);
        $this->assertSame('USD', $found['list_price_currency']);

        $this->assertTrue($found['contains_battery']);
        $this->assertSame($this->lamp->id, $found['product_id']);
    }

    /** Your customer does not care where you sourced it — the type sets it. */
    #[Test]
    public function the_selling_price_follows_the_customer_type(): void
    {
        $key = "product:{$this->lamp->id}";

        $this->assertSame(
            9.0,
            $this->lookup()->resolve($key, 1, $this->customerTypeId($this->wholesaleCustomer))['list_price'],
        );

        $this->assertSame(
            12.0,
            $this->lookup()->resolve($key, 1, $this->customerTypeId($this->regularCustomer))['list_price'],
        );
    }

    #[Test]
    public function quantity_breaks_apply_to_the_selling_price(): void
    {
        $key = "product:{$this->lamp->id}";
        $type = $this->customerTypeId($this->wholesaleCustomer);

        $this->assertSame(9.0, $this->lookup()->resolve($key, 99, $type)['list_price']);
        $this->assertSame(8.0, $this->lookup()->resolve($key, 100, $type)['list_price']);
        $this->assertSame(8.0, $this->lookup()->resolve($key, 500, $type)['list_price']);
    }

    #[Test]
    public function a_catalogue_item_brings_its_supplier_and_its_break_price(): void
    {
        $item = CatalogueItem::where('code', 'TX-JAC-280')->firstOrFail();

        CatalogueItemSellPrice::create([
            'catalogue_item_id' => $item->id,
            'customer_type_id' => $this->customerTypeId($this->wholesaleCustomer),
            'price' => 11, 'currency' => 'USD',
        ]);

        $found = $this->lookup()->resolve(
            "item:{$item->id}",
            quantity: 500,
            customerTypeId: $this->customerTypeId($this->wholesaleCustomer),
        );

        $this->assertStringContainsString('TX-JAC-280', $found['description']);
        $this->assertSame($item->supplier_id, $found['supplier_id']);

        // The 500 break, not the single-unit price.
        $this->assertSame(6.2, $found['unit_cost']);
        $this->assertSame(11.0, $found['list_price']);
        $this->assertSame($item->id, $found['catalogue_item_id']);
    }

    #[Test]
    public function a_crystal_pick_carries_the_colour_and_the_size(): void
    {
        $price = $this->pricedCrystal()->load(['crystalProduct', 'size']);

        CrystalSellPrice::create([
            'crystal_product_id' => $price->crystal_product_id,
            'crystal_size_id' => $price->crystal_size_id,
            'customer_type_id' => $this->customerTypeId($this->wholesaleCustomer),
            'price' => 3.5, 'currency' => 'USD',
        ]);

        $found = $this->lookup()->resolve(
            "crystal:{$price->crystal_product_id}:{$price->crystal_size_id}",
            quantity: 500,
            customerTypeId: $this->customerTypeId($this->wholesaleCustomer),
        );

        $this->assertStringContainsString($price->crystalProduct->crystal_code, $found['description']);
        $this->assertStringContainsString($price->size->label, $found['description']);

        $this->assertSame((float) $price->price, $found['unit_cost']);
        $this->assertSame(3.5, $found['list_price']);
        $this->assertSame($price->crystal_product_id, $found['crystal_product_id']);
        $this->assertSame($price->crystal_size_id, $found['crystal_size_id']);
    }

    /**
     * The standard price is the fallback the product screen says it is.
     *
     * Without it, "From price list" would work only for products already
     * priced for every customer type — which is none of them on the day the
     * system starts being used.
     */
    #[Test]
    public function the_standard_selling_price_applies_when_the_type_has_no_price_of_its_own(): void
    {
        $plain = Product::create([
            'sku' => 'PLAIN-01',
            'name' => 'Plain thing',
            'selling_price' => 14,
            'selling_price_currency' => 'USD',
            'is_active' => true,
        ]);

        $found = $this->lookup()->resolve(
            "product:{$plain->id}",
            1,
            $this->customerTypeId($this->wholesaleCustomer),
        );

        $this->assertSame(14.0, $found['list_price']);
        $this->assertSame('USD', $found['list_price_currency']);
    }

    /** A type price beats the standard one — that is the point of having it. */
    #[Test]
    public function a_customer_type_price_wins_over_the_standard_one(): void
    {
        $this->lamp->update(['selling_price' => 99]);

        $found = $this->lookup()->resolve(
            "product:{$this->lamp->id}",
            1,
            $this->customerTypeId($this->wholesaleCustomer),
        );

        $this->assertSame(9.0, $found['list_price']);
    }

    /** The section said "set below" and there was nothing below to set. */
    #[Test]
    public function customer_type_prices_can_be_entered_on_the_product_screen(): void
    {
        $regularType = $this->customerTypeId($this->regularCustomer);

        Livewire::test(EditProduct::class, ['record' => $this->lamp->getRouteKey()])
            ->fillForm([
                'sellPrices' => [
                    ['customer_type_id' => $regularType, 'price' => 17, 'currency' => 'USD', 'min_quantity' => 1],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(
            17.0,
            (float) $this->lamp->fresh()->sellPriceFor($regularType, 1)->price,
        );
    }

    /** An unpriced item leaves the cost box alone rather than zeroing it. */
    #[Test]
    public function an_item_nobody_has_priced_yet_reports_no_price_rather_than_zero(): void
    {
        $bare = Product::create(['sku' => 'BARE-01', 'name' => 'Unpriced thing', 'is_active' => true]);

        $found = $this->lookup()->resolve("product:{$bare->id}", 1, null);

        $this->assertNull($found['unit_cost']);
        $this->assertNull($found['list_price']);
    }

    #[Test]
    public function a_key_for_nothing_resolves_to_nothing(): void
    {
        $this->assertNull($this->lookup()->resolve(null));
        $this->assertNull($this->lookup()->resolve(''));
        $this->assertNull($this->lookup()->resolve('nonsense'));
        $this->assertNull($this->lookup()->resolve('product:999999'));
    }

    // ------------------------------------------------------------ the screen

    #[Test]
    public function picking_on_the_deal_screen_fills_the_line_and_saves_what_it_points_at(): void
    {
        $page = Livewire::test(CreateDeal::class)
            ->fillForm([
                'customer_id' => $this->wholesaleCustomer->id,
                'deal_date' => today(),
                'sell_currency' => 'USD',
                'rmb_usd_rate' => 7.2,
                'lines' => [
                    [
                        'description' => '',
                        'quantity' => 200,
                        'unit' => 'pcs',
                        'unit_cost' => 0,
                        'cost_currency' => 'CNY',
                        'pricing_method' => 'markup',
                        'markup_percent' => 25,
                        'unit_price' => 0,
                    ],
                ],
            ]);

        $key = array_key_first($page->get('data.lines'));

        $page->set("data.lines.{$key}.catalogue_key", "product:{$this->lamp->id}");

        // Everything the list already knew, without a second screen.
        $this->assertSame('LED table lamp', $page->get("data.lines.{$key}.description"));
        $this->assertSame('台灯', $page->get("data.lines.{$key}.description_zh"));
        $this->assertSame(36.0, (float) $page->get("data.lines.{$key}.unit_cost"));
        $this->assertSame('CNY', $page->get("data.lines.{$key}.cost_currency"));
        $this->assertSame($this->supplier->id, (int) $page->get("data.lines.{$key}.supplier_id"));
        $this->assertTrue((bool) $page->get("data.lines.{$key}.contains_battery"));

        // ¥36 ÷ 7.2 = $5, plus 25% = $6.25.
        $this->assertSame(6.25, (float) $page->get("data.lines.{$key}.unit_price"));

        $page->call('create')->assertHasNoFormErrors();

        $line = DealLine::firstOrFail();

        // What it points at is kept, which is what makes the freight split and
        // the per-product reports possible at all.
        $this->assertSame($this->lamp->id, $line->product_id);
        $this->assertSame('台灯', $line->description_zh);
        $this->assertTrue($line->contains_battery);
    }

    #[Test]
    public function pricing_from_the_list_takes_the_customers_own_price(): void
    {
        $page = Livewire::test(CreateDeal::class)
            ->fillForm([
                'customer_id' => $this->wholesaleCustomer->id,
                'deal_date' => today(),
                'sell_currency' => 'IQD',
                'iqd_usd_rate' => 1470,
                'rmb_usd_rate' => 7.2,
                'lines' => [
                    [
                        'description' => '',
                        'quantity' => 200,
                        'unit' => 'pcs',
                        'unit_cost' => 0,
                        'cost_currency' => 'CNY',
                        'pricing_method' => 'markup',
                        'markup_percent' => 25,
                        'unit_price' => 0,
                    ],
                ],
            ]);

        $key = array_key_first($page->get('data.lines'));

        $page->set("data.lines.{$key}.catalogue_key", "product:{$this->lamp->id}");
        $page->set("data.lines.{$key}.pricing_method", 'list');

        /*
         * 200 pieces reaches the hundred break at $8, and the customer is billed
         * in dinars: $8 x 1,470 = 11,760 IQD. Before this, choosing "From price
         * list" changed nothing whatever.
         */
        $this->assertSame(11760.0, (float) $page->get("data.lines.{$key}.unit_price"));
    }

    /**
     * The owner can settle a price in the currency it was settled in.
     *
     * A customer negotiates in dinars, and the price box for dinars had been
     * made read-only for anyone who can see cost — so the one figure the
     * customer actually agreed to was the one figure that could not be typed.
     */
    #[Test]
    public function a_typed_price_can_be_given_in_the_customers_own_currency(): void
    {
        $page = Livewire::test(CreateDeal::class)
            ->fillForm([
                'customer_id' => $this->wholesaleCustomer->id,
                'deal_date' => today(),
                'sell_currency' => 'IQD',
                'iqd_usd_rate' => 1470,
                'rmb_usd_rate' => 7.2,
                'lines' => [
                    [
                        'description' => 'Hand-agreed item',
                        'quantity' => 10,
                        'unit' => 'pcs',
                        'unit_cost' => 36,
                        'cost_currency' => 'CNY',
                        'pricing_method' => 'manual',
                        'unit_price' => 0,
                    ],
                ],
            ]);

        $key = array_key_first($page->get('data.lines'));

        $page->set("data.lines.{$key}.unit_price", 30000);

        // 30,000 IQD = $20.408..., said back in the yuan the goods were bought
        // in: x 7.2 = ¥146.94.
        $this->assertEqualsWithDelta(
            146.94,
            (float) $page->get("data.lines.{$key}.sell_each_in_cost_currency"),
            0.01,
        );

        $page->call('create')->assertHasNoFormErrors();

        $this->assertSame('30000.0000', DealLine::firstOrFail()->unit_price);
    }

    /** Clearing the search makes it a one-off again, without deleting anything. */
    #[Test]
    public function clearing_the_pick_unlinks_the_line_but_keeps_what_was_typed(): void
    {
        $page = Livewire::test(CreateDeal::class)
            ->fillForm([
                'customer_id' => $this->wholesaleCustomer->id,
                'deal_date' => today(),
                'sell_currency' => 'USD',
                'rmb_usd_rate' => 7.2,
                'lines' => [[
                    'description' => '', 'quantity' => 5, 'unit' => 'pcs',
                    'unit_cost' => 0, 'cost_currency' => 'CNY',
                    'pricing_method' => 'markup', 'markup_percent' => 25, 'unit_price' => 0,
                ]],
            ]);

        $key = array_key_first($page->get('data.lines'));

        $page->set("data.lines.{$key}.catalogue_key", "product:{$this->lamp->id}");
        $page->set("data.lines.{$key}.catalogue_key", null);

        $this->assertNull($page->get("data.lines.{$key}.product_id"));
        $this->assertSame('LED table lamp', $page->get("data.lines.{$key}.description"));
        $this->assertSame(36.0, (float) $page->get("data.lines.{$key}.unit_cost"));
    }

    /** Reopening a deal shows what each line was picked from. */
    #[Test]
    public function an_existing_line_remembers_which_list_it_came_from(): void
    {
        $deal = Deal::create([
            'number' => 'D-2026-0009',
            'customer_id' => $this->wholesaleCustomer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'product_id' => $this->lamp->id,
            'description' => 'LED table lamp',
            'quantity' => 5,
            'unit_cost' => 36,
            'cost_currency' => 'CNY',
            'unit_price' => 6.25,
        ]);

        $page = Livewire::test(EditDeal::class, ['record' => $deal->getRouteKey()]);

        $key = array_key_first($page->get('data.lines'));

        // Reopened, the search box says what the line came from rather than
        // sitting empty beside a description that plainly came from somewhere.
        $this->assertSame(
            "product:{$this->lamp->id}",
            $page->get("data.lines.{$key}.catalogue_key"),
        );

        $this->assertStringContainsString('LED table lamp', $this->lookup()->label("product:{$this->lamp->id}"));
    }
}
