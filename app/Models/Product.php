<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = [
        'sku', 'slug', 'barcode', 'name', 'name_ar', 'name_ku', 'name_zh', 'description',
        'product_category_id', 'brand_id', 'product_group_id', 'default_supplier_id',
        'unit_id', 'purchase_unit_id', 'pack_size', 'attributes',
        'weight_kg', 'volume_cbm', 'carton_length_cm', 'carton_width_cm', 'carton_height_cm',
        'hs_code', 'duty_rate', 'country_of_origin',
        'cost_price', 'selling_price', 'selling_price_currency', 'min_selling_price',
        'target_margin_percent', 'average_cost', 'last_landed_cost',
        'tax_rate', 'reorder_level', 'reorder_quantity', 'lead_time_days',
        'track_stock', 'is_active', 'is_sellable', 'is_purchasable', 'status', 'internal_notes',
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
            'is_active' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
        ];
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
}
