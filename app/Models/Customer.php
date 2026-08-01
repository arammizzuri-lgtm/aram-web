<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    protected $fillable = [
        'code', 'name', 'name_ar', 'name_ku', 'contact_person', 'email', 'phone',
        'whatsapp', 'billing_address', 'shipping_address', 'city', 'area',
        'tax_number', 'price_tier_id', 'credit_limit', 'credit_limit_currency',
        'default_currency', 'payment_terms_days', 'opening_balance',
        'is_blocked', 'blocked_reason', 'sales_rep_id', 'rating', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'credit_limit' => 'decimal:2',
            'payment_terms_days' => 'integer',
            'opening_balance' => 'decimal:2',
            'is_blocked' => 'boolean',
            'rating' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class, 'price_tier_id');
    }

    /** How much of the credit limit is used, for the usage bar on the profile. */
    public function creditUsedPercent(): float
    {
        $limit = (float) $this->credit_limit;

        return $limit > 0 ? round($this->outstandingBalance() / $limit * 100, 1) : 0.0;
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /** Opening balance plus everything invoiced, less everything received. */
    public function outstandingBalance(): float
    {
        $invoiced = (float) $this->invoices()->whereNot('status', 'cancelled')->sum('total');
        $paid = (float) $this->invoices()->whereNot('status', 'cancelled')->sum('amount_paid');

        return round((float) $this->opening_balance + $invoiced - $paid, 2);
    }

    /** How much more this customer may owe before hitting their credit limit. */
    public function availableCredit(): float
    {
        return round((float) $this->credit_limit - $this->outstandingBalance(), 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
