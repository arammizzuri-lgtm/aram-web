<?php

namespace Tests\Feature;

use App\Actions\Catalog\CommitPriceListImport;
use App\Models\PriceListImport;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\Unit;
use App\Services\Import\PriceListMatcher;
use App\Services\Import\SheetReader;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PriceListImportTest extends TestCase
{
    use RefreshDatabase;

    private Supplier $supplier;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
        ]);

        // Its own fixtures rather than the demo seeder, which is gone. Import
        // behaviour should be provable from the smallest catalogue that shows
        // it — a rise, a cut, an unchanged line and a new item.
        $this->supplier = Supplier::create([
            'code' => 'SUP-NBL',
            'name' => 'Ningbo Lighting Co.',
            'default_currency' => 'USD',
            'is_active' => true,
        ]);

        $this->seedCatalogue();

        $this->path = $this->writeSheet();
    }

    /** The prices the sheet under test is measured against. */
    private function seedCatalogue(): void
    {
        $category = ProductCategory::create(['name' => 'Chandeliers', 'slug' => 'chandeliers']);
        $unit = Unit::where('code', 'PCS')->firstOrFail();

        // sku, name, supplier sku, current cost
        $catalogue = [
            ['CRY-0042', 'Crystal Chandelier A-330', 'A-330', '85.00'],
            ['CRY-0043', 'Crystal Chandelier A-331', 'A-331', '92.00'],
            ['CRY-0088', 'Crystal Wall Light Duo', 'W-112', '24.50'],
            ['CRY-0120', 'Crystal Table Lamp Pearl', 'T-205', '31.00'],
        ];

        foreach ($catalogue as [$sku, $name, $supplierSku, $cost]) {
            $product = Product::create([
                'sku' => $sku,
                'name' => $name,
                'product_category_id' => $category->id,
                'unit_id' => $unit->id,
                'default_supplier_id' => $this->supplier->id,
                'cost_price' => $cost,
                'selling_price' => (string) ((float) $cost * 1.8),
                'is_active' => true,
            ]);

            SupplierProduct::create([
                'supplier_id' => $this->supplier->id,
                'product_id' => $product->id,
                'supplier_sku' => $supplierSku,
                'currency' => 'USD',
                'unit_price' => $cost,
                'is_preferred' => true,
            ]);
        }
    }

    protected function tearDown(): void
    {
        @unlink($this->path);

        parent::tearDown();
    }

    /**
     * A price list shaped the way real ones arrive: junk above the header, a
     * price rise, a price cut, an unchanged line, a brand-new item, a nonsense
     * jump, and a broken row.
     */
    private function writeSheet(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pricelist').'.csv';

        $lines = [
            'NINGBO LIGHTING CO., LTD',
            '2026 Price List — FOB Ningbo',
            '',
            'Item No,Description,USD/PC,MOQ,PCS/CTN,CBM',
            'A-330,Crystal Chandelier A-330,"92.50",50,2,0.08',   // was 85.00 → +8.82%
            'A-331,Crystal Chandelier A-331,"88.00",50,2,0.11',   // was 92.00 → −4.35%
            'W-112,Crystal Wall Light Duo,"24.50",100,6,0.02',    // unchanged
            'X-999,Crystal Pendant New Model,"41.00",30,4,0.05',  // new
            'T-205,Crystal Table Lamp Pearl,"310.00",40,4,0.03',  // was 31.00 → +900%, suspicious
            'BROKEN-ROW,,,,,',                                     // no price
        ];

        file_put_contents($path, implode("\n", $lines));

        return $path;
    }

    private function analyse(): PriceListImport
    {
        $reader = app(SheetReader::class);
        $rows = $reader->read($this->path);

        $import = PriceListImport::create([
            'supplier_id' => $this->supplier->id,
            'original_filename' => 'pricelist.csv',
            'stored_path' => $this->path,
            'status' => 'parsing',
            'header_row' => 4,
            'currency' => 'USD',
            'effective_date' => today(),
        ]);

        $mapping = $reader->guessMapping($rows[3]);

        return app(PriceListMatcher::class)->build($import, $rows, $mapping, 5);
    }

    #[Test]
    public function it_finds_the_columns_despite_the_supplier_naming(): void
    {
        $mapping = app(SheetReader::class)->guessMapping(
            ['Item No', 'Description', 'USD/PC', 'MOQ', 'PCS/CTN', 'CBM']
        );

        $this->assertSame(0, $mapping['supplier_sku']);
        $this->assertSame(1, $mapping['name']);
        $this->assertSame(2, $mapping['unit_price']);
        $this->assertSame(3, $mapping['moq']);
        $this->assertSame(5, $mapping['volume_cbm']);
    }

    #[Test]
    public function it_classifies_every_row_without_touching_the_catalogue(): void
    {
        $before = SupplierProduct::where('supplier_id', $this->supplier->id)->pluck('unit_price', 'supplier_sku');

        $import = $this->analyse();

        $this->assertSame('previewed', $import->status);
        $this->assertSame(1, $import->rows_new);
        $this->assertSame(3, $import->rows_updated);
        $this->assertSame(1, $import->rows_unchanged);
        $this->assertSame(1, $import->rows_error);

        $after = SupplierProduct::where('supplier_id', $this->supplier->id)->pluck('unit_price', 'supplier_sku');
        $this->assertEquals($before, $after, 'previewing must not change a single price');
    }

    #[Test]
    public function a_wild_price_jump_is_flagged_and_left_unticked(): void
    {
        $import = $this->analyse();

        $row = $import->rows()->where('supplier_sku', 'T-205')->firstOrFail();

        $this->assertSame('update_price', $row->action);
        $this->assertFalse($row->is_approved, 'a 900% jump must not be approved by default');
        $this->assertTrue($row->isSuspicious());
    }

    #[Test]
    public function committing_applies_only_the_approved_rows(): void
    {
        $import = $this->analyse();

        app(CommitPriceListImport::class)->handle($import);

        $this->assertSame('92.5000', $this->priceFor('A-330'), 'the rise was applied');
        $this->assertSame('88.0000', $this->priceFor('A-331'), 'the cut was applied');
        $this->assertSame('31.0000', $this->priceFor('T-205'), 'the suspicious row was skipped');
        $this->assertNotNull(SupplierProduct::where('supplier_sku', 'X-999')->first(), 'the new item was created');
    }

    /** A price list says what something costs, not what to sell it for. */
    #[Test]
    public function newly_created_products_are_drafts_with_no_selling_price(): void
    {
        app(CommitPriceListImport::class)->handle($this->analyse());

        $product = SupplierProduct::where('supplier_sku', 'X-999')->firstOrFail()->product;

        $this->assertSame('draft', $product->status);
        $this->assertFalse($product->is_active);
        $this->assertSame('0.0000', $product->selling_price);
        $this->assertSame('41.0000', $product->cost_price);
    }

    #[Test]
    public function every_applied_change_is_recorded_in_price_history(): void
    {
        app(CommitPriceListImport::class)->handle($this->analyse());

        $history = SupplierProduct::where('supplier_sku', 'A-330')->firstOrFail()->priceHistory()->first();

        $this->assertSame('92.5000', $history->unit_price);
        $this->assertSame('85.0000', $history->previous_price);
        $this->assertSame('8.82', $history->change_percent);
        $this->assertSame('import', $history->source);
    }

    /** The safety net: history is what makes the whole import reversible. */
    #[Test]
    public function an_import_can_be_undone(): void
    {
        $import = app(CommitPriceListImport::class)->handle($this->analyse());

        app(CommitPriceListImport::class)->revert($import);

        $this->assertSame('85.0000', $this->priceFor('A-330'), 'the original price is restored');
        $this->assertSame('92.0000', $this->priceFor('A-331'));
        $this->assertNull(SupplierProduct::where('supplier_sku', 'X-999')->first(), 'the created link is removed');
        $this->assertSame('reverted', $import->fresh()->status);
    }

    #[Test]
    public function an_import_cannot_be_committed_twice(): void
    {
        $import = app(CommitPriceListImport::class)->handle($this->analyse());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already been committed');

        app(CommitPriceListImport::class)->handle($import);
    }

    #[Test]
    public function messy_numbers_are_parsed(): void
    {
        $reader = app(SheetReader::class);

        $this->assertSame(1234.56, $reader->parseNumber('$1,234.56'));
        $this->assertSame(92.5, $reader->parseNumber(' USD 92.50 '));
        $this->assertSame(1234.56, $reader->parseNumber('1.234,56', ','));
        $this->assertNull($reader->parseNumber(''));
        $this->assertNull($reader->parseNumber('N/A'));
    }

    private function priceFor(string $supplierSku): string
    {
        return SupplierProduct::where('supplier_id', $this->supplier->id)
            ->where('supplier_sku', $supplierSku)
            ->value('unit_price');
    }
}
