<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Money in from a customer.
 *
 * Belongs to the customer, not to an invoice. That is the whole design: an
 * advance paid before the deal exists has somewhere to sit, and money is safe
 * the moment it is recorded rather than waiting on bookkeeping.
 *
 * Whatever is not matched to an invoice is the customer's credit balance.
 */
class CustomerPayment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id', 'number', 'amount', 'currency', 'exchange_rate',
        'base_amount', 'direction', 'method', 'reference', 'paid_at',
        'notes', 'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:6',
            'base_amount' => 'decimal:4',
            'paid_at' => 'date',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isRefund(): bool
    {
        return $this->direction === 'refund';
    }

    /** Still sitting as credit, matched to nothing. */
    public function unallocatedBase(): Money
    {
        return Money::of($this->base_amount, 'USD')
            ->minus(Money::of($this->allocations->sum('base_amount'), 'USD'));
    }

    public function isFullyAllocated(): bool
    {
        return ! $this->unallocatedBase()->isPositive();
    }
}
