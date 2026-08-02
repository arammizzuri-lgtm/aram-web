<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One colour in one supplier's crystal catalogue.
 *
 * Codes and names belong to the supplier, not to us: Supplier A's P01 "Crystal"
 * and Supplier B's equivalent may carry entirely different codes, names and
 * finishes. Nothing here is shared between suppliers except the size pool.
 */
class CrystalProduct extends Model
{
    protected $fillable = [
        'supplier_id', 'crystal_code', 'crystal_name', 'finish', 'colour_hex',
        'display_order', 'product_id', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CrystalPrice::class);
    }

    /** What you charge, per size and customer type. Cost lives on prices(). */
    public function sellPrices(): HasMany
    {
        return $this->hasMany(CrystalSellPrice::class);
    }

    /**
     * The selling price for one size and customer type.
     *
     * An exact match on the type beats the shared default, so a wholesale rate
     * can be set for a few sizes without restating the rest.
     */
    public function sellPriceFor(int $sizeId, ?int $customerTypeId = null): ?CrystalSellPrice
    {
        return $this->sellPrices
            ->filter(fn (CrystalSellPrice $p) => $p->crystal_size_id === $sizeId)
            ->filter(fn (CrystalSellPrice $p) => $p->customer_type_id === $customerTypeId
                || $p->customer_type_id === null)
            ->sortByDesc(fn (CrystalSellPrice $p) => $p->customer_type_id === $customerTypeId ? 1 : 0)
            ->first();
    }

    /** Set once this colour is actually stocked, linking it into purchasing. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isStocked(): bool
    {
        return $this->product_id !== null;
    }

    /** Prices keyed by size id, for laying a catalogue row out as a grid. */
    public function priceMap(): array
    {
        return $this->prices
            ->mapWithKeys(fn (CrystalPrice $price) => [$price->crystal_size_id => $price])
            ->all();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        // whereLike keeps the search case-insensitive on PostgreSQL too — a
        // plain LIKE is case-sensitive there and would find nothing.
        return $query->where(fn (Builder $q) => $q
            ->whereLike('crystal_code', "%{$term}%")
            ->orWhereLike('crystal_name', "%{$term}%"));
    }
}
