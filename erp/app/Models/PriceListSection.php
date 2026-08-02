<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section of the Price Lists module.
 *
 * Crystals, Textile, Packaging and Furniture are rows here rather than classes,
 * so a fifth category is a record plus that section's own catalogue tables — no
 * change to navigation, permissions or the module shell.
 */
class PriceListSection extends Model
{
    protected $fillable = [
        'code', 'name', 'description', 'icon', 'route_name', 'sort_order', 'is_active',
        'attribute_schema', 'price_unit', 'item_label',
    ];

    protected function casts(): array
    {
        return [
            'attribute_schema' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(CatalogueItem::class);
    }

    /**
     * The fields items in this section carry.
     *
     * Declared as data so fabric can have composition, width and GSM while
     * packaging has dimensions and material, without either needing its own
     * table or its own screen.
     *
     * @return array<int, array{key: string, label: string, type?: string, unit?: string}>
     */
    public function attributes(): array
    {
        return (array) ($this->getAttribute('attribute_schema') ?? []);
    }

    /** A section is only usable once its catalogue tables and screen exist. */
    public function isImplemented(): bool
    {
        return filled($this->route_name);
    }

    public function url(): ?string
    {
        return $this->isImplemented() ? url($this->route_name) : null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
