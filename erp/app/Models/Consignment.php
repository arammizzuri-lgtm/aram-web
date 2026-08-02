<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A tracking number from your forwarder.
 *
 * A deal can arrive under several of these — one per supplier, or shipped in
 * batches as suppliers finish. One of these can carry goods for several deals,
 * consolidated to save cost. Both happen, so the link runs both ways.
 *
 * Weight and CBM are recorded even though the freight amount is typed from the
 * bill rather than calculated. They earn their place by making the split honest
 * when one bill covers more than one customer.
 */
class Consignment extends Model
{
    use SoftDeletes;

    public const MODES = [
        'sea' => 'Sea',
        'air_no_battery' => 'Air (no battery)',
        'air_battery' => 'Air (with battery)',
    ];

    public const STATUSES = [
        'awaiting' => 'Awaiting',
        'in_transfer' => 'In transfer',
        'arrived' => 'Arrived',
        'delivered' => 'Delivered',
    ];

    protected $fillable = [
        'tracking_number', 'mode', 'collection_point_id',
        'boxes', 'gross_weight_kg', 'cbm', 'status',
        'freight_amount', 'freight_currency', 'freight_base', 'exchange_rate',
        'shipped_at', 'arrived_at', 'delivered_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'boxes' => 'integer',
            'gross_weight_kg' => 'decimal:3',
            'cbm' => 'decimal:4',
            'freight_amount' => 'decimal:4',
            'freight_base' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'shipped_at' => 'date',
            'arrived_at' => 'date',
            'delivered_at' => 'date',
        ];
    }

    public function collectionPoint(): BelongsTo
    {
        return $this->belongsTo(CollectionPoint::class);
    }

    public function deals(): BelongsToMany
    {
        return $this->belongsToMany(Deal::class)
            ->using(ConsignmentDeal::class)
            ->withPivot(['freight_share', 'freight_share_base', 'share_is_manual'])
            ->withTimestamps();
    }

    public function isAir(): bool
    {
        return str_starts_with($this->mode, 'air');
    }

    /** Only battery-capable air can carry lithium goods. */
    public function acceptsBatteries(): bool
    {
        return $this->mode !== 'air_no_battery';
    }

    /** The split interface only appears once more than one deal is attached. */
    public function isShared(): bool
    {
        return $this->deals()->count() > 1;
    }

    /**
     * How the freight bill should be divided, before you adjust it.
     *
     * Split by what the mode is actually charged for: sea freight buys space,
     * so CBM; air freight buys weight, so kilograms. Splitting by goods value
     * instead — the obvious lazy choice — would systematically over-charge
     * small expensive crystals and under-charge bulky cheap fabric, which for
     * this product mix is precisely the wrong answer.
     *
     * Returns deal id => share, summing exactly to the freight total: allocate()
     * hands the rounding residual to the largest share so nothing is lost.
     *
     * Falls back to an even split when the deals carry no weight or volume
     * figures, because a share of nothing cannot be apportioned.
     *
     * @param  array<int, float>  $weights  deal id => kg or cbm for that deal
     * @return array<int, Money>
     */
    public function suggestedSplit(array $weights = []): array
    {
        $freight = Money::of($this->freight_amount ?? 0, $this->freight_currency ?: 'USD');
        $dealIds = $this->deals()->pluck('deals.id')->all();

        if ($dealIds === [] || $freight->isZero()) {
            return [];
        }

        $ratios = [];

        foreach ($dealIds as $id) {
            $ratios[$id] = (float) ($weights[$id] ?? 0);
        }

        // Nothing to go on — divide evenly rather than invent a basis.
        if (array_sum($ratios) <= 0) {
            $ratios = array_fill_keys($dealIds, 1);
        }

        return $freight->allocate($ratios);
    }

    /** What this consignment is charged on: CBM by sea, kilograms by air. */
    public function splitBasis(): string
    {
        return $this->isAir() ? 'weight' : 'cbm';
    }

    public function scopeInTransit(Builder $query): Builder
    {
        return $query->whereIn('status', ['awaiting', 'in_transfer']);
    }
}
