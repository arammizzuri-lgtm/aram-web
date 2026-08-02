<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One supplier's version of one of your products.
 *
 * Suppliers quote their own SKUs and their own (often Chinese) product names.
 * Price lists are matched against this, never against your catalogue SKU — and
 * because the same product can appear here for several suppliers, it is also how
 * you compare who to reorder from.
 */
class SupplierProduct extends Model
{
    protected $fillable = [
        'supplier_id', 'product_id', 'supplier_sku', 'supplier_name', 'supplier_name_zh',
        'currency', 'unit_price', 'price_unit_id', 'moq', 'pack_size',
        'lead_time_days', 'is_preferred', 'last_quoted_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:4',
            'moq' => 'decimal:4',
            'pack_size' => 'decimal:4',
            'is_preferred' => 'boolean',
            'last_quoted_at' => 'date',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(SupplierProductPrice::class);
    }

    /**
     * Record a new quoted price, keeping the old one in history.
     *
     * Returns false when the price has not moved, so an unchanged import does not
     * fill the history with noise.
     */
    public function recordPrice(float $price, string $source = 'manual', ?string $effectiveDate = null, ?int $importId = null): bool
    {
        $previous = (float) $this->unit_price;

        if (abs($previous - $price) < 0.00005) {
            return false;
        }

        SupplierProductPrice::create([
            'supplier_product_id' => $this->id,
            'currency' => $this->currency,
            'unit_price' => $price,
            'previous_price' => $previous,
            'change_percent' => $previous > 0 ? round(($price - $previous) / $previous * 100, 2) : null,
            'effective_date' => $effectiveDate ?? today()->toDateString(),
            'source' => $source,
            'price_list_import_id' => $importId,
        ]);

        $this->forceFill([
            'unit_price' => $price,
            'last_quoted_at' => $effectiveDate ?? today()->toDateString(),
        ])->save();

        return true;
    }

    public function scopePreferred(Builder $query): Builder
    {
        return $query->where('is_preferred', true);
    }
}
