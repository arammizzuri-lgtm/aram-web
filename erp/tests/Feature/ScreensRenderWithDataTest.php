<?php

namespace Tests\Feature;

use App\Filament\Resources\Consignments\Pages\ManageConsignments;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use App\Filament\Resources\Deals\Pages\EditDeal;
use App\Filament\Resources\Deals\Pages\ListDeals;
use App\Filament\Resources\Deals\RelationManagers\ConsignmentsRelationManager;
use App\Filament\Resources\ExchangeRates\Pages\ManageExchangeRates;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Branch;
use App\Models\CollectionPoint;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\CustomerType;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every screen, with more than one thing on it.
 *
 * A screen that forgot to eager-load a relationship renders perfectly until the
 * day it has data. That is how the consignments screen reached the live site
 * and became a 500 the moment there was a second deal to put in its picker:
 * the suite mounted the page, the browser smoke test loaded it, and both did so
 * against an empty database.
 *
 * `phpunit.xml` runs with APP_ENV=testing and AppServiceProvider turns on
 * `preventLazyLoading` outside production, so a relationship the query did not
 * load throws here instead of costing a quiet extra query per row.
 *
 * Two conditions have to hold before that guard can catch anything, and neither
 * is obvious:
 *
 * **Rows, because columns are only evaluated when a row renders.** Hence
 * `assertCanSeeTableRecords()` — which asserts the row's HTML — rather than
 * `assertOk()`, which proves only that the page mounted.
 *
 * **More than one row, because Laravel arms the guard only on models that
 * arrived in company:**
 *
 *     // Eloquent\Builder::hydrate()
 *     if (count($items) > 1) {
 *         $model->preventsLazyLoading = Model::preventsLazyLoading();
 *     }
 *
 * One lazy load is not an N+1, so a single-row query leaves it unarmed. Every
 * fixture here makes a second record whose only job is to put the first one in
 * a crowd; written with one apiece, these tests pass against the very code they
 * exist to catch.
 *
 * Note what this can and cannot find. Filament works out the eager loads for a
 * `relation.attribute` **column** by itself
 * (`Columns\Concerns\InteractsWithTableQuery::applyEagerLoading()`), so those
 * are safe without anyone doing anything. What it cannot see into is a
 * **closure** — an option label, a description, a computed state — and that is
 * where every eager load in this codebase is written by hand and where the next
 * omission will be.
 */
class ScreensRenderWithDataTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);
    }

    // ------------------------------------------------------------- fixtures

    private function customer(string $code = 'C-001'): Customer
    {
        return Customer::create([
            'code' => $code,
            'name' => "Trader {$code}",
            'default_currency' => 'IQD',
            'is_active' => true,
            // The type is what the customers table shows in its own column.
            'customer_type_id' => CustomerType::query()->value('id'),
        ]);
    }

    private function supplier(): Supplier
    {
        return Supplier::firstOrCreate(
            ['code' => 'SUP-A'],
            ['name' => 'Yiwu', 'default_currency' => 'CNY', 'is_active' => true],
        );
    }

    private function deal(string $number = 'D-2026-0001', ?Customer $customer = null): Deal
    {
        $customer ??= $this->customer($number);

        $deal = Deal::create([
            'number' => $number,
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplier()->id,
            'description' => 'Crystal P07',
            'quantity' => 10,
            'unit_cost' => 12.5,
            'cost_currency' => 'CNY',
            'unit_price' => 28000,
        ]);

        return $deal->fresh();
    }

    private function consignment(string $tracking = '16940'): Consignment
    {
        return Consignment::create([
            'tracking_number' => $tracking,
            'mode' => 'sea',
            'collection_point_id' => CollectionPoint::firstOrCreate(
                ['name' => 'Guangzhou collection point'],
                ['city' => 'Guangzhou', 'is_active' => true],
            )->id,
            'boxes' => 1,
            'gross_weight_kg' => 18.5,
            'cbm' => 0.11,
            'status' => 'awaiting',
        ]);
    }

    private function product(string $sku, ?int $categoryId = null): Product
    {
        return Product::create([
            'sku' => $sku,
            'name' => "Product {$sku}",
            'selling_price' => 14,
            'is_active' => true,
            'product_category_id' => $categoryId ?? ProductCategory::create([
                'name' => "Category {$sku}", 'slug' => strtolower($sku), 'is_active' => true,
            ])->id,
            'unit_id' => Unit::query()->value('id'),
            'default_supplier_id' => $this->supplier()->id,
        ]);
    }

    // -------------------------------------------------------------- screens

    /**
     * The one that broke.
     *
     * The consignment form's deal picker preloads every deal and builds each
     * option label from `$record->customer?->name`, which the relationship
     * query never loaded. It needs a deal to exist before there is an option to
     * label at all — which is why an empty database sailed straight past it.
     */
    /**
     * The one that broke.
     *
     * The consignment form's deal picker preloads every deal and builds each
     * option label from `$record->customer?->name`, which the relationship
     * query never loaded. Two deals, so the preload returns a crowd and the
     * guard is armed — with one deal on the system this screen works, which is
     * exactly why it was fine until it was not.
     */
    #[Test]
    public function the_consignment_form_can_label_its_deals(): void
    {
        $consignment = $this->consignment();
        $deal = $this->deal('D-2026-0001');
        $this->deal('D-2026-0002');

        $consignment->deals()->attach($deal->id);

        Livewire::test(ManageConsignments::class)
            ->callAction(TestAction::make('edit')->table($consignment))
            ->assertHasNoFormErrors();
    }

    #[Test]
    public function the_consignments_list_shows_whose_goods_they_are(): void
    {
        $first = $this->consignment('16940');
        $second = $this->consignment('16941');

        $first->deals()->attach($this->deal('D-2026-0001')->id);
        $second->deals()->attach($this->deal('D-2026-0002')->id);

        Livewire::test(ManageConsignments::class)
            ->assertCanSeeTableRecords([$first, $second]);
    }

    #[Test]
    public function the_deals_list_shows_its_deals(): void
    {
        $first = $this->deal('D-2026-0001');
        $second = $this->deal('D-2026-0002');

        Livewire::test(ListDeals::class)->assertCanSeeTableRecords([$first, $second]);
    }

    /** The shipping panel on the deal, which only renders once one is attached. */
    #[Test]
    public function the_shipping_panel_on_a_deal_shows_its_tracking_numbers(): void
    {
        $deal = $this->deal();

        // A deal can arrive under several tracking numbers, which is both the
        // ordinary case and the one that arms the guard.
        $first = $this->consignment('16940');
        $second = $this->consignment('16941');

        $deal->consignments()->attach([$first->id, $second->id]);

        Livewire::test(ConsignmentsRelationManager::class, [
            'ownerRecord' => $deal->fresh(),
            'pageClass' => EditDeal::class,
        ])->assertCanSeeTableRecords([$first, $second]);
    }

    #[Test]
    public function the_products_list_shows_a_products_category_and_supplier(): void
    {
        $first = $this->product('LAMP-01');
        $second = $this->product('LAMP-02');

        Livewire::test(ListProducts::class)->assertCanSeeTableRecords([$first, $second]);
    }

    #[Test]
    public function the_customers_list_shows_a_customers_type(): void
    {
        $first = $this->customer('C-001');
        $second = $this->customer('C-002');

        Livewire::test(ManageCustomers::class)->assertCanSeeTableRecords([$first, $second]);
    }

    #[Test]
    public function the_exchange_rates_list_shows_who_added_one(): void
    {
        $rates = collect(['CNY', 'IQD'])->map(fn (string $from) => ExchangeRate::create([
            'from_currency' => $from,
            'to_currency' => 'USD',
            'rate' => 7.2,
            'effective_date' => today(),
            'source' => 'manual',
            'created_by' => $this->owner->id,
        ]));

        Livewire::test(ManageExchangeRates::class)->assertCanSeeTableRecords($rates->all());
    }

    #[Test]
    public function the_users_list_shows_roles_and_branches(): void
    {
        $branch = Branch::create(['code' => 'HQ', 'name' => 'Erbil', 'is_active' => true]);

        $this->owner->update(['branch_id' => $branch->id]);

        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true, 'branch_id' => $branch->id,
        ]);
        $assistant->assignRole('assistant');

        Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$this->owner, $assistant]);
    }
}
