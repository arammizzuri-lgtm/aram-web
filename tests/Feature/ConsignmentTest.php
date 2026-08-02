<?php

namespace Tests\Feature;

use App\Filament\Resources\Consignments\Pages\ManageConsignments;
use App\Models\CollectionPoint;
use App\Models\Consignment;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Shipping\ConsignmentWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tracking numbers, and dividing a shared freight bill honestly.
 *
 * The split is the part worth testing hard. When one bill covers two customers
 * and it is divided wrongly, both deals still look plausible — the error never
 * announces itself, it just quietly moves profit from one customer to another.
 */
class ConsignmentTest extends TestCase
{
    use RefreshDatabase;

    private ConsignmentWriter $writer;

    private Supplier $supplier;

    private Unit $unit;

    private ProductCategory $category;

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

        $this->supplier = Supplier::create(['code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY']);
        $this->unit = Unit::where('code', 'PCS')->firstOrFail();
        $this->category = ProductCategory::create(['name' => 'General', 'slug' => 'general']);

        $this->writer = app(ConsignmentWriter::class);
    }

    /**
     * A deal for one customer, carrying a product with a known weight and size.
     *
     * The two shapes matter: crystals are heavy and small, fabric is light and
     * bulky. That contrast is exactly what a value-based split gets wrong.
     */
    private function dealFor(string $name, float $weightKg, float $cbm, float $qty, float $price): Deal
    {
        $customer = Customer::create([
            'code' => 'C-'.substr(md5($name), 0, 4),
            'name' => $name,
            'default_currency' => 'IQD',
            'is_active' => true,
        ]);

        $product = Product::create([
            'sku' => 'SKU-'.substr(md5($name), 0, 6),
            'name' => $name.' goods',
            'product_category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'weight_kg' => $weightKg,
            'volume_cbm' => $cbm,
            'cost_price' => 10,
            'selling_price' => 20,
            'is_active' => true,
        ]);

        $deal = Deal::create([
            'number' => 'D-'.substr(md5($name), 0, 6),
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $this->supplier->id,
            'product_id' => $product->id,
            'description' => $product->name,
            'quantity' => $qty,
            'unit_cost' => 10,
            'cost_currency' => 'CNY',
            'unit_price' => $price,
        ]);

        return app(DealWriter::class)->sync($deal->fresh());
    }

    private function consignment(string $mode, float $freight, array $dealIds): Consignment
    {
        $consignment = Consignment::create([
            'tracking_number' => '16940',
            'mode' => $mode,
            'boxes' => 4,
            'gross_weight_kg' => 138.5,
            'cbm' => 0.62,
            'status' => 'in_transfer',
            'freight_amount' => $freight,
            'freight_currency' => 'USD',
        ]);

        $consignment->deals()->attach($dealIds);

        return $consignment->refresh();
    }

    // ------------------------------------------------------------- the basics

    #[Test]
    public function a_consignment_records_what_the_forwarder_reports(): void
    {
        $deal = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $consignment = $this->consignment('sea', 600, [$deal->id]);

        $this->assertSame('16940', $consignment->tracking_number);
        $this->assertSame(4, $consignment->boxes);
        $this->assertSame('138.500', $consignment->gross_weight_kg);
        $this->assertSame('0.6200', $consignment->cbm);
        $this->assertFalse($consignment->isAir());
    }

    /** Both directions happen, so the link runs both ways. */
    #[Test]
    public function a_deal_can_have_many_tracking_numbers_and_a_number_many_deals(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $sara = $this->dealFor('Sara', 0.5, 0.01, 100, 95000);

        $shared = $this->consignment('sea', 1400, [$ali->id, $sara->id]);

        $second = Consignment::create([
            'tracking_number' => '26805',
            'mode' => 'air_no_battery',
            'gross_weight_kg' => 18.5,
            'status' => 'awaiting',
        ]);
        $second->deals()->attach($ali->id);

        $this->assertSame(2, $shared->deals()->count());
        $this->assertSame(2, $ali->fresh()->consignments()->count());
        $this->assertTrue($shared->isShared());
        $this->assertFalse($second->isShared());
    }

    // ------------------------------------------------------------- the split

    /**
     * Sea is charged for space, so the suggestion divides by volume.
     *
     * Ali: 500 x 0.0002 = 0.1 cbm. Sara: 100 x 0.01 = 1.0 cbm.
     * So Sara takes 10/11 of the bill despite Ali's goods being worth more —
     * which is the whole point.
     */
    #[Test]
    public function a_sea_bill_is_suggested_by_volume(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $sara = $this->dealFor('Sara', 0.5, 0.01, 100, 95000);

        $consignment = $this->consignment('sea', 1100, [$ali->id, $sara->id]);

        $split = $this->writer->suggest($consignment);

        $this->assertSame('cbm', $consignment->splitBasis());
        $this->assertSame('100.0000', $split[$ali->id]->amount);
        $this->assertSame('1000.0000', $split[$sara->id]->amount);
    }

    /**
     * Air is charged for weight, so the same two deals divide differently.
     *
     * Ali: 500 x 0.2 = 100 kg. Sara: 100 x 0.5 = 50 kg. Now Ali carries two
     * thirds — the opposite of the sea answer, from identical goods.
     */
    #[Test]
    public function an_air_bill_is_suggested_by_weight(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $sara = $this->dealFor('Sara', 0.5, 0.01, 100, 95000);

        $consignment = $this->consignment('air_no_battery', 900, [$ali->id, $sara->id]);

        $split = $this->writer->suggest($consignment);

        $this->assertSame('weight', $consignment->splitBasis());
        $this->assertSame('600.0000', $split[$ali->id]->amount);
        $this->assertSame('300.0000', $split[$sara->id]->amount);
    }

    /** Nothing is lost to rounding — the shares sum to the bill exactly. */
    #[Test]
    public function the_shares_always_add_up_to_the_bill(): void
    {
        $a = $this->dealFor('A', 1, 0.003, 7, 1000);
        $b = $this->dealFor('B', 1, 0.003, 7, 1000);
        $c = $this->dealFor('C', 1, 0.003, 7, 1000);

        // 1,000 / 3 does not divide cleanly.
        $consignment = $this->consignment('sea', 1000, [$a->id, $b->id, $c->id]);

        $split = $this->writer->suggest($consignment);
        $total = array_sum(array_map(fn ($m) => (float) $m->amount, $split));

        $this->assertSame(1000.0, round($total, 4));
    }

    /** A share of nothing cannot be apportioned, so it divides evenly instead. */
    #[Test]
    public function deals_with_no_recorded_weight_split_evenly_rather_than_arbitrarily(): void
    {
        $customA = $this->dealWithCustomLineOnly('Custom A');
        $customB = $this->dealWithCustomLineOnly('Custom B');

        $consignment = $this->consignment('sea', 500, [$customA->id, $customB->id]);

        $split = $this->writer->suggest($consignment);

        $this->assertSame('250.0000', $split[$customA->id]->amount);
        $this->assertSame('250.0000', $split[$customB->id]->amount);
    }

    private function dealWithCustomLineOnly(string $name): Deal
    {
        $customer = Customer::create([
            'code' => 'C-'.substr(md5($name), 0, 4),
            'name' => $name, 'default_currency' => 'IQD', 'is_active' => true,
        ]);

        $deal = Deal::create([
            'number' => 'D-'.substr(md5($name), 0, 6),
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'iqd_usd_rate' => 1470,
        ]);

        // Typed straight onto the deal, so nothing knows its weight.
        DealLine::create([
            'deal_id' => $deal->id,
            'description' => 'One-off sample box',
            'quantity' => 1,
            'unit_cost' => 0,
            'unit_price' => 100000,
        ]);

        return $deal->fresh();
    }

    // ------------------------------------------------------------- applying

    #[Test]
    public function applying_a_split_records_it_against_each_deal_in_dollars(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $sara = $this->dealFor('Sara', 0.5, 0.01, 100, 95000);

        $consignment = $this->consignment('sea', 1100, [$ali->id, $sara->id]);

        $this->writer->applySplit($consignment, [$ali->id => 400, $sara->id => 700]);

        $pivot = $consignment->fresh()->deals()->get()->keyBy('id');

        $this->assertSame('400.0000', $pivot[$ali->id]->pivot->freight_share);
        $this->assertSame('400.0000', $pivot[$ali->id]->pivot->freight_share_base);
        $this->assertTrue((bool) $pivot[$ali->id]->pivot->share_is_manual);
    }

    /** Freight is part of what the deal cost, or the profit is a fiction. */
    #[Test]
    public function the_freight_share_lands_in_the_deals_cost(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $consignment = $this->consignment('sea', 600, [$ali->id]);

        $before = $ali->load('lines', 'purchases.costs', 'expenses', 'consignments')
            ->costBase()->toFloat();

        $this->writer->applyWholeBillToSoleDeal($consignment);

        $after = $ali->fresh()->load('lines', 'purchases.costs', 'expenses', 'consignments')
            ->costBase()->toFloat();

        $this->assertSame(600.0, round($after - $before, 2));
    }

    /** One customer, one bill — no split interface, no decision to make. */
    #[Test]
    public function a_sole_deal_takes_the_whole_bill_without_being_asked(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $consignment = $this->consignment('sea', 600, [$ali->id]);

        $this->writer->applyWholeBillToSoleDeal($consignment);

        $pivot = $consignment->fresh()->deals()->first()->pivot;

        $this->assertSame('600.0000', $pivot->freight_share);
        $this->assertFalse((bool) $pivot->share_is_manual, 'it was not a judgement');
    }

    // ------------------------------------------------------------- warnings

    /** Raised before booking, not after the forwarder rejects it. */
    #[Test]
    public function battery_goods_on_ordinary_air_freight_are_flagged(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $ali->lines()->first()->update(['contains_battery' => true]);

        $consignment = $this->consignment('air_no_battery', 900, [$ali->fresh()->id]);

        $warnings = $this->writer->warnings($consignment);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('battery goods', $warnings[0]);
        $this->assertStringContainsString('Air (with battery)', $warnings[0]);
    }

    #[Test]
    public function battery_goods_are_fine_by_sea_or_on_battery_capable_air(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $ali->lines()->first()->update(['contains_battery' => true]);

        foreach (['sea', 'air_battery'] as $mode) {
            Consignment::query()->delete();
            $consignment = $this->consignment($mode, 900, [$ali->fresh()->id]);

            $this->assertSame([], $this->writer->warnings($consignment), $mode);
        }
    }

    /** A split that does not add up is money nobody has accounted for. */
    #[Test]
    public function a_split_that_misses_part_of_the_bill_is_flagged(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $sara = $this->dealFor('Sara', 0.5, 0.01, 100, 95000);

        $consignment = $this->consignment('sea', 1100, [$ali->id, $sara->id]);

        $this->writer->applySplit($consignment, [$ali->id => 400, $sara->id => 500]);

        $warnings = $this->writer->warnings($consignment->fresh());

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('unaccounted for', $warnings[0]);
    }

    // ----------------------------------------------------------------- screen

    /**
     * The split interface only exists when there is something to divide.
     *
     * One customer, one bill, no decision — showing a "split" button there
     * would invite a choice that has only one possible answer.
     */
    #[Test]
    public function the_split_button_is_hidden_unless_the_consignment_is_shared(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $sole = $this->consignment('sea', 600, [$ali->id]);

        Livewire::test(ManageConsignments::class)
            ->assertTableActionHidden('split', $sole);

        $sara = $this->dealFor('Sara', 0.5, 0.01, 100, 95000);
        $sole->deals()->attach($sara->id);

        Livewire::test(ManageConsignments::class)
            ->assertTableActionVisible('split', $sole->fresh());
    }

    #[Test]
    public function the_split_screen_writes_the_shares_to_the_deals(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $sara = $this->dealFor('Sara', 0.5, 0.01, 100, 95000);
        $consignment = $this->consignment('sea', 1100, [$ali->id, $sara->id]);

        Livewire::test(ManageConsignments::class)
            ->callTableAction('split', $consignment, [
                'share_'.$ali->id => 100,
                'share_'.$sara->id => 1000,
            ])
            ->assertHasNoTableActionErrors();

        $shares = $consignment->fresh()->deals()->get()->keyBy('id');

        $this->assertSame('100.0000', $shares[$ali->id]->pivot->freight_share);
        $this->assertSame('1000.0000', $shares[$sara->id]->pivot->freight_share);
    }

    /** Freight is cost, so the assistant never gets to see or set it. */
    #[Test]
    public function the_assistant_cannot_reach_the_freight_split(): void
    {
        $ali = $this->dealFor('Ali', 0.2, 0.0002, 500, 28000);
        $sara = $this->dealFor('Sara', 0.5, 0.01, 100, 95000);
        $consignment = $this->consignment('sea', 1100, [$ali->id, $sara->id]);

        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        Livewire::test(ManageConsignments::class)
            ->assertTableActionHidden('split', $consignment);
    }

    // ---------------------------------------------------------------- points

    /** Addresses you hand to suppliers, not storage you control. */
    #[Test]
    public function collection_points_carry_the_address_a_supplier_can_actually_read(): void
    {
        $point = CollectionPoint::create([
            'name' => 'Guangzhou consolidation',
            'city' => 'Guangzhou',
            'address' => 'Baiyun District, Warehouse 12',
            'address_zh' => '广州市白云区12号仓库',
            'contact_name' => 'Mr Chen',
            'phone' => '+86 20 1234 5678',
        ]);

        $this->assertSame('广州市白云区12号仓库', $point->address_zh);
        $this->assertTrue($point->is_active);
    }
}
