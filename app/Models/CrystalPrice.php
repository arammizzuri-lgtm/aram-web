<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The price of one colour in one size from one supplier.
 *
 * The supplier is stored here as well as on the product because a price only
 * ever means anything alongside the supplier who quoted it — the same P01 at
 * 10mm is a different number from every source.
 */
class CrystalPrice extends Model
{
    protected $fillable = [
        'supplier_id', 'crystal_product_id', 'crystal_size_id',
        'price', 'currency', 'moq', 'effective_date', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'moq' => 'decimal:4',
            'effective_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // The denormalised supplier must always agree with the product's.
        static::saving(function (self $price) {
            $price->supplier_id ??= $price->crystalProduct?->supplier_id;
        });
    }

    public function crystalProduct(): BelongsTo
    {
        return $this->belongsTo(CrystalProduct::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(CrystalSize::class, 'crystal_size_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(CrystalPriceHistory::class);
    }

    public function money(): Money
    {
        return Money::of($this->price, $this->currency);
    }

    /**
     * Set a new price, keeping the old one in history.
     *
     * Returns false when nothing moved, so re-saving an untouched grid does not
     * fill the history with noise.
     */
    public function updatePrice(float $newPrice, string $source = 'manual', ?int $userId = null): bool
    {
        $previous = (float) $this->price;

        if (abs($previous - $newPrice) < 0.00005) {
            return false;
        }

        $this->forceFill([
            'price' => $newPrice,
            'updated_by' => $userId ?? auth()->id(),
        ])->save();

        CrystalPriceHistory::create([
            'crystal_price_id' => $this->id,
            'price' => $newPrice,
            'previous_price' => $previous,
            'change_percent' => $previous > 0 ? round(($newPrice - $previous) / $previous * 100, 2) : null,
            'currency' => $this->currency,
            'effective_date' => today(),
            'source' => $source,
            'created_by' => $userId ?? auth()->id(),
        ]);

        return true;
    }

    public function scopeForSupplier(Builder $query, int $supplierId): Builder
    {
        return $query->where('supplier_id', $supplierId);
    }
}
