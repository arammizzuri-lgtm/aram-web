<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved column mapping for one supplier's spreadsheet format.
 *
 * Every supplier lays their price list out differently, and they rarely change
 * it. Saving the mapping once turns every later import of the same format into
 * a single click.
 */
class ImportProfile extends Model
{
    protected $fillable = [
        'supplier_id', 'name', 'sheet_name', 'header_row', 'first_data_row',
        'column_map', 'currency', 'decimal_separator', 'thousands_separator', 'is_default',
    ];

    protected function casts(): array
    {
        return [
            'column_map' => 'array',
            'header_row' => 'integer',
            'first_data_row' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
