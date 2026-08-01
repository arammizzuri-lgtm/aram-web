<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierProductPrice extends Model
{
    protected $fillable = [
        'supplier_product_id', 'currency', 'unit_price', 'previous_price',
        'change_percent', 'effective_date', 'source', 'price_list_import_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'previous_price' => 'decimal:4',
            'change_percent' => 'decimal:2',
            'effective_date' => 'date',
        ];
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }
}
