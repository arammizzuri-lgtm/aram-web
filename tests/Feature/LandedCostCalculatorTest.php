<?php

namespace Tests\Feature;

use App\Enums\AllocationBasis;
use App\Models\Currency;
use App\Models\LandedCostLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shipment;
use App\Models\ShipmentCost;
use App\Models\ShipmentCostType;
use App\Models\ShipmentItem;
use App\Models\Warehouse;
use App\Services\Costing\LandedCostCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * The worked example from docs/04-LANDED-COST.md §4, asserted to the cent.
 *
 * A 40HQ container carrying crystal chandeliers, sofa sets and fabric rolls:
 * $18,300 of goods, $8,148.57 of shipment costs. If this test ever fails, the
 * business is being told the wrong thing about its margins.
 */
class LandedCostCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private Shipment $shipment;

    /** @var array<string, ShipmentItem> */
    private array $items = [];

    protected function setUp(): void
    {
        parent::setUp();

        Currency::create(['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'decimal_places' => 2, 'is_base' => true]);

        $this->seedCostTypes();
        $this->buildContainer();
    }

    private function seedCostTypes(): void
    {
        $types = [
            ['code' => 'sea_freight', 'name' => 'Sea freight', 'basis' => 'volume', 'pass' => 1],
            ['code' => 'insurance', 'name' => 'Insurance', 'basis' => 'value', 'pass' => 1],
            ['code' => 'customs_duty', 'name' => 'Customs duty', 'basis' => 'per_line_hs', 'pass' => 2],
            ['code' => 'clearance_agent', 'name' => 'Clearance agent', 'basis' => 'value', 'pass' => 3],
            ['code' => 'bank_charges', 'name' => 'Bank charges', 'basis' => 'value', 'pass' => 3],
            ['code' => 'port_charges', 'name' => 'Port charges', 'basis' => 'volume', 'pass' => 4],
            ['code' => 'inland_transport', 'name' => 'Inland transport', 'basis' => 'volume', 'pass' => 4],
        ];

        foreach ($types as $type) {
            ShipmentCostType::create([
                'name' => $type['name'],
                'code' => $type['code'],
                'default_allocation_basis' => $type['basis'],
                'is_customs_duty' => $type['code'] === 'customs_duty',
                'calculation_pass' => $type['pass'],
            ]);
        }
    }

    private function buildContainer(): void
    {
        $category = ProductCategory::create(['name' => 'Imports', 'slug' => 'imports']);
        $warehouse = Warehouse::create(['code' => 'MAIN', 'name' => 'Main Warehouse']);

        $this->shipment = Shipment::create([
            'warehouse_id' => $warehouse->id,
            'shipping_method' => 'sea_fcl',
            'container_number' => 'TCLU8877661',
            'container_type' => '40hq',
            'status' => 'in_transit',
        ]);

        // qty, unit cost, kg/unit, CBM/unit, duty %
        $specs = [
            'crystal' => ['Crystal Chandelier A-330', 'CRY-0042', 100, '85.00', '12', '0.08', 15],
            'sofa' => ['Sofa Set Milano', 'FUR-0117', 20, '220.00', '45', '1.60', 20],
            'fabric' => ['Fabric Roll Jacquard', 'FAB-0233', 300, '18.00', '8', '0.06', 10],
        ];

        foreach ($specs as $key => [$name, $sku, $qty, $cost, $kg, $cbm, $duty]) {
            $product = Product::create([
                'sku' => $sku,
                'name' => $name,
                'product_category_id' => $category->id,
                'weight_kg' => $kg,
                'volume_cbm' => $cbm,
                'duty_rate' => $duty,
                'cost_price' => $cost,
                'selling_price' => 0,
            ]);

            $this->items[$key] = ShipmentItem::create([
                'shipment_id' => $this->shipment->id,
                'product_id' => $product->id,
                'quantity' => $qty,
                'unit_cost' => $cost,
                'currency' => 'USD',
                'exchange_rate' => 1,
                'unit_weight_kg' => $kg,
                'unit_volume_cbm' => $cbm,
                'duty_rate' => $duty,
            ]);
        }

        $this->shipment->refresh()->refreshTotals();
    }

    private function addCost(string $typeCode, string $amount, ?string $basis = null, bool $estimated = false): ShipmentCost
    {
        $type = ShipmentCostType::where('code', $typeCode)->firstOrFail();

        return ShipmentCost::create([
            'shipment_id' => $this->shipment->id,
            'shipment_cost_type_id' => $type->id,
            'amount' => $amount,
            'currency' => 'USD',
            'exchange_rate' => 1,
            'allocation_basis' => $basis ?? $type->default_allocation_basis->value,
            'is_estimated' => $estimated,
        ]);
    }

    private function addAllCosts(): void
    {
        $this->addCost('sea_freight', '3200.00');
        $this->addCost('insurance', '183.00');
        $this->addCost('customs_duty', '0');
        $this->addCost('clearance_agent', '450.00');
        $this->addCost('bank_charges', '95.00');
        $this->addCost('port_charges', '380.00');
        $this->addCost('inland_transport', '600.00');
    }

    private function calculate(bool $final = false)
    {
        return app(LandedCostCalculator::class)->calculate($this->shipment->fresh(), $final);
    }

    private function line(string $key): LandedCostLine
    {
        return LandedCostLine::where('shipment_item_id', $this->items[$key]->id)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    // ------------------------------------------------------------------ tests

    #[Test]
    public function the_container_totals_match_the_specification(): void
    {
        $shipment = $this->shipment->fresh();

        $this->assertSame('18300.0000', $shipment->total_goods_base);
        $this->assertSame('4500.0000', $shipment->total_weight_kg);
        $this->assertSame('58.000000', $shipment->total_volume_cbm);
    }

    /** §4.3 — sea freight by CBM, including the residual on the largest line. */
    #[Test]
    public function sea_freight_is_split_by_volume(): void
    {
        $this->addCost('sea_freight', '3200.00');
        $this->calculate();

        $this->assertSame('441.3793', $this->line('crystal')->allocated_costs_base);
        $this->assertSame('1765.5173', $this->line('sofa')->allocated_costs_base);
        $this->assertSame('993.1034', $this->line('fabric')->allocated_costs_base);
    }

    /**
     * The sofas are 24% of the value but 55% of the container. Allocating freight
     * by value instead would charge them $769 rather than $1,766 — the error that
     * makes bulky imports look profitable when they are not.
     */
    #[Test]
    public function volume_and_value_allocation_differ_sharply_for_bulky_goods(): void
    {
        $this->addCost('sea_freight', '3200.00', basis: AllocationBasis::Value->value);
        $this->calculate();

        // 3200 × 4400 / 18300 — less than half what the volume basis charges.
        $this->assertSame('769.3989', $this->line('sofa')->allocated_costs_base);
    }

    /** §4.4 — duty is per line on CIF, not a lump spread across the container. */
    #[Test]
    public function customs_duty_is_charged_per_hs_code_on_the_cif_value(): void
    {
        $this->addCost('sea_freight', '3200.00');
        $this->addCost('insurance', '183.00');
        $this->addCost('customs_duty', '0');

        $run = $this->calculate();

        $this->assertSame('9026.3793', $this->line('crystal')->cif_value_base);
        $this->assertSame('6209.5173', $this->line('sofa')->cif_value_base);
        $this->assertSame('6447.1034', $this->line('fabric')->cif_value_base);

        $duty = ShipmentCost::whereHas('type', fn ($q) => $q->where('code', 'customs_duty'))->firstOrFail();
        $this->assertSame('3240.5707', $duty->fresh()->base_amount);

        // Only freight, insurance and duty are on this shipment: 3200 + 183 + 3240.5707.
        $this->assertSame('6623.5707', $run->fresh()->total_costs_base);
    }

    /** §4.6 — the landed unit costs the whole business runs on. */
    #[Test]
    public function it_reproduces_the_worked_example_landed_unit_costs(): void
    {
        $this->addAllCosts();
        $run = $this->calculate();

        $this->assertSame('107.6865', $this->line('crystal')->landed_unit_cost);
        $this->assertSame('406.1574', $this->line('sofa')->landed_unit_cost);
        $this->assertSame('25.1892', $this->line('fabric')->landed_unit_cost);

        $this->assertSame('10768.6507', $this->line('crystal')->total_landed_base);
        $this->assertSame('8123.1487', $this->line('sofa')->total_landed_base);
        $this->assertSame('7556.7713', $this->line('fabric')->total_landed_base);

        $this->assertSame('8148.5707', $run->fresh()->total_costs_base);
    }

    #[Test]
    public function every_cost_reconciles_exactly_to_its_allocations(): void
    {
        $this->addAllCosts();
        $run = $this->calculate();

        foreach ($run->shipment->costs as $cost) {
            $allocated = (float) $cost->fresh()->base_amount;
            $sum = round((float) $cost->allocations()->sum('amount_base'), 4);

            $this->assertEqualsWithDelta(
                $allocated, $sum, 0.0001,
                "cost {$cost->id} must allocate in full — a rounding gap here is money vanishing"
            );
        }

        $lineTotal = round((float) $run->lines()->sum('total_landed_base'), 4);
        $this->assertEqualsWithDelta(26448.5707, $lineTotal, 0.0001);
    }

    #[Test]
    public function the_cost_uplift_over_goods_value_is_reported_per_line(): void
    {
        $this->addAllCosts();
        $this->calculate();

        $this->assertSame('26.69', $this->line('crystal')->cost_uplift_percent);
        $this->assertSame('84.62', $this->line('sofa')->cost_uplift_percent);
        $this->assertSame('39.94', $this->line('fabric')->cost_uplift_percent);
    }

    // ---------------------------------------------------------- run lifecycle

    #[Test]
    public function each_calculation_creates_a_new_version_and_supersedes_the_last(): void
    {
        $this->addAllCosts();

        $first = $this->calculate();
        $first->update(['status' => 'applied']);

        $second = $this->calculate();

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame('superseded', $first->fresh()->status, 'history is kept, not overwritten');
    }

    #[Test]
    public function a_shipment_with_estimated_costs_cannot_be_finalised(): void
    {
        $this->addAllCosts();
        $this->addCost('clearance_agent', '450.00', estimated: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('still has estimated costs');

        $this->calculate(final: true);
    }

    #[Test]
    public function a_shipment_with_no_items_cannot_be_costed(): void
    {
        $empty = Shipment::create([
            'warehouse_id' => Warehouse::first()->id,
            'status' => 'planning',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('has no items');

        app(LandedCostCalculator::class)->calculate($empty);
    }

    #[Test]
    public function the_run_snapshots_the_bases_it_used(): void
    {
        $this->addAllCosts();
        $run = $this->calculate();

        $bases = collect($run->basis_snapshot)->pluck('basis', 'type');

        $this->assertSame('volume', $bases['sea_freight']);
        $this->assertSame('value', $bases['insurance']);
        $this->assertSame('per_line_hs', $bases['customs_duty']);
    }

    #[Test]
    public function allocations_are_stored_per_cost_so_the_breakdown_can_be_shown(): void
    {
        $this->addAllCosts();
        $this->calculate();

        $breakdown = $this->line('sofa')->load('allocations.shipmentCost.type')->unitCostBreakdown();

        $this->assertEqualsWithDelta(88.2759, $breakdown['Sea freight'], 0.0001);
        $this->assertEqualsWithDelta(62.0952, $breakdown['Customs duty'], 0.0001);
        $this->assertEqualsWithDelta(2.2000, $breakdown['Insurance'], 0.0001);
    }
}
