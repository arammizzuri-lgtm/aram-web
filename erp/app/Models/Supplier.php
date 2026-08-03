<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'name_zh', 'contact_person', 'email', 'phone', 'whatsapp',
        'wechat_id', 'address', 'country', 'city', 'website', 'tax_number',
        'default_currency', 'default_incoterm', 'port_of_loading',
        'average_lead_time_days', 'deposit_percent', 'rating', 'bank_details',
        'payment_terms_days', 'opening_balance', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'payment_terms_days' => 'integer',
            'average_lead_time_days' => 'integer',
            'deposit_percent' => 'decimal:2',
            'rating' => 'integer',
            'bank_details' => 'array',
            'opening_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class);
    }

    /** The catalogue this supplier sells, under their own SKUs. */
    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    /** This supplier's whole catalogue tree, every section of it. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** This supplier's own crystal colour chart, in the Price Lists module. */
    public function crystalProducts(): HasMany
    {
        return $this->hasMany(CrystalProduct::class);
    }

    public function crystalPrices(): HasMany
    {
        return $this->hasMany(CrystalPrice::class);
    }

    /** Textile, packaging and furniture lines this supplier quotes. */
    public function catalogueItems(): HasMany
    {
        return $this->hasMany(CatalogueItem::class);
    }

    /** This supplier's side of the deals you have bought for. */
    public function purchases(): HasMany
    {
        return $this->hasMany(DealPurchase::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /**
     * What you still owe this supplier, in USD.
     *
     * Uses base_amount rather than actual_cost_base on the paid side: what you
     * owe is settled by what the supplier received, not by what the transfer
     * cost you. The exchange house's cut is your expense, not their credit.
     */
    public function outstandingBalance(): float
    {
        // cost_total_base is already the line total, so no multiplication here.
        $ordered = (float) DealLine::query()
            ->whereIn('deal_purchase_id', $this->purchases()->whereNot('status', 'cancelled')->select('id'))
            ->sum('cost_total_base');

        $paid = (float) $this->payments()->sum('base_amount');

        return round((float) $this->opening_balance + $ordered - $paid, 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
