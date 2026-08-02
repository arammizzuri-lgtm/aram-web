<?php

namespace App\Services\Import;

use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\ODS\Options as OdsOptions;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use RuntimeException;

/**
 * Reads a supplier price list into plain rows.
 *
 * Streams rather than loading the file into memory, because supplier catalogues
 * routinely run to tens of thousands of rows and PhpSpreadsheet-style full-tree
 * parsing falls over on them.
 */
class SheetReader
{
    /** Enough to let the operator pick the header row by eye without loading the file. */
    public const int PREVIEW_ROWS = 25;

    /**
     * Read rows as zero-indexed column arrays.
     *
     * @return array<int, array<int, string>>
     */
    public function read(string $path, ?string $sheetName = null, int $limit = 0): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("Cannot read the uploaded file at {$path}.");
        }

        $reader = $this->readerFor($path);
        $reader->open($path);

        $rows = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                if ($sheetName !== null && $sheet->getName() !== $sheetName) {
                    continue;
                }

                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = array_map(
                        fn ($cell) => trim((string) $cell->getValue()),
                        $row->getCells(),
                    );

                    if ($limit > 0 && count($rows) >= $limit) {
                        break 2;
                    }
                }

                break;
            }
        } finally {
            $reader->close();
        }

        return $rows;
    }

    /** @return array<int, array<int, string>> */
    public function preview(string $path, ?string $sheetName = null): array
    {
        return $this->read($path, $sheetName, self::PREVIEW_ROWS);
    }

    /**
     * Build a reader that keeps blank rows.
     *
     * OpenSpout drops empty rows by default, which would shift every index and
     * silently misalign the header row: the operator picks "row 4" from what
     * they see in Excel, and blank spacer rows above the table are extremely
     * common in supplier price lists. Preserving them keeps our row numbers and
     * the spreadsheet's row numbers the same thing.
     */
    private function readerFor(string $path): ReaderInterface
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'csv', 'txt' => new CsvReader(tap(new CsvOptions, fn ($o) => $o->SHOULD_PRESERVE_EMPTY_ROWS = true)),
            'ods' => new OdsReader(tap(new OdsOptions, fn ($o) => $o->SHOULD_PRESERVE_EMPTY_ROWS = true)),
            default => new XlsxReader(tap(new XlsxOptions, fn ($o) => $o->SHOULD_PRESERVE_EMPTY_ROWS = true)),
        };
    }

    /** @return array<int, string> */
    public function sheetNames(string $path): array
    {
        $reader = $this->readerFor($path);
        $reader->open($path);

        $names = [];

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $names[] = $sheet->getName();
            }
        } finally {
            $reader->close();
        }

        return $names;
    }

    /**
     * Guess which of your fields each column holds.
     *
     * Chinese suppliers label columns in English, Chinese or both, so headers are
     * matched against a list of aliases rather than an exact string. A guess is
     * only ever a starting point — the operator confirms it before anything runs.
     *
     * @param  array<int, string>  $headerRow
     * @return array<string, int> field => column index
     */
    public function guessMapping(array $headerRow): array
    {
        $aliases = [
            'supplier_sku' => ['sku', 'item', 'item no', 'item code', 'model', 'model no', 'art no', 'article', 'code', '型号', '货号', '编号'],
            'name' => ['name', 'description', 'product', 'product name', 'desc', '品名', '名称', '产品名称'],
            'name_zh' => ['chinese', 'chinese name', '中文', '中文名'],
            'unit_price' => ['price', 'unit price', 'fob', 'fob price', 'usd', 'usd/pc', 'cost', '单价', '价格'],
            'moq' => ['moq', 'min order', 'minimum', 'min qty', '起订量'],
            'pack_size' => ['pack', 'pcs/ctn', 'qty/ctn', 'per carton', 'packing', '装箱数'],
            'volume_cbm' => ['cbm', 'volume', 'm3', 'meas', '体积'],
            'weight_kg' => ['kg', 'weight', 'gw', 'g.w.', 'gross weight', '毛重'],
        ];

        $mapping = [];

        foreach ($headerRow as $index => $header) {
            $normalised = mb_strtolower(trim($header));

            if ($normalised === '') {
                continue;
            }

            foreach ($aliases as $field => $candidates) {
                if (isset($mapping[$field])) {
                    continue;
                }

                foreach ($candidates as $candidate) {
                    if ($normalised === $candidate || str_contains($normalised, $candidate)) {
                        $mapping[$field] = $index;

                        continue 3;
                    }
                }
            }
        }

        return $mapping;
    }

    /**
     * Parse a number written the way a supplier wrote it.
     *
     * Handles currency symbols, thousands separators and the European comma
     * decimal, all of which turn up in real price lists.
     */
    public function parseNumber(?string $value, string $decimalSeparator = '.'): ?float
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $cleaned = preg_replace('/[^\d,.\-]/', '', $value) ?? '';

        if ($cleaned === '' || $cleaned === '-') {
            return null;
        }

        $cleaned = $decimalSeparator === ','
            ? str_replace(['.', ','], ['', '.'], $cleaned)
            : str_replace(',', '', $cleaned);

        return is_numeric($cleaned) ? (float) $cleaned : null;
    }
}
