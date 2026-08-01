<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Groups the variants of one model — the same chandelier in gold and in chrome —
 * without a full variant matrix. Each sellable variant stays its own product, so
 * it can carry its own SKU, weight, CBM and landed cost.
 */
class ProductGroup extends Model
{
    protected $fillable = ['name', 'product_category_id', 'notes'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}
