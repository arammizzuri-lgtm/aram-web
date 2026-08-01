<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
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

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** Opening balance plus everything billed, less everything paid. */
    public function outstandingBalance(): float
    {
        $billed = (float) $this->bills()->whereNot('status', 'cancelled')->sum('total');
        $paid = (float) $this->bills()->whereNot('status', 'cancelled')->sum('amount_paid');

        return round((float) $this->opening_balance + $billed - $paid, 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
