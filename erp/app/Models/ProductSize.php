<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One size of one product, and what that size costs.
 *
 * Sizes belong to the product rather than to a shared pool: a crystal's 10mm
 * and a fabric's 150cm have nothing to say to each other, and a pool would have
 * to hold both. The label is free text for the same reason.
 *
 * The cost is the supplier's, and the supplier is already known from the
 * product's branch — nothing needs restating here.
 */
class ProductSize extends Model
{
    protected $fillable = [
        'product_id', 'label', 'cost_price', 'currency',
        'moq', 'effective_date', 'display_order', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:4',
            'moq' => 'decimal:4',
            'effective_date' => 'date',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Priced and unpriced are different states, and only one can be sold from. */
    public function isPriced(): bool
    {
        return $this->cost_price !== null;
    }

    public function scopePriced(Builder $query): Builder
    {
        return $query->whereNotNull('cost_price');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('label');
    }
}
