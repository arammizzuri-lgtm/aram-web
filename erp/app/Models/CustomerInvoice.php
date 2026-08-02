<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * What the customer receives. Never carries a cost, in print or in the row.
 *
 * A snapshot, not a live view of the deal: lines are copied at issue. Editing
 * the deal afterwards cannot change an invoice already sent, because a customer
 * holding a printed copy and you looking at a screen must never see different
 * numbers. Corrections are made by cancelling and re-issuing, which leaves a
 * visible trail instead of a silent edit.
 *
 * One deal produces more than one of these: the goods now, the shipping once
 * the freight bill arrives.
 */
class CustomerInvoice extends Model
{
    use SoftDeletes;

    public const TYPES = [
        'goods' => 'Goods',
        'shipping' => 'Shipping',
        'other' => 'Other',
    ];

    protected $fillable = [
        'deal_id', 'customer_id', 'number', 'type', 'status',
        'currency', 'exchange_rate', 'subtotal', 'discount', 'total', 'total_base',
        'amount_paid', 'invoice_date', 'due_date', 'language', 'notes',
        'issued_at', 'cancelled_at', 'cancellation_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exchange_rate' => 'decimal:6',
            'subtotal' => 'decimal:4',
            'discount' => 'decimal:4',
            'total' => 'decimal:4',
            'total_base' => 'decimal:4',
            'amount_paid' => 'decimal:4',
            'invoice_date' => 'date',
            'due_date' => 'date',
            'issued_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CustomerInvoiceLine::class)->orderBy('display_order');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(CustomerPaymentAllocation::class);
    }

    /** Sorani reads right to left, so the document is mirrored, not translated. */
    public function isRightToLeft(): bool
    {
        return $this->language === 'ckb';
    }

    public function isIssued(): bool
    {
        return $this->issued_at !== null && $this->status !== 'cancelled';
    }

    public function paidBase(): Money
    {
        return Money::of($this->allocations->sum('base_amount'), 'USD');
    }

    public function outstandingBase(): Money
    {
        return Money::of($this->total_base, 'USD')->minus($this->paidBase());
    }

    public function isPaid(): bool
    {
        return ! $this->outstandingBase()->isPositive();
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereNot('status', 'cancelled')->whereNot('status', 'draft');
    }
}
