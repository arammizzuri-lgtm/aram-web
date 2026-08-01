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

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /** Quantity on hand across every warehouse, or one warehouse when given. */
    public function stockOnHand(?int $warehouseId = null): float
    {
        return (float) $this->stockLevels()
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    /** What Sales is allowed to promise: on hand minus what is already committed. */
    public function stockAvailable(?int $warehouseId = null): float
    {
        $levels = $this->stockLevels()
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId));

        return (float) $levels->sum('quantity') - (float) $levels->sum('reserved_quantity');
    }

    public function stockIncoming(?int $warehouseId = null): float
    {
        return (float) $this->stockLevels()
            ->when($warehouseId, fn (Builder $query) => $query->where('warehouse_id', $warehouseId))
            ->sum('incoming_quantity');
    }

    /**
     * Margin against the true landed cost, not the supplier's invoice price.
     *
     * Falls back to cost_price only when nothing has been received yet.
     */
    public function grossMargin(): float
    {
        return (float) $this->selling_price - $this->effectiveCost();
    }

    public function marginPercent(): float
    {
        $price = (float) $this->selling_price;

        return $price > 0 ? round($this->grossMargin() / $price * 100, 2) : 0.0;
    }

    public function effectiveCost(): float
    {
        $average = (float) $this->average_cost;

        return $average > 0 ? $average : (float) $this->cost_price;
    }

    /** The duty rate that applies, falling back to the category default. */
    public function effectiveDutyRate(): float
    {
        return (float) ($this->duty_rate ?? $this->category?->default_duty_rate ?? 0);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Products tracked for stock whose total on hand sits at or below the reorder level. */
    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('track_stock', true)
            ->where('reorder_level', '>', 0)
            ->whereRaw(
                '(select coalesce(sum(quantity), 0) from stock_levels where stock_levels.product_id = products.id) <= products.reorder_level'
            );
    }
}
