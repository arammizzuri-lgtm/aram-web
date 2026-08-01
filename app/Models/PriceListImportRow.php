<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceListImportRow extends Model
{
    protected $fillable = [
        'price_list_import_id', 'row_number', 'raw', 'supplier_sku', 'name', 'name_zh',
        'currency', 'unit_price', 'moq', 'pack_size', 'volume_cbm', 'weight_kg',
        'matched_product_id', 'matched_supplier_product_id', 'match_method', 'match_confidence',
        'action', 'old_price', 'new_price', 'change_percent', 'is_approved', 'errors',
    ];

    protected function casts(): array
    {
        return [
            'raw' => 'array',
            'errors' => 'array',
            'unit_price' => 'decimal:4',
            'old_price' => 'decimal:4',
            'new_price' => 'decimal:4',
            'change_percent' => 'decimal:2',
            'moq' => 'decimal:4',
            'pack_size' => 'decimal:4',
            'volume_cbm' => 'decimal:6',
            'weight_kg' => 'decimal:4',
            'is_approved' => 'boolean',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PriceListImport::class, 'price_list_import_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'matched_product_id');
    }

    public function isSuspicious(): bool
    {
        return filled($this->errors) && $this->action === 'update_price';
    }
}
