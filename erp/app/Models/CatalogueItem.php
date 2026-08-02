<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line in a supplier's catalogue for a flat-priced section.
 *
 * Serves Textile, Packaging and Furniture. Crystals has its own tables because
 * its pricing is a colour × size matrix; these three are flat lists where an
 * item carries one price with quantity breaks. The fields an item holds come
 * from its section's attribute schema.
 */
class CatalogueItem extends Model
{
    protected $fillable = [
        'price_list_section_id', 'supplier_id', 'code', 'name', 'name_zh',
        'attributes', 'unit_id', 'moq', 'pack_size', 'lead_time_days',
        'product_id', 'display_order', 'notes', 'is_active',
        // See Product: the shipping warning reads this, so it has to be settable.
        'contains_battery',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'contains_battery' => 'boolean',
            'moq' => 'decimal:4',
            'pack_size' => 'decimal:4',
            'display_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PriceListSection::class, 'price_list_section_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /** Set once this entry is actually stocked, linking it into purchasing. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(CatalogueItemPrice::class);
    }

    /** What you charge for it, per customer type. Cost lives on prices(). */
    public function sellPrices(): HasMany
    {
        return $this->hasMany(CatalogueItemSellPrice::class);
    }

    /**
     * The selling price for a customer type, falling back to the shared one.
     *
     * An exact match on the type beats the default, which is what makes a
     * wholesale price possible without repeating every other price under it.
     */
    public function sellPriceFor(?int $customerTypeId = null): ?CatalogueItemSellPrice
    {
        return $this->sellPrices
            ->filter(fn (CatalogueItemSellPrice $p) => $p->customer_type_id === $customerTypeId
                || $p->customer_type_id === null)
            ->sortByDesc(fn (CatalogueItemSellPrice $p) => $p->customer_type_id === $customerTypeId ? 1 : 0)
            ->first();
    }

    public function isStocked(): bool
    {
        return $this->product_id !== null;
    }

    /**
     * The price that applies at a given order quantity.
     *
     * Largest applicable break wins — ordering 6,000 metres gets the 5,000 rate,
     * not the 500 one.
     */
    public function priceFor(float $quantity = 1): ?CatalogueItemPrice
    {
        return $this->prices
            ->filter(fn (CatalogueItemPrice $p) => (float) $p->min_quantity <= $quantity)
            ->sortByDesc(fn (CatalogueItemPrice $p) => (float) $p->min_quantity)
            ->first();
    }

    /**
     * A named section attribute, e.g. composition on a fabric.
     *
     * Read via getAttribute() rather than `$this->attributes` — the column shares
     * its name with Eloquent's internal raw-attribute array, so inside this class
     * the property access would return the whole model's raw columns instead of
     * the cast JSON value.
     */
    public function attribute(string $key): ?string
    {
        $value = $this->sectionAttributes()[$key] ?? null;

        return $value === null || $value === '' ? null : (string) $value;
    }

    /** @return array<string, mixed> */
    public function sectionAttributes(): array
    {
        return (array) ($this->getAttribute('attributes') ?? []);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForSection(Builder $query, int $sectionId): Builder
    {
        return $query->where('price_list_section_id', $sectionId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        // whereLike, not `where(..., 'like', ...)`: SQLite matches LIKE
        // case-insensitively but PostgreSQL does not, so a raw LIKE search
        // silently stops finding anything once this runs on the production
        // database. whereLike emits ILIKE where the driver needs it.
        return $query->where(fn (Builder $q) => $q
            ->whereLike('code', "%{$term}%")
            ->orWhereLike('name', "%{$term}%")
            ->orWhereLike('name_zh', "%{$term}%"));
    }
}
