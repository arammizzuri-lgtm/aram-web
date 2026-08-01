<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A size in the shared pool.
 *
 * Sizes are not per-supplier: a supplier's available sizes are simply the ones
 * they have priced. That way a supplier offering 60mm adds one row here rather
 * than duplicating every size that already exists.
 */
class CrystalSize extends Model
{
    protected $fillable = ['size_mm', 'label', 'display_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'size_mm' => 'decimal:2',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CrystalPrice::class);
    }

    /** "10 mm" — trailing zeros dropped, since 10.00 mm reads as noise. */
    public function getLabelAttribute(?string $value): string
    {
        return $value ?: rtrim(rtrim((string) $this->size_mm, '0'), '.').' mm';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Only the sizes a given supplier actually quotes. */
    public function scopePricedBy(Builder $query, int $supplierId): Builder
    {
        return $query->whereHas('prices', fn (Builder $q) => $q->where('supplier_id', $supplierId));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('display_order')->orderBy('size_mm');
    }
}
