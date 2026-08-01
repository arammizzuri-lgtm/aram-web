<?php

namespace Database\Seeders;

use App\Actions\Inventory\ReceiveShipment;
use App\Models\Brand;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FreightForwarder;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PriceTier;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Shipment;
use App\Models\ShipmentCost;
use App\Models\ShipmentCostType;
use App\Models\ShipmentEvent;
use App\Models\ShipmentItem;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Services\Costing\LandedCostCalculator;
use App\Services\Inventory\StockLedger;
use Carbon\CarbonInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * A realistic slice of the business: Chinese suppliers, the product categories
 * actually imported, local wholesale customers, and one fully costed container.
 *
 * The container is the worked example from docs/04-LANDED-COST.md, so the landed
 * costs shown on screen are the same figures the engine is tested against.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::where('code', 'MAIN')->firstOrFail();
        $pcs = Unit::where('code', 'PCS')->firstOrFail();
        $carton = Unit::where('code', 'CTN')->firstOrFail();
        $set = Unit::where('code', 'SET')->firstOrFail();
        $roll = Unit::where('code', 'ROLL')->firstOrFail();

        $categories = $this->categories();
        $brands = $this->brands();
        $suppliers = $this->suppliers();
        $products = $this->products($categories, $brands, $suppliers, compact('pcs', 'carton', 'set', 'roll'));

        $customers = $this->customers();
        $this->purchaseOrders($warehouse, $products, $suppliers);
        $shipment = $this->container($warehouse, $products);

        if ($shipment !== null) {
            app(ReceiveShipment::class)->handle($shipment);
            $this->sales($warehouse, $products, $customers);
            $this->expenses($shipment);
        }
    }

    /** @return array<string, ProductCategory> */
    private function categories(): array
    {
        $tree = [
            'Crystals' => ['hs' => '9405.10', 'duty' => 15, 'children' => ['Chandeliers', 'Wall Lights', 'Crystal Décor']],
            'Furniture' => ['hs' => '9401.61', 'duty' => 20, 'children' => ['Sofas', 'Tables', 'Chairs', 'Bedroom']],
            'Fabrics & Textiles' => ['hs' => '5407.61', 'duty' => 10, 'children' => ['Upholstery', 'Curtains', 'Bedding']],
            'Home Decoration' => ['hs' => '6913.90', 'duty' => 12, 'children' => ['Vases', 'Mirrors', 'Wall Art']],
            'Building Materials' => ['hs' => '6907.21', 'duty' => 8, 'children' => ['Tiles', 'Sanitary', 'Fittings']],
        ];

        $created = [];

        foreach ($tree as $name => $meta) {
            $parent = ProductCategory::updateOrCreate(['slug' => Str::slug($name)], [
                'name' => $name,
                'default_hs_code' => $meta['hs'],
                'default_duty_rate' => $meta['duty'],
                'is_active' => true,
            ]);

            $created[$name] = $parent;

            foreach ($meta['children'] as $child) {
                $created[$child] = ProductCategory::updateOrCreate(['slug' => Str::slug($child)], [
                    'name' => $child,
                    'parent_id' => $parent->id,
                    'default_hs_code' => $meta['hs'],
                    'default_duty_rate' => $meta['duty'],
                    'is_active' => true,
                ]);
            }
        }

        return $created;
    }

    /** @return array<string, Brand> */
    private function brands(): array
    {
        return collect(['Lumière', 'Milano Living', 'Silk Road', 'Casa Nova'])
            ->mapWithKeys(fn (string $name) => [
                $name => Brand::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]),
            ])->all();
    }

    /** @return array<string, Supplier> */
    private function suppliers(): array
    {
        $suppliers = [
            [
                'code' => 'SUP-NBL', 'name' => 'Ningbo Lighting Co.', 'name_zh' => '宁波照明有限公司',
                'contact_person' => 'Li Wei', 'city' => 'Ningbo', 'port_of_loading' => 'Ningbo',
                'wechat_id' => 'ningbo_lighting', 'whatsapp' => '+86 574 8888 1234',
                'email' => 'sales@ningbolighting.cn', 'average_lead_time_days' => 42, 'rating' => 4,
            ],
            [
                'code' => 'SUP-FSF', 'name' => 'Foshan Furniture Group', 'name_zh' => '佛山家具集团',
                'contact_person' => 'Chen Hao', 'city' => 'Foshan', 'port_of_loading' => 'Shenzhen',
                'wechat_id' => 'foshan_furn', 'whatsapp' => '+86 757 2233 5566',
                'email' => 'export@foshanfurniture.cn', 'average_lead_time_days' => 55, 'rating' => 5,
            ],
            [
                'code' => 'SUP-SHT', 'name' => 'Shaoxing Textile Mills', 'name_zh' => '绍兴纺织厂',
                'contact_person' => 'Wang Fang', 'city' => 'Shaoxing', 'port_of_loading' => 'Ningbo',
                'wechat_id' => 'sx_textile', 'whatsapp' => '+86 575 8899 7766',
                'email' => 'info@sxtextile.cn', 'average_lead_time_days' => 35, 'rating' => 4,
            ],
        ];

        $created = [];

        foreach ($suppliers as $supplier) {
            $created[$supplier['code']] = Supplier::updateOrCreate(
                ['code' => $supplier['code']],
                $supplier + [
                    'country' => 'CN',
                    'default_currency' => 'USD',
                    'default_incoterm' => 'FOB',
                    'deposit_percent' => 30,
                    'payment_terms_days' => 30,
                    'is_active' => true,
                ],
            );
        }

        return $created;
    }

    /**
     * @param  array<string, ProductCategory>  $categories
     * @param  array<string, Brand>  $brands
     * @param  array<string, Supplier>  $suppliers
     * @return array<string, Product>
     */
    private function products(array $categories, array $brands, array $suppliers, array $units): array
    {
        // sku, name, name_zh, category, brand, supplier, cost, sell, kg, cbm, duty, unit, supplier sku, pack
        $catalogue = [
            ['CRY-0042', 'Crystal Chandelier A-330 · 8 Light Gold', '水晶吊灯 A-330', 'Chandeliers', 'Lumière', 'SUP-NBL', '85.00', '155.00', '12', '0.08', 15, 'pcs', 'A-330', 2],
            ['CRY-0043', 'Crystal Chandelier A-331 · 12 Light Chrome', '水晶吊灯 A-331', 'Chandeliers', 'Lumière', 'SUP-NBL', '92.00', '168.00', '15', '0.11', 15, 'pcs', 'A-331', 2],
            ['CRY-0088', 'Crystal Wall Light Duo', '水晶壁灯', 'Wall Lights', 'Lumière', 'SUP-NBL', '24.50', '46.00', '3.2', '0.02', 15, 'pcs', 'W-112', 6],
            ['CRY-0120', 'Crystal Table Lamp Pearl', '水晶台灯', 'Crystal Décor', 'Lumière', 'SUP-NBL', '31.00', '58.00', '4.5', '0.03', 15, 'pcs', 'T-205', 4],

            ['FUR-0117', 'Sofa Set Milano · 3+2+1 Beige', '米兰沙发套装', 'Sofas', 'Milano Living', 'SUP-FSF', '220.00', '395.00', '45', '1.60', 20, 'set', 'B-114', 1],
            ['FUR-0118', 'Sofa Set Verona · L-Shape Grey', '维罗纳转角沙发', 'Sofas', 'Milano Living', 'SUP-FSF', '265.00', '470.00', '52', '1.95', 20, 'set', 'B-118', 1],
            ['FUR-0203', 'Dining Table Oak 180cm', '橡木餐桌', 'Tables', 'Milano Living', 'SUP-FSF', '145.00', '260.00', '38', '0.72', 20, 'pcs', 'D-330', 1],
            ['FUR-0210', 'Dining Chair Velvet (set of 4)', '天鹅绒餐椅', 'Chairs', 'Milano Living', 'SUP-FSF', '88.00', '162.00', '18', '0.34', 20, 'set', 'D-341', 1],

            ['FAB-0233', 'Fabric Roll Jacquard 280cm', '提花面料', 'Upholstery', 'Silk Road', 'SUP-SHT', '18.00', '33.00', '8', '0.06', 10, 'roll', 'C-902', 1],
            ['FAB-0234', 'Fabric Roll Velvet Emerald', '祖母绿天鹅绒', 'Upholstery', 'Silk Road', 'SUP-SHT', '22.50', '41.00', '9.5', '0.07', 10, 'roll', 'C-915', 1],
            ['FAB-0301', 'Blackout Curtain Fabric Charcoal', '遮光窗帘布', 'Curtains', 'Silk Road', 'SUP-SHT', '14.20', '27.50', '6.8', '0.05', 10, 'roll', 'K-440', 1],

            ['DEC-0455', 'Ceramic Vase Set Ivory (3pc)', '陶瓷花瓶套装', 'Vases', 'Casa Nova', 'SUP-NBL', '19.80', '38.00', '5.5', '0.045', 12, 'set', 'V-778', 4],
            ['DEC-0470', 'Wall Mirror Round Gold 80cm', '圆形金色壁镜', 'Mirrors', 'Casa Nova', 'SUP-NBL', '34.00', '64.00', '7.2', '0.06', 12, 'pcs', 'M-201', 2],

            ['BLD-0512', 'Porcelain Floor Tile 80×80 Marble', '瓷砖', 'Tiles', null, 'SUP-FSF', '9.40', '17.50', '22', '0.03', 8, 'm2', 'P-880', 1],
            ['BLD-0530', 'Basin Mixer Tap Brushed Steel', '面盆龙头', 'Sanitary', null, 'SUP-FSF', '16.60', '31.00', '1.9', '0.008', 8, 'pcs', 'S-660', 10],
        ];

        $unitMap = [
            'pcs' => $units['pcs'], 'set' => $units['set'],
            'roll' => $units['roll'], 'm2' => $units['pcs'],
        ];

        $created = [];

        foreach ($catalogue as $row) {
            [$sku, $name, $nameZh, $category, $brand, $supplierCode, $cost, $sell, $kg, $cbm, $duty, $unit, $supplierSku, $pack] = $row;

            $supplier = $suppliers[$supplierCode];

            $product = Product::updateOrCreate(['sku' => $sku], [
                'name' => $name,
                'name_zh' => $nameZh,
                'slug' => Str::slug($sku.' '.$name),
                'product_category_id' => $categories[$category]->id,
                'brand_id' => $brand ? $brands[$brand]->id : null,
                'default_supplier_id' => $supplier->id,
                'unit_id' => ($unitMap[$unit] ?? $units['pcs'])->id,
                'purchase_unit_id' => $units['carton']->id,
                'pack_size' => $pack,
                'weight_kg' => $kg,
                'volume_cbm' => $cbm,
                'hs_code' => $categories[$category]->default_hs_code,
                'duty_rate' => $duty,
                'country_of_origin' => 'CN',
                'cost_price' => $cost,
                'selling_price' => $sell,
                'selling_price_currency' => 'USD',
                'min_selling_price' => round((float) $cost * 1.15, 2),
                'target_margin_percent' => 35,
                'reorder_level' => 25,
                'reorder_quantity' => 100,
                'lead_time_days' => $supplier->average_lead_time_days,
                'track_stock' => true,
                'is_active' => true,
                'is_sellable' => true,
                'is_purchasable' => true,
                'status' => 'active',
            ]);

            SupplierProduct::updateOrCreate(
                ['supplier_id' => $supplier->id, 'supplier_sku' => $supplierSku],
                [
                    'product_id' => $product->id,
                    'supplier_name' => $name,
                    'supplier_name_zh' => $nameZh,
                    'currency' => 'USD',
                    'unit_price' => $cost,
                    'moq' => 50,
                    'pack_size' => $pack,
                    'lead_time_days' => $supplier->average_lead_time_days,
                    'is_preferred' => true,
                    'last_quoted_at' => now()->subMonths(2)->toDateString(),
                ],
            );

            $created[$sku] = $product;
        }

        return $created;
    }

    /** @return array<string, Customer> */
    private function customers(): array
    {
        $wholesale = PriceTier::where('code', 'WHOLESALE')->first();
        $vip = PriceTier::where('code', 'VIP')->first();

        $customers = [
            ['code' => 'CUS-0001', 'name' => 'Erbil Home Center', 'name_ar' => 'مركز أربيل للمنزل', 'city' => 'Erbil', 'area' => 'Ainkawa', 'credit_limit' => 20000, 'tier' => $vip],
            ['code' => 'CUS-0002', 'name' => 'Sulaymaniyah Furniture House', 'name_ar' => 'بيت الأثاث السليمانية', 'city' => 'Sulaymaniyah', 'area' => 'Salim St', 'credit_limit' => 15000, 'tier' => $wholesale],
            ['code' => 'CUS-0003', 'name' => 'Duhok Lighting Gallery', 'name_ar' => 'معرض دهوك للإنارة', 'city' => 'Duhok', 'area' => 'Nizarke', 'credit_limit' => 8000, 'tier' => $wholesale],
            ['code' => 'CUS-0004', 'name' => 'Baghdad Décor Trading', 'name_ar' => 'بغداد للديكور', 'city' => 'Baghdad', 'area' => 'Karrada', 'credit_limit' => 25000, 'tier' => $vip],
            ['code' => 'CUS-0005', 'name' => 'Kirkuk Textile Market', 'name_ar' => 'سوق كركوك للنسيج', 'city' => 'Kirkuk', 'area' => 'Central', 'credit_limit' => 6000, 'tier' => $wholesale],
        ];

        $created = [];

        foreach ($customers as $customer) {
            $created[$customer['code']] = Customer::updateOrCreate(['code' => $customer['code']], [
                'name' => $customer['name'],
                'name_ar' => $customer['name_ar'],
                'city' => $customer['city'],
                'area' => $customer['area'],
                'credit_limit' => $customer['credit_limit'],
                'credit_limit_currency' => 'USD',
                'default_currency' => 'USD',
                'price_tier_id' => $customer['tier']?->id,
                'payment_terms_days' => 30,
                'is_active' => true,
            ]);
        }

        return $created;
    }

    /**
     * The orders behind the container, so the chain reads end to end:
     * order → proforma → deposit → production → shipped → received.
     *
     * @param  array<string, Product>  $products
     * @param  array<string, Supplier>  $suppliers
     */
    private function purchaseOrders(Warehouse $warehouse, array $products, array $suppliers): void
    {
        if (PurchaseOrder::query()->exists()) {
            return;
        }

        // supplier, days ago, status, PI ref, [sku => [cartons, unit price]]
        $orders = [
            ['SUP-NBL', 92, 'received', 'PI-NBL-26041', ['CRY-0042' => [50, '85.00']]],
            ['SUP-FSF', 90, 'received', 'PI-FSF-8842', ['FUR-0117' => [20, '220.00']]],
            ['SUP-SHT', 88, 'received', 'PI-SHT-2210', ['FAB-0233' => [300, '18.00']]],
            ['SUP-NBL', 21, 'in_production', 'PI-NBL-26118', [
                'CRY-0043' => [40, '92.00'],
                'CRY-0088' => [120, '24.50'],
            ]],
            ['SUP-FSF', 9, 'confirmed', 'PI-FSF-9014', [
                'FUR-0203' => [15, '145.00'],
                'FUR-0210' => [30, '88.00'],
            ]],
        ];

        foreach ($orders as [$supplierCode, $daysAgo, $status, $reference, $lines]) {
            $supplier = $suppliers[$supplierCode];
            $orderedAt = now()->subDays($daysAgo);

            $order = PurchaseOrder::create([
                'supplier_id' => $supplier->id,
                'warehouse_id' => $warehouse->id,
                'order_date' => $orderedAt->toDateString(),
                'expected_date' => $orderedAt->copy()->addDays($supplier->average_lead_time_days ?? 45)->toDateString(),
                'status' => $status,
                'currency' => 'USD',
                'exchange_rate' => 1,
                'incoterm' => $supplier->default_incoterm,
                'supplier_reference' => $reference,
                'deposit_percent' => $supplier->deposit_percent,
                'port_of_loading' => $supplier->port_of_loading,
                'payment_terms_days' => $supplier->payment_terms_days,
            ]);

            foreach ($lines as $sku => [$cartons, $unitPrice]) {
                $product = $products[$sku];
                $packSize = max(1.0, (float) $product->pack_size);
                $baseQuantity = $cartons * $packSize;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $order->id,
                    'product_id' => $product->id,
                    'supplier_sku' => $product->supplierProducts()->where('supplier_id', $supplier->id)->value('supplier_sku'),
                    // Ordered in cartons, stored in the unit stock is held in.
                    'order_quantity' => $cartons,
                    'pack_size' => $packSize,
                    'quantity' => $baseQuantity,
                    'received_quantity' => $status === 'received' ? $baseQuantity : 0,
                    'unit_price' => $unitPrice,
                    'unit_weight_kg' => $product->weight_kg,
                    'unit_volume_cbm' => $product->volume_cbm,
                    'hs_code' => $product->hs_code,
                    'duty_rate' => $product->duty_rate ?? 0,
                ]);
            }

            $order->recalculateTotals();
            $order->forceFill(['base_total' => $order->fresh()->total])->saveQuietly();
        }
    }

    /** @param array<string, Product> $products */
    private function container(Warehouse $warehouse, array $products): ?Shipment
    {
        if (Shipment::where('container_number', 'TCLU8877661')->exists()) {
            return null;
        }

        $forwarder = FreightForwarder::updateOrCreate(
            ['name' => 'Golden Route Logistics'],
            ['code' => 'GRL', 'contact_person' => 'Zhang Min', 'country' => 'CN', 'is_active' => true],
        );

        $shipment = Shipment::create([
            'freight_forwarder_id' => $forwarder->id,
            'warehouse_id' => $warehouse->id,
            'shipping_method' => 'sea_fcl',
            'container_number' => 'TCLU8877661',
            'container_type' => '40hq',
            'bl_number' => 'COSU6398471250',
            'port_of_loading' => 'Ningbo',
            'port_of_discharge' => 'Umm Qasr',
            'etd' => now()->subDays(46)->toDateString(),
            'atd' => now()->subDays(45)->toDateString(),
            'eta' => now()->subDays(6)->toDateString(),
            'ata' => now()->subDays(5)->toDateString(),
            'customs_cleared_at' => now()->subDays(2)->toDateString(),
            'status' => 'cleared',
            'notes' => 'Mixed container: crystals, furniture and textiles.',
        ]);

        // The worked example from docs/04-LANDED-COST.md §4.
        $manifest = [
            ['CRY-0042', 100, '85.00'],
            ['FUR-0117', 20, '220.00'],
            ['FAB-0233', 300, '18.00'],
        ];

        foreach ($manifest as [$sku, $quantity, $unitCost]) {
            $product = $products[$sku];

            $item = new ShipmentItem([
                'shipment_id' => $shipment->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'currency' => 'USD',
                'exchange_rate' => 1,
            ]);

            $item->snapshotFromProduct($product)->save();
        }

        $costs = [
            ['sea_freight', '3200.00', 'Ningbo → Umm Qasr, 40HQ'],
            ['insurance', '183.00', 'All-risk, 1% of value'],
            ['customs_duty', '0', 'Calculated per HS code'],
            ['clearance_agent', '450.00', 'Customs broker fee'],
            ['bank_charges', '95.00', 'Telegraphic transfer'],
            ['port_charges', '380.00', 'Umm Qasr terminal handling'],
            ['inland_transport', '600.00', 'Umm Qasr → Erbil warehouse'],
        ];

        foreach ($costs as [$code, $amount, $description]) {
            $type = ShipmentCostType::where('code', $code)->firstOrFail();

            ShipmentCost::create([
                'shipment_id' => $shipment->id,
                'shipment_cost_type_id' => $type->id,
                'description' => $description,
                'amount' => $amount,
                'currency' => 'USD',
                'exchange_rate' => 1,
                'allocation_basis' => $type->default_allocation_basis->value,
                'is_estimated' => false,
                'incurred_at' => now()->subDays(10)->toDateString(),
            ]);
        }

        $timeline = [
            ['booked', 'Container booked with Golden Route Logistics', 46],
            ['departed', 'Sailed from Ningbo', 45],
            ['arrived', 'Arrived Umm Qasr', 5],
            ['customs_cleared', 'Customs cleared, entry 2026/IQ/88213', 2],
        ];

        foreach ($timeline as [$event, $description, $daysAgo]) {
            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'event' => $event,
                'description' => $description,
                'occurred_at' => now()->subDays($daysAgo),
            ]);
        }

        $shipment->refresh()->refreshTotals();

        // Cost it, so the demo shows real landed costs rather than empty columns.
        $run = app(LandedCostCalculator::class)->calculate($shipment->fresh());
        $run->update(['status' => 'applied', 'applied_at' => now()]);
        $shipment->update(['landed_cost_status' => 'actual']);

        return $shipment->fresh();
    }

    /**
     * A few weeks of trading against the container that just landed.
     *
     * COGS is snapshotted from the product's landed cost at the moment each
     * invoice is posted, which is what makes the reported margin real rather
     * than a guess against the supplier price.
     *
     * @param  array<string, Product>  $products
     * @param  array<string, Customer>  $customers
     */
    private function sales(Warehouse $warehouse, array $products, array $customers): void
    {
        $ledger = app(StockLedger::class);

        // customer, days ago, paid?, [sku => [qty, price]]
        $orders = [
            ['CUS-0001', 24, 'paid', ['CRY-0042' => [12, 155.00], 'DEC-0455' => [20, 38.00]]],
            ['CUS-0002', 19, 'paid', ['FUR-0117' => [4, 395.00]]],
            ['CUS-0003', 14, 'partial', ['CRY-0042' => [8, 158.00], 'CRY-0088' => [15, 46.00]]],
            ['CUS-0005', 9, 'unpaid', ['FAB-0233' => [80, 33.00]]],
            ['CUS-0004', 4, 'unpaid', ['FUR-0117' => [3, 410.00], 'FAB-0233' => [40, 34.00]]],
            ['CUS-0001', 2, 'unpaid', ['CRY-0042' => [6, 155.00]]],
        ];

        foreach ($orders as [$customerCode, $daysAgo, $payment, $lines]) {
            $customer = $customers[$customerCode];
            $date = now()->subDays($daysAgo);

            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'invoice_date' => $date->toDateString(),
                'due_date' => $date->copy()->addDays(30)->toDateString(),
                'status' => 'posted',
                'invoice_type' => 'standard',
                'currency' => 'USD',
                'exchange_rate' => 1,
                'posted_at' => $date,
            ]);

            $cogs = 0.0;

            foreach ($lines as $sku => [$quantity, $price]) {
                $product = $products[$sku]->fresh();
                $unitCost = (float) $product->average_cost;

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'unit_cost_base' => $unitCost,
                ]);

                $cogs += $quantity * $unitCost;

                if ($product->stockOnHand($warehouse->id) >= $quantity) {
                    $ledger->issue($product, $warehouse->id, $quantity, reference: $invoice);
                }
            }

            $invoice->recalculateTotals();
            $invoice->refresh();

            $total = (float) $invoice->total;
            $profit = $total - $cogs;

            $invoice->forceFill([
                'base_total' => $total,
                'cogs_total_base' => round($cogs, 4),
                'gross_profit_base' => round($profit, 4),
                'margin_percent' => $total > 0 ? round($profit / $total * 100, 2) : 0,
            ])->saveQuietly();

            $this->settle($invoice, $payment, $date);
        }
    }

    private function settle(Invoice $invoice, string $mode, CarbonInterface $invoicedAt): void
    {
        if ($mode === 'unpaid') {
            return;
        }

        $amount = $mode === 'paid'
            ? (float) $invoice->total
            : round((float) $invoice->total * 0.4, 2);

        $payment = Payment::create([
            'customer_id' => $invoice->customer_id,
            'invoice_id' => $invoice->id,
            'payment_date' => $invoicedAt->copy()->addDays(12)->toDateString(),
            'amount' => $amount,
            'method' => 'bank_transfer',
            'currency' => 'USD',
            'exchange_rate' => 1,
            'base_amount' => $amount,
        ]);

        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'base_amount' => $amount,
            'allocated_at' => now(),
        ]);

        $invoice->forceFill([
            'amount_paid' => $amount,
            'status' => $mode === 'paid' ? 'paid' : 'partially_paid',
        ])->saveQuietly();
    }

    /** Operating overheads — deliberately separate from the shipping costs inside landed cost. */
    private function expenses(Shipment $shipment): void
    {
        $overheads = [
            ['WAREHOUSE', 'Warehouse rent — August', 1800, 12],
            ['SALARIES', 'Staff salaries — August', 4200, 10],
            ['FUEL', 'Delivery van fuel', 340, 8],
            ['OFFICE', 'Office supplies & utilities', 260, 6],
            ['MARKETING', 'Catalogue printing', 480, 3],
        ];

        foreach ($overheads as [$code, $description, $amount, $daysAgo]) {
            Expense::create([
                'expense_category_id' => ExpenseCategory::where('code', $code)->value('id'),
                'expense_date' => now()->subDays($daysAgo)->toDateString(),
                'description' => $description,
                'amount' => $amount,
                'currency' => 'USD',
                'exchange_rate' => 1,
                'status' => 'paid',
                'is_allocated_to_shipment' => false,
            ]);
        }
    }
}
