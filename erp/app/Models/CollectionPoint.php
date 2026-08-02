<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Your forwarder's warehouses in China.
 *
 * These are addresses you hand to suppliers, not storage you control. Nothing
 * of yours sits here in any sense worth tracking — no quantities, no stock,
 * nothing to reconcile. `address_zh` matters most: it is what the supplier's
 * driver actually reads.
 */
class CollectionPoint extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'city', 'address', 'address_zh', 'contact_name', 'phone',
        'is_active', 'display_order',
    ];

    /**
     * Mirrored from the column default so a freshly created point reads as
     * active straight away. Relying on the database default alone leaves this
     * null on the in-memory model until it is reloaded.
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function consignments(): HasMany
    {
        return $this->hasMany(Consignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
