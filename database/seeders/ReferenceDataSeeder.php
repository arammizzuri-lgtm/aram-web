<?php

namespace Database\Seeders;

use App\Models\CustomerType;
use App\Models\ExpenseCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Reference data the system needs before anyone can do anything, independent
 * of any demo records.
 *
 * Deliberately small. The shipment cost types that used to live here existed to
 * drive the landed-cost engine's allocation passes across a container of mixed
 * stock. There is no such container, so there is nothing to allocate and no
 * defaults to get right.
 *
 * Customer types and collection points are seeded as starting points, not as
 * fixed lists — both are yours to rename, add to, or delete.
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

        /*
         * Starting customer types.
         *
         * `default_markup_percent` is a fallback used when a product has no
         * explicit selling price for the type — it means a type is usable
         * immediately rather than only after every product has been priced.
         */
        $types = [
            ['code' => 'WHOLESALE', 'name' => 'Wholesale', 'default_markup_percent' => 15, 'is_default' => true, 'display_order' => 1],
            ['code' => 'REGULAR', 'name' => 'Regular', 'default_markup_percent' => 25, 'is_default' => false, 'display_order' => 2],
        ];

        foreach ($types as $type) {
            CustomerType::updateOrCreate(['code' => $type['code']], $type);
        }

        /*
         * Expense categories.
         *
         * The old `is_shipment_allocatable` flag is gone: an expense either
         * names the deal it was incurred for or it is general overhead. There
         * is no third state where it gets spread across a container's contents.
         */
        $expenseCategories = [
            ['code' => 'FREIGHT', 'name' => 'Freight & Shipping', 'type' => 'logistics'],
            ['code' => 'CUSTOMS', 'name' => 'Customs & Clearance', 'type' => 'logistics'],
            ['code' => 'TRANSPORT', 'name' => 'Local Transport & Delivery', 'type' => 'logistics'],
            ['code' => 'TRANSFER', 'name' => 'Money Transfer & Exchange', 'type' => 'financial'],
            ['code' => 'BANK', 'name' => 'Bank Charges', 'type' => 'financial'],
            ['code' => 'SALARIES', 'name' => 'Salaries', 'type' => 'operating'],
            ['code' => 'OFFICE', 'name' => 'Office & Rent', 'type' => 'operating'],
            ['code' => 'PHONE', 'name' => 'Phone & Internet', 'type' => 'operating'],
            ['code' => 'TRAVEL', 'name' => 'Travel', 'type' => 'operating'],
            ['code' => 'MARKETING', 'name' => 'Marketing', 'type' => 'operating'],
            ['code' => 'OTHER', 'name' => 'Other', 'type' => 'operating'],
        ];

        foreach ($expenseCategories as $index => $category) {
            ExpenseCategory::updateOrCreate(['code' => $category['code']], [
                'name' => $category['name'],
                'type' => $category['type'],
                'sort_order' => $index,
            ]);
        }
    }
}
