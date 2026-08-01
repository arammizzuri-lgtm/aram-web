<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPrice extends Model
{
    protected $fillable = [
        'product_id', 'price_tier_id', 'currency', 'price', 'min_quantity', 'valid_from', 'valid_to',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:4',
            'min_quantity' => 'decimal:4',
            'valid_from' => 'date',
            'valid_to' => 'date',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class, 'price_tier_id');
    }

    /** The price in force today for a tier, honouring quantity breaks. */
    public function scopeInForce(Builder $query, int $tierId, float $quantity = 1): Builder
    {
        return $query->where('price_tier_id', $tierId)
            ->where('min_quantity', '<=', $quantity)
            ->where(fn (Builder $q) => $q->whereNull('valid_from')->orWhereDate('valid_from', '<=', today()))
            ->where(fn (Builder $q) => $q->whereNull('valid_to')->orWhereDate('valid_to', '>=', today()))
            ->orderByDesc('min_quantity');
    }
}
