<?php

namespace App\Services\Import;

use App\Models\PriceListImport;
use App\Models\PriceListImportRow;
use App\Models\Product;
use App\Models\SupplierProduct;
use Illuminate\Support\Facades\DB;

/**
 * Turns a parsed price list into a reviewable set of proposed changes.
 *
 * Nothing is written to the catalogue here. Each row is classified — new, price
 * up, price down, unchanged, or suspicious — so the operator sees the whole diff
 * before a single price moves. A bad spreadsheet should never be able to corrupt
 * the catalogue silently.
 */
class PriceListMatcher
{
    /** A move this large is almost always a unit mix-up or a wrong column. */
    private const float SUSPICIOUS_CHANGE_PERCENT = 50.0;

    public function __construct(private readonly SheetReader $reader) {}

    /**
     * @param  array<int, array<int, string>>  $rows  raw sheet rows
     * @param  array<string, int>  $mapping  field => column index
     */
    public function build(PriceListImport $import, array $rows, array $mapping, int $firstDataRow): PriceListImport
    {
        return DB::transaction(function () use ($import, $rows, $mapping, $firstDataRow) {
            $import->rows()->delete();

            // The supplier's own catalogue, keyed by their SKU — the only key a
            // price list can reliably be matched on.
            $existing = SupplierProduct::query()
                ->where('supplier_id', $import->supplier_id)
                ->get()
                ->keyBy(fn (SupplierProduct $sp) => mb_strtolower($sp->supplier_sku));

            $counts = ['new' => 0, 'updated' => 0, 'unchanged' => 0, 'error' => 0];
            $changes = [];

            foreach ($rows as $index => $row) {
                $rowNumber = $index + 1;

                if ($rowNumber < $firstDataRow) {
                    continue;
                }

                $parsed = $this->parseRow($row, $mapping, $import);

                if ($parsed['supplier_sku'] === null || $parsed['unit_price'] === null) {
                    // A blank tail row is not an error, just the end of the data.
                    if ($this->isBlank($row)) {
                        continue;
                    }

                    $counts['error']++;
                    $this->writeRow($import, $rowNumber, $row, $parsed, [
                        'action' => 'error',
                        'errors' => ['Missing supplier SKU or price'],
                    ]);

                    continue;
                }

                $match = $existing->get(mb_strtolower($parsed['supplier_sku']));

                if ($match === null) {
                    $counts['new']++;
                    $this->writeRow($import, $rowNumber, $row, $parsed, [
                        'action' => 'create',
                        'match_method' => 'none',
                        'new_price' => $parsed['unit_price'],
                    ]);

                    continue;
                }

                $oldPrice = (float) $match->unit_price;
                $newPrice = $parsed['unit_price'];
                $changePercent = $oldPrice > 0 ? round(($newPrice - $oldPrice) / $oldPrice * 100, 2) : null;

                if (abs($newPrice - $oldPrice) < 0.00005) {
                    $counts['unchanged']++;
                    $this->writeRow($import, $rowNumber, $row, $parsed, [
                        'action' => 'unchanged',
                        'match_method' => 'supplier_sku',
                        'matched_product_id' => $match->product_id,
                        'matched_supplier_product_id' => $match->id,
                        'old_price' => $oldPrice,
                        'new_price' => $newPrice,
                        'change_percent' => 0,
                    ]);

                    continue;
                }

                $counts['updated']++;
                $changes[] = $changePercent ?? 0;
                $suspicious = $changePercent !== null && abs($changePercent) > self::SUSPICIOUS_CHANGE_PERCENT;

                $this->writeRow($import, $rowNumber, $row, $parsed, [
                    'action' => 'update_price',
                    'match_method' => 'supplier_sku',
                    'matched_product_id' => $match->product_id,
                    'matched_supplier_product_id' => $match->id,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'change_percent' => $changePercent,
                    // Pre-unticked, so a suspicious row has to be chosen deliberately.
                    'is_approved' => ! $suspicious,
                    'errors' => $suspicious ? ['Change exceeds 50% — check the price column and units'] : null,
                ]);
            }

            $import->update([
                'status' => 'previewed',
                'column_map' => $mapping,
                'rows_total' => array_sum($counts),
                'rows_new' => $counts['new'],
                'rows_updated' => $counts['updated'],
                'rows_unchanged' => $counts['unchanged'],
                'rows_error' => $counts['error'],
                'avg_change_percent' => $changes === [] ? null : round(array_sum($changes) / count($changes), 2),
            ]);

            return $import->fresh();
        });
    }

    /** @return array<string, mixed> */
    private function parseRow(array $row, array $mapping, PriceListImport $import): array
    {
        $value = function (string $field) use ($row, $mapping): ?string {
            $index = $mapping[$field] ?? null;

            if ($index === null) {
                return null;
            }

            $cell = trim($row[$index] ?? '');

            return $cell === '' ? null : $cell;
        };

        $number = fn (string $field) => $this->reader->parseNumber($value($field));

        return [
            'supplier_sku' => $value('supplier_sku'),
            'name' => $value('name'),
            'name_zh' => $value('name_zh'),
            'unit_price' => $number('unit_price'),
            'moq' => $number('moq'),
            'pack_size' => $number('pack_size'),
            'volume_cbm' => $number('volume_cbm'),
            'weight_kg' => $number('weight_kg'),
            'currency' => $import->currency,
        ];
    }

    private function writeRow(PriceListImport $import, int $rowNumber, array $raw, array $parsed, array $overrides): void
    {
        PriceListImportRow::create(array_merge([
            'price_list_import_id' => $import->id,
            'row_number' => $rowNumber,
            'raw' => array_slice($raw, 0, 30),
            'is_approved' => true,
        ], $parsed, $overrides));
    }

    private function isBlank(array $row): bool
    {
        return trim(implode('', $row)) === '';
    }

    /** Products already in the catalogue that this supplier does not yet quote. */
    public function unlinkedProducts(PriceListImport $import): int
    {
        return Product::query()
            ->whereDoesntHave('supplierProducts', fn ($q) => $q->where('supplier_id', $import->supplier_id))
            ->count();
    }
}
