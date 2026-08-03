<?php

namespace Database\Seeders;

use App\Models\CatalogueItem;
use App\Models\CatalogueItemPrice;
use App\Models\CrystalProduct;
use App\Models\CrystalSize;
use App\Models\PriceListSection;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Supplier A's crystal catalogue, transcribed from the printed colour chart.
 *
 * Codes and names are the supplier's own and are stored as data — nothing here
 * is referenced by code anywhere in the application. Prices are deliberately
 * left empty: the chart shows colours, not rates, so they are entered per size
 * in the price grid rather than invented here.
 */
class CrystalCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $this->sections();
        $this->sizes();

        $supplier = $this->supplier();

        $this->catalogue($supplier);
        $this->flatSections();
    }

    /**
     * Starter lines for Textile, Packaging and Furniture.
     *
     * Enough of each to show the shape — the fields each section carries and the
     * quantity-break pricing — against the Chinese suppliers already seeded.
     */
    private function flatSections(): void
    {
        // firstOrCreate, not updateOrCreate: if the demo seeder already made
        // these suppliers, their real details win over these placeholders.
        $textile = $this->flatSupplier('SUP-SHT', 'Shaoxing Textile Mills', 'Shaoxing');
        $furniture = $this->flatSupplier('SUP-FSF', 'Foshan Furniture Group', 'Foshan');
        $packaging = $this->flatSupplier('SUP-NBL', 'Ningbo Lighting Co.', 'Ningbo');

        // section code, supplier, code, name, name_zh, attributes, moq, [break => price]
        $lines = [
            ['textile', $textile, 'TX-JAC-280', 'Jacquard Upholstery 280cm', '提花面料', [
                'composition' => '70% Polyester / 30% Cotton', 'width_cm' => 280, 'gsm' => 340,
                'colour' => 'Ivory', 'finish' => 'Stain-resistant',
            ], 300, [1 => 6.80, 500 => 6.20, 3000 => 5.60]],
            ['textile', $textile, 'TX-VEL-150', 'Velvet Emerald 150cm', '祖母绿天鹅绒', [
                'composition' => '100% Polyester', 'width_cm' => 150, 'gsm' => 280,
                'colour' => 'Emerald', 'finish' => 'Crush-resistant',
            ], 200, [1 => 4.90, 500 => 4.45, 3000 => 4.10]],
            ['textile', $textile, 'TX-TUL-300', 'Bridal Tulle 300cm', '新娘网纱', [
                'composition' => '100% Nylon', 'width_cm' => 300, 'gsm' => 45,
                'colour' => 'Off-white', 'finish' => 'Soft',
            ], 500, [1 => 1.35, 1000 => 1.18, 5000 => 1.02]],
            ['textile', $textile, 'TX-LAC-030', 'Corded Lace Trim 3cm', '花边', [
                'composition' => '90% Nylon / 10% Spandex', 'width_cm' => 3, 'gsm' => 60,
                'colour' => 'Gold', 'finish' => 'Metallic thread',
            ], 1000, [1 => 0.62, 2000 => 0.54]],

            ['packaging', $packaging, 'PK-BOX-RIG', 'Rigid Gift Box 25×25×10', '硬盒', [
                'material' => 'Greyboard 1200gsm', 'length_cm' => 25, 'width_cm' => 25,
                'height_cm' => 10, 'printing' => 'CMYK + matte lamination',
            ], 500, [1 => 1.85, 1000 => 1.52, 5000 => 1.24]],
            ['packaging', $packaging, 'PK-BAG-KRF', 'Kraft Paper Bag with Rope Handle', '牛皮纸袋', [
                'material' => 'Kraft 210gsm', 'length_cm' => 32, 'width_cm' => 12,
                'height_cm' => 40, 'printing' => '1-colour logo',
            ], 1000, [1 => 0.48, 2000 => 0.41, 10000 => 0.33]],
            ['packaging', $packaging, 'PK-RIB-SAT', 'Satin Ribbon 25mm', '缎带', [
                'material' => 'Polyester satin', 'length_cm' => 2.5, 'width_cm' => 2.5,
                'height_cm' => null, 'printing' => 'Hot-stamped',
            ], 2000, [1 => 0.14, 5000 => 0.11]],
            ['packaging', $packaging, 'PK-TAG-HAN', 'Hang Tag with Eyelet', '吊牌', [
                'material' => 'Art card 350gsm', 'length_cm' => 9, 'width_cm' => 5,
                'height_cm' => null, 'printing' => 'CMYK both sides',
            ], 2000, [1 => 0.09, 10000 => 0.06]],

            ['furniture', $furniture, 'FN-SOF-MIL', 'Milano 3+2+1 Sofa Set', '米兰沙发套装', [
                'material' => 'Solid beech frame, linen blend', 'finish' => 'Beige',
                'dimensions' => '210×90×85 / 150×90×85 / 95×90×85', 'cbm' => 1.6,
                'assembly' => 'Legs only',
            ], 5, [1 => 220.00, 20 => 208.00, 50 => 196.00]],
            ['furniture', $furniture, 'FN-TAB-OAK', 'Oak Dining Table 180cm', '橡木餐桌', [
                'material' => 'Solid oak', 'finish' => 'Natural matte',
                'dimensions' => '180×90×76', 'cbm' => 0.72, 'assembly' => 'Flat-pack',
            ], 10, [1 => 145.00, 30 => 136.00]],
            ['furniture', $furniture, 'FN-CHR-VEL', 'Velvet Dining Chair', '天鹅绒餐椅', [
                'material' => 'Metal frame, velvet', 'finish' => 'Dusty rose',
                'dimensions' => '45×52×88', 'cbm' => 0.085, 'assembly' => 'Legs only',
            ], 40, [1 => 22.00, 100 => 20.50, 400 => 18.90]],
            ['furniture', $furniture, 'FN-MIR-RND', 'Round Wall Mirror 80cm', '圆形壁镜', [
                'material' => 'Iron frame, 4mm glass', 'finish' => 'Brushed gold',
                'dimensions' => '80×80×3', 'cbm' => 0.06, 'assembly' => 'None',
            ], 20, [1 => 34.00, 100 => 30.50]],
        ];

        foreach ($lines as $order => [$sectionCode, $supplier, $code, $name, $nameZh, $attributes, $moq, $breaks]) {
            $section = PriceListSection::where('code', $sectionCode)->first();

            if ($section === null) {
                continue;
            }

            $item = CatalogueItem::updateOrCreate(
                ['price_list_section_id' => $section->id, 'supplier_id' => $supplier->id, 'code' => $code],
                [
                    'name' => $name,
                    'name_zh' => $nameZh,
                    'attributes' => array_filter($attributes, fn ($v) => $v !== null),
                    'moq' => $moq,
                    'display_order' => $order,
                    'is_active' => true,
                ],
            );

            foreach ($breaks as $minQuantity => $price) {
                CatalogueItemPrice::updateOrCreate(
                    ['catalogue_item_id' => $item->id, 'min_quantity' => $minQuantity],
                    [
                        'supplier_id' => $supplier->id,
                        'price' => $price,
                        'currency' => 'USD',
                        'effective_date' => now()->subMonths(2)->toDateString(),
                    ],
                );
            }
        }
    }

    private function sections(): void
    {
        /*
         * Each section declares the fields its items carry. Crystals is the
         * exception — its pricing is a colour × size matrix with its own tables,
         * so it has no attribute schema here.
         */
        $sections = [
            [
                'code' => 'crystals', 'name' => 'Crystals', 'icon' => 'heroicon-o-sparkles',
                'route_name' => '/erp/product-price-list?section=crystals', 'item_label' => 'Colour',
                'description' => 'Rhinestones, crystals and decorative stones.',
                'attribute_schema' => null, 'price_unit' => null,
            ],
            [
                'code' => 'textile', 'name' => 'Textile', 'icon' => 'heroicon-o-swatch',
                'route_name' => '/erp/product-price-list?section=textile', 'item_label' => 'Fabric',
                'description' => 'Fabrics, lace, embroidery, tulle, mesh, trims and accessories.',
                'price_unit' => 'per metre',
                'attribute_schema' => [
                    ['key' => 'composition', 'label' => 'Composition', 'type' => 'text'],
                    ['key' => 'width_cm', 'label' => 'Width', 'type' => 'number', 'unit' => 'cm'],
                    ['key' => 'gsm', 'label' => 'Weight', 'type' => 'number', 'unit' => 'g/m²'],
                    ['key' => 'colour', 'label' => 'Colour', 'type' => 'text'],
                    ['key' => 'finish', 'label' => 'Finish', 'type' => 'text'],
                ],
            ],
            [
                'code' => 'packaging', 'name' => 'Packaging', 'icon' => 'heroicon-o-gift',
                'route_name' => '/erp/product-price-list?section=packaging', 'item_label' => 'Item',
                'description' => 'Boxes, bags, gift packaging, labels, tags and ribbons.',
                'price_unit' => 'per unit',
                'attribute_schema' => [
                    ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
                    ['key' => 'length_cm', 'label' => 'Length', 'type' => 'number', 'unit' => 'cm'],
                    ['key' => 'width_cm', 'label' => 'Width', 'type' => 'number', 'unit' => 'cm'],
                    ['key' => 'height_cm', 'label' => 'Height', 'type' => 'number', 'unit' => 'cm'],
                    ['key' => 'printing', 'label' => 'Printing', 'type' => 'text'],
                ],
            ],
            [
                'code' => 'furniture', 'name' => 'Furniture', 'icon' => 'heroicon-o-home-modern',
                'route_name' => '/erp/product-price-list?section=furniture', 'item_label' => 'Model',
                'description' => 'Furniture, décor, lighting and home accessories.',
                'price_unit' => 'per piece',
                'attribute_schema' => [
                    ['key' => 'material', 'label' => 'Material', 'type' => 'text'],
                    ['key' => 'finish', 'label' => 'Finish', 'type' => 'text'],
                    ['key' => 'dimensions', 'label' => 'Dimensions', 'type' => 'text'],
                    ['key' => 'cbm', 'label' => 'Volume', 'type' => 'number', 'unit' => 'm³'],
                    ['key' => 'assembly', 'label' => 'Assembly', 'type' => 'text'],
                ],
            ],
        ];

        foreach ($sections as $index => $section) {
            PriceListSection::updateOrCreate(
                ['code' => $section['code']],
                $section + ['sort_order' => $index, 'is_active' => true],
            );
        }
    }

    private function sizes(): void
    {
        foreach ([10, 12, 16, 20, 30, 40, 50] as $index => $millimetres) {
            CrystalSize::updateOrCreate(
                ['size_mm' => $millimetres],
                ['display_order' => $index, 'is_active' => true],
            );
        }
    }

    /** A stand-in supplier so this seeder runs on its own, without the demo data. */
    private function flatSupplier(string $code, string $name, string $city): Supplier
    {
        return Supplier::firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'city' => $city,
                'country' => 'CN',
                'default_currency' => 'USD',
                'default_incoterm' => 'FOB',
                'payment_terms_days' => 30,
                'is_active' => true,
            ],
        );
    }

    private function supplier(): Supplier
    {
        return Supplier::updateOrCreate(
            ['code' => 'SUP-A'],
            [
                'name' => 'Supplier A',
                'country' => 'CN',
                'default_currency' => 'CNY',
                'default_incoterm' => 'FOB',
                'payment_terms_days' => 30,
                'deposit_percent' => 30,
                'is_active' => true,
                'notes' => 'Crystal and rhinestone catalogue. Rename once the real company name is confirmed.',
            ],
        );
    }

    private function catalogue(Supplier $supplier): void
    {
        foreach ($this->colours() as $order => [$code, $name, $finish]) {
            CrystalProduct::updateOrCreate(
                ['supplier_id' => $supplier->id, 'crystal_code' => $code],
                [
                    'crystal_name' => $name,
                    'finish' => $finish,
                    'display_order' => $order,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * All 90 colours, in the order they appear on the chart.
     *
     * The finish is kept because the chart groups by it and because an Aurora
     * Borealis coating is priced differently from the plain colour it sits beside.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function colours(): array
    {
        $plain = [
            ['P01', 'Crystal'], ['P02', 'Jet Black'], ['P03', 'Black Diamond'],
            ['P04', 'Lt. Amethyst'], ['P05', 'Amethyst'], ['P06', 'Tanzanite'],
            ['P08', 'Peridot'], ['P09', 'Emerald'], ['P10', 'Olivine'],
            ['P11', 'Lt. Sapphire'], ['P12', 'Sapphire'], ['P13', 'Aquamarine'],
            ['P14', 'Green Zircon'], ['P15', 'Capri Blue'], ['P16', 'Montana'],
            ['P17', 'Jonquil'], ['P18', 'Lt. Col. Topaz'], ['P19', 'Topaz'],
            ['P20', 'Smoked Topaz'], ['P23', 'Light Peach'], ['P24', 'Citrine'],
            ['P25', 'Hyacinth'], ['P26', 'Lt. Siam'], ['P27', 'Siam'],
            ['P28', 'Lt. Rose'], ['P29', 'Rose'], ['P30', 'Fuchsia'],
            ['P31', 'Blue Zircon'], ['P38', 'Transparent Clear'], ['P37', 'Rose Golden'],
        ];

        $aurora = [
            ['P56', 'Crystal AB'], ['P57', 'Black AB'], ['P65', 'Black Diamond AB'],
            ['P79', 'Lt. Amethyst AB'], ['P72', 'Amethyst AB'], ['P78', 'Tanzanite AB'],
            ['P61', 'Peridot AB'], ['P69', 'Emerald AB'], ['P59', 'Olivine AB'],
            ['P63', 'Lt. Sapphire AB'], ['P54', 'Sapphire AB'], ['P67', 'Aquamarine AB'],
            ['P64', 'Green Zircon AB'], ['P71', 'Capri Blue AB'], ['P82', 'Montana AB'],
            ['P75', 'Jonquil AB'], ['P68', 'Lt. Col. Topaz AB'], ['P70', 'Topaz AB'],
            ['P83', 'Smoked Topaz AB'], ['P76', 'Light Peach AB'], ['P60', 'Citrine AB'],
            ['P80', 'Hyacinth AB'], ['P55', 'Light Siam AB'], ['P62', 'Siam AB'],
            ['P66', 'Light Rose AB'], ['P81', 'Rose AB'], ['P77', 'Fuchsia AB'],
            ['P73', 'Blue Zircon AB'], ['P74', 'Transparent AB'], ['P58', 'Rainbow Rose Golden'],
        ];

        $special = [
            ['P32', 'Purple Crystal'], ['P33', 'White Opal'], ['P34', 'Pink Opal'],
            ['P35', 'Blue Opal'], ['P36', 'Green Opal'], ['P39', 'Jet Hematite'],
            ['P40', 'Labrador'], ['P41', 'Aurum'], ['P42', 'VM'],
            ['P43', 'Red Volcano'], ['P44', 'Blue Volcano'], ['P47', 'Green Volcano'],
            ['P101', 'Neon Blue'], ['P102', 'Neon Orange'], ['P103', 'Neon Citrine'],
            ['P104', 'Neon Rose'], ['P105', 'Glow White AB'], ['P106', 'Glow Citrine AB'],
            ['P112', 'Glow Hyacinth AB'], ['P100', 'Glow Purple AB'], ['P46', 'Metallic Blue'],
            ['P48', 'Morning'], ['P49', 'Amber'], ['P50', 'Sun Golden'],
            ['P51', 'Purple Velvet'], ['P52', 'Air Violet'], ['P53', 'Air Rose'],
            ['P45', 'Star Sky'], ['P22', 'GSHA'], ['P96', 'Ceramic White'],
        ];

        return [
            ...array_map(fn (array $c) => [...$c, 'plain'], $plain),
            ...array_map(fn (array $c) => [...$c, 'ab'], $aurora),
            ...array_map(fn (array $c) => [...$c, 'special'], $special),
        ];
    }
}
