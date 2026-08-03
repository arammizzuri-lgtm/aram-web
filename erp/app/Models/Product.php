<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        // The tree, the supplier who owns it, and which price list it lives in.
        'parent_id', 'supplier_id', 'price_list_section_id',
        'sku', 'slug', 'barcode', 'name', 'name_ar', 'name_ku', 'name_zh', 'description',
        'product_category_id', 'brand_id', 'product_group_id', 'default_supplier_id',
        'unit_id', 'purchase_unit_id', 'pack_size', 'attributes',
        'weight_kg', 'volume_cbm', 'carton_length_cm', 'carton_width_cm', 'carton_height_cm',
        'hs_code', 'duty_rate', 'country_of_origin',
        'cost_price', 'selling_price', 'selling_price_currency', 'min_selling_price',
        'target_margin_percent', 'average_cost', 'last_landed_cost',
        'tax_rate', 'reorder_level', 'reorder_quantity', 'lead_time_days',
        'track_stock', 'is_active', 'is_sellable', 'is_purchasable', 'status', 'internal_notes',
        // Lithium goods cannot fly as ordinary cargo. The column and the warning
        // that reads it both existed; this was missing, so nothing could ever
        // set it and the warning could never fire.
        'contains_battery',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'pack_size' => 'decimal:4',
            'weight_kg' => 'decimal:4',
            'volume_cbm' => 'decimal:6',
            'duty_rate' => 'decimal:2',
            'cost_price' => 'decimal:4',
            'selling_price' => 'decimal:4',
            'min_selling_price' => 'decimal:4',
            'average_cost' => 'decimal:4',
            'last_landed_cost' => 'decimal:4',
            'target_margin_percent' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'reorder_level' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'track_stock' => 'boolean',
            'contains_battery' => 'boolean',
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        /*
         * Nobody adding a crystal colour wants to invent a code for it, but
         * imports, barcode lookups and reports all still key on one. So it is
         * derived rather than asked for, and only when it was left blank —
         * typing your own supplier code still wins.
         */
        static::saving(function (self $product) {
            if (blank($product->sku)) {
                $product->sku = $product->generateSku();
            }

            // Purchasing, imports and supplier comparison all read
            // default_supplier_id. The form no longer asks twice, so the tree's
            // owner fills it in — otherwise adding a product here would quietly
            // drop out of every screen that reorders from a supplier.
            $product->default_supplier_id ??= $product->supplier_id;
        });
    }

    /** The branch this hangs from. Null means it is a top-level shelf. */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** Whose catalogue this whole branch belongs to. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** Crystals, Textile, Packaging or Furniture. */
    public function section(): BelongsTo
    {
        return $this->belongsTo(PriceListSection::class, 'price_list_section_id');
    }

    /** The sizes it is sold in, each with its own cost. */
    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(ProductGroup::class, 'product_group_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    /** Every supplier who sells this, with their own SKU and price. */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /** What you sell it for, per customer type. Cost lives on supplierProducts. */
    public function sellPrices(): HasMany
    {
        return $this->hasMany(ProductSellPrice::class);
    }

    public function dealLines(): HasMany
    {
        return $this->hasMany(DealLine::class);
    }

    /**
     * The selling price for a customer type, falling back to the base price.
     *
     * A null customer type is the default price. Quantity breaks apply: the
     * largest break at or below the quantity wins, so 500 pieces can sell
     * cheaper per piece than 50.
     */
    public function sellPriceFor(?int $customerTypeId = null, float $quantity = 1): ?ProductSellPrice
    {
        return $this->sellPrices
            ->filter(fn (ProductSellPrice $p) => (float) $p->min_quantity <= $quantity)
            ->filter(fn (ProductSellPrice $p) => $p->customer_type_id === $customerTypeId
                || $p->customer_type_id === null)
            // An exact customer-type match beats the shared default.
            ->sortByDesc(fn (ProductSellPrice $p) => [
                $p->customer_type_id === $customerTypeId ? 1 : 0,
                (float) $p->min_quantity,
            ])
            ->first();
    }

    /** Margin on the price list alone — a real deal's margin comes from its lines. */
    public function grossMargin(): float
    {
        return (float) $this->selling_price - (float) $this->cost_price;
    }

    public function marginPercent(): float
    {
        $price = (float) $this->selling_price;

        return $price > 0 ? round($this->grossMargin() / $price * 100, 2) : 0.0;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Top of a supplier's tree — the shelves you see before opening anything. */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeInSection(Builder $query, int $sectionId): Builder
    {
        return $query->where('price_list_section_id', $sectionId);
    }

    /**
     * A shelf holds other products; a priced item holds sizes.
     *
     * Nothing forbids a product from having both, and the screens cope, but the
     * distinction is what decides whether a row is worth showing on a price
     * list at all — you cannot quote "Flat Crystal", only a size of a P13.
     */
    public function isShelf(): bool
    {
        return $this->children()->exists();
    }

    /** ['Crystal', 'Flat Crystal', 'P13'] — the trail from the top, for headings. */
    public function pathNames(): array
    {
        $names = [];

        for ($node = $this; $node !== null; $node = $node->parent) {
            array_unshift($names, $node->name);
        }

        return $names;
    }

    public function pathLabel(string $separator = ' › '): string
    {
        return implode($separator, $this->pathNames());
    }

    /**
     * Its own id and everything beneath it.
     *
     * A parent picker has to exclude these: hanging Crystal under its own P13
     * would build a loop that every walk up the tree then runs forever on.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->getKey()];

        foreach ($this->children as $child) {
            $ids = [...$ids, ...$child->descendantIds()];
        }

        return $ids;
    }

    /**
     * A readable code built from the section and the name.
     *
     * Uniqueness is settled by counting up rather than by random noise, so the
     * second P13 in a section is P13-1 and still means something to read.
     */
    protected function generateSku(): string
    {
        $prefix = Str::of($this->section?->code ?: 'PRD')
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '')
            ->limit(4, '');

        $base = Str::of($this->name ?: Str::random(6))
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '-')
            ->trim('-')
            ->limit(24, '');

        $candidate = "{$prefix}-{$base}";
        $suffix = 1;

        while (static::withTrashed()
            ->where('sku', $candidate)
            ->when($this->exists, fn (Builder $q) => $q->whereKeyNot($this->getKey()))
            ->exists()
        ) {
            $candidate = "{$prefix}-{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}
