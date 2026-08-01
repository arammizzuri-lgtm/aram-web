<?php

namespace App\Actions\Catalog;

use App\Models\PriceListImport;
use App\Models\PriceListImportRow;
use App\Models\Product;
use App\Models\SupplierProduct;
use App\Models\SupplierProductPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Applies an approved price list to the catalogue.
 *
 * Runs in one transaction, and every price it moves leaves a history row behind,
 * which is what makes the whole import reversible afterwards. New products are
 * created as drafts rather than going straight on sale, because a price list
 * tells you what something costs, not what you should charge for it.
 */
class CommitPriceListImport
{
    public function handle(PriceListImport $import): PriceListImport
    {
        if ($import->status === 'committed') {
            throw new RuntimeException("Import #{$import->id} has already been committed.");
        }

        if ($import->status !== 'previewed') {
            throw new RuntimeException('Review the changes before committing this import.');
        }

        return DB::transaction(function () use ($import) {
            $effectiveDate = $import->effective_date?->toDateString() ?? today()->toDateString();
            $created = 0;
            $updated = 0;

            $rows = $import->rows()
                ->where('is_approved', true)
                ->whereIn('action', ['create', 'update_price'])
                ->get();

            foreach ($rows as $row) {
                if ($row->action === 'create') {
                    $this->createSupplierProduct($import, $row, $effectiveDate);
                    $created++;

                    continue;
                }

                $supplierProduct = SupplierProduct::find($row->matched_supplier_product_id);

                if ($supplierProduct === null) {
                    continue;
                }

                if ($supplierProduct->recordPrice((float) $row->new_price, 'import', $effectiveDate, $import->id)) {
                    $updated++;
                }
            }

            $import->update([
                'status' => 'committed',
                'committed_at' => now(),
                'rows_new' => $created,
                'rows_updated' => $updated,
            ]);

            return $import->fresh();
        });
    }

    private function createSupplierProduct(PriceListImport $import, PriceListImportRow $row, string $effectiveDate): void
    {
        $product = $row->matched_product_id
            ? Product::find($row->matched_product_id)
            : null;

        // A price list is a costing document, so anything new starts as a draft
        // with no selling price — someone still has to decide what to charge.
        $product ??= Product::create([
            'sku' => $this->generateSku($import, $row),
            'name' => $row->name ?: $row->supplier_sku,
            'name_zh' => $row->name_zh,
            'slug' => Str::slug(($row->supplier_sku ?? '').'-'.Str::random(6)),
            'default_supplier_id' => $import->supplier_id,
            'cost_price' => $row->new_price,
            'selling_price' => 0,
            'volume_cbm' => $row->volume_cbm ?? 0,
            'weight_kg' => $row->weight_kg ?? 0,
            'pack_size' => $row->pack_size ?? 1,
            'status' => 'draft',
            'is_active' => false,
            'is_sellable' => false,
        ]);

        $supplierProduct = SupplierProduct::create([
            'supplier_id' => $import->supplier_id,
            'product_id' => $product->id,
            'supplier_sku' => $row->supplier_sku,
            'supplier_name' => $row->name,
            'supplier_name_zh' => $row->name_zh,
            'currency' => $import->currency,
            'unit_price' => $row->new_price,
            'moq' => $row->moq,
            'pack_size' => $row->pack_size ?? 1,
            'last_quoted_at' => $effectiveDate,
        ]);

        SupplierProductPrice::create([
            'supplier_product_id' => $supplierProduct->id,
            'currency' => $import->currency,
            'unit_price' => $row->new_price,
            'effective_date' => $effectiveDate,
            'source' => 'import',
            'price_list_import_id' => $import->id,
        ]);

        $row->forceFill(['matched_product_id' => $product->id, 'matched_supplier_product_id' => $supplierProduct->id])->save();
    }

    /**
     * Derive an internal SKU for a product the price list introduced.
     *
     * Built from the supplier code and their own SKU so it stays recognisable
     * on both sides of a WeChat conversation, with a counter appended if that
     * combination is somehow already taken.
     */
    private function generateSku(PriceListImport $import, PriceListImportRow $row): string
    {
        $supplierCode = Str::of($import->supplier?->code ?? 'SUP')
            ->afterLast('-')
            ->upper()
            ->limit(6, '');

        $base = Str::of($row->supplier_sku ?? Str::random(6))
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(24, '');

        $candidate = "{$supplierCode}-{$base}";
        $suffix = 1;

        while (Product::where('sku', $candidate)->exists()) {
            $candidate = "{$supplierCode}-{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Roll the catalogue back to the prices it held before this import.
     *
     * Possible because every change wrote a history row carrying the previous
     * price; products the import created are deactivated rather than deleted, in
     * case anything has already been ordered against them.
     */
    public function revert(PriceListImport $import): PriceListImport
    {
        if ($import->status !== 'committed') {
            throw new RuntimeException('Only a committed import can be reverted.');
        }

        return DB::transaction(function () use ($import) {
            $history = SupplierProductPrice::query()
                ->where('price_list_import_id', $import->id)
                ->get();

            foreach ($history as $entry) {
                $supplierProduct = SupplierProduct::find($entry->supplier_product_id);

                if ($supplierProduct === null) {
                    continue;
                }

                if ($entry->previous_price !== null) {
                    $supplierProduct->forceFill(['unit_price' => $entry->previous_price])->save();

                    continue;
                }

                // No previous price means this row created the link.
                $supplierProduct->product?->forceFill(['is_active' => false, 'status' => 'draft'])->saveQuietly();
                $supplierProduct->delete();
            }

            $history->each->delete();

            $import->update(['status' => 'reverted', 'reverted_at' => now()]);

            return $import->fresh();
        });
    }
}
