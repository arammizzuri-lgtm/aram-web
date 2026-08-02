<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'name', 'name_ar', 'name_ku', 'contact_person', 'email', 'phone',
        'whatsapp', 'billing_address', 'shipping_address', 'city', 'area',
        'tax_number', 'customer_type_id', 'document_language',
        'credit_limit', 'credit_limit_currency',
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

    /** Wholesale, regular — decides which selling price a product uses. */
    public function customerType(): BelongsTo
    {
        return $this->belongsTo(CustomerType::class);
    }

    /** How much of the credit limit is used, for the usage bar on the profile. */
    public function creditUsedPercent(): float
    {
        $limit = (float) $this->credit_limit;

        return $limit > 0 ? round($this->outstandingBalance() / $limit * 100, 1) : 0.0;
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(CustomerInvoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CustomerPayment::class);
    }

    /**
     * What this customer owes, in USD.
     *
     * Everything invoiced less everything received — not less what has been
     * *matched* to invoices. An advance paid before the deal existed is real
     * money in your hand and must reduce what they owe, even though there was
     * nothing to match it against at the time.
     */
    public function outstandingBalance(): float
    {
        $invoiced = (float) $this->invoices()
            ->whereNot('status', 'cancelled')
            ->sum('total_base');

        $received = (float) $this->payments()->sum('base_amount');

        return round((float) $this->opening_balance + $invoiced - $received, 2);
    }

    /**
     * Money held that is not yet matched to any invoice.
     *
     * This is how an advance payment and a refund owed back to the customer
     * are the same thing seen from two sides, rather than two features.
     */
    public function unallocatedCredit(): float
    {
        $received = (float) $this->payments()->sum('base_amount');

        $matched = (float) CustomerPaymentAllocation::query()
            ->whereIn('customer_payment_id', $this->payments()->select('id'))
            ->sum('base_amount');

        return round($received - $matched, 2);
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
