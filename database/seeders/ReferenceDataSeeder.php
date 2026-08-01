<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use App\Models\PriceTier;
use App\Models\ShipmentCostType;
use App\Models\Unit;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

/**
 * Reference data the system needs to function, independent of any demo records.
 *
 * The shipment cost types carry the allocation defaults that make landed cost
 * correct out of the box — freight by volume, insurance by value, duty per HS
 * code — so the first container costed is right without anyone tuning it.
 */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'PCS', 'name' => 'Piece', 'symbol' => 'pcs', 'precision' => 0],
            ['code' => 'CTN', 'name' => 'Carton', 'symbol' => 'ctn', 'precision' => 0],
            ['code' => 'SET', 'name' => 'Set', 'symbol' => 'set', 'precision' => 0],
            ['code' => 'ROLL', 'name' => 'Roll', 'symbol' => 'roll', 'precision' => 0],
            ['code' => 'M2', 'name' => 'Square metre', 'symbol' => 'm²', 'precision' => 2],
            ['code' => 'M', 'name' => 'Metre', 'symbol' => 'm', 'precision' => 2],
            ['code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg', 'precision' => 3],
        ];

        foreach ($units as $unit) {
            Unit::updateOrCreate(['code' => $unit['code']], $unit);
        }

        Warehouse::updateOrCreate(
            ['code' => 'MAIN'],
            ['name' => 'Main Warehouse', 'type' => 'main', 'is_default' => true, 'is_active' => true],
        );

        $tiers = [
            ['code' => 'WHOLESALE', 'name' => 'Wholesale', 'default_discount_percent' => 0, 'is_default' => true, 'sort_order' => 1],
            ['code' => 'VIP', 'name' => 'VIP / Volume', 'default_discount_percent' => 8, 'is_default' => false, 'sort_order' => 2],
            ['code' => 'RETAIL', 'name' => 'Retail', 'default_discount_percent' => -15, 'is_default' => false, 'sort_order' => 3],
        ];

        foreach ($tiers as $tier) {
            PriceTier::updateOrCreate(['code' => $tier['code']], $tier);
        }

        /*
         * pass 1 = forms the CIF value duty is charged on
         * pass 2 = the duty itself
         * pass 3 = value-based fees
         * pass 4 = post-arrival charges
         */
        $costTypes = [
            ['code' => 'sea_freight', 'name' => 'Sea freight', 'basis' => 'volume', 'pass' => 1, 'duty' => false],
            ['code' => 'air_freight', 'name' => 'Air freight', 'basis' => 'weight', 'pass' => 1, 'duty' => false],
            ['code' => 'insurance', 'name' => 'Insurance', 'basis' => 'value', 'pass' => 1, 'duty' => false],
            ['code' => 'customs_duty', 'name' => 'Customs duty', 'basis' => 'per_line_hs', 'pass' => 2, 'duty' => true],
            ['code' => 'clearance_agent', 'name' => 'Clearance agent', 'basis' => 'value', 'pass' => 3, 'duty' => false],
            ['code' => 'bank_charges', 'name' => 'Bank charges', 'basis' => 'value', 'pass' => 3, 'duty' => false],
            ['code' => 'inspection', 'name' => 'Inspection', 'basis' => 'manual', 'pass' => 3, 'duty' => false],
            ['code' => 'port_charges', 'name' => 'Port charges', 'basis' => 'volume', 'pass' => 4, 'duty' => false],
            ['code' => 'inland_transport', 'name' => 'Inland transport', 'basis' => 'volume', 'pass' => 4, 'duty' => false],
            ['code' => 'demurrage', 'name' => 'Demurrage', 'basis' => 'volume', 'pass' => 4, 'duty' => false],
            ['code' => 'other', 'name' => 'Other charges', 'basis' => 'value', 'pass' => 4, 'duty' => false],
        ];

        foreach ($costTypes as $index => $type) {
            ShipmentCostType::updateOrCreate(['code' => $type['code']], [
                'name' => $type['name'],
                'default_allocation_basis' => $type['basis'],
                'calculation_pass' => $type['pass'],
                'is_customs_duty' => $type['duty'],
                'affects_landed_cost' => true,
                'sort_order' => $index,
            ]);
        }

        // Categories flagged shipment-allocatable are the ones that may be pushed
        // into a container's landed cost; the rest stay as general overhead.
        $expenseCategories = [
            ['code' => 'CARGO', 'name' => 'Cargo & Freight', 'type' => 'logistics', 'allocatable' => true],
            ['code' => 'CUSTOMS', 'name' => 'Customs & Duty', 'type' => 'logistics', 'allocatable' => true],
            ['code' => 'CLEARANCE', 'name' => 'Clearance & Port', 'type' => 'logistics', 'allocatable' => true],
            ['code' => 'TRANSPORT', 'name' => 'Transportation', 'type' => 'logistics', 'allocatable' => true],
            ['code' => 'WAREHOUSE', 'name' => 'Warehouse', 'type' => 'operating', 'allocatable' => false],
            ['code' => 'FUEL', 'name' => 'Fuel', 'type' => 'operating', 'allocatable' => false],
            ['code' => 'SALARIES', 'name' => 'Employee Expenses', 'type' => 'operating', 'allocatable' => false],
            ['code' => 'OFFICE', 'name' => 'Office Expenses', 'type' => 'operating', 'allocatable' => false],
            ['code' => 'MARKETING', 'name' => 'Marketing', 'type' => 'operating', 'allocatable' => false],
            ['code' => 'BANK', 'name' => 'Bank Charges', 'type' => 'financial', 'allocatable' => true],
            ['code' => 'OTHER', 'name' => 'Other', 'type' => 'operating', 'allocatable' => false],
        ];

        foreach ($expenseCategories as $index => $category) {
            ExpenseCategory::updateOrCreate(['code' => $category['code']], [
                'name' => $category['name'],
                'type' => $category['type'],
                'is_shipment_allocatable' => $category['allocatable'],
                'sort_order' => $index,
            ]);
        }
    }
}
