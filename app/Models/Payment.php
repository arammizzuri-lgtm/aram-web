<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use HasDocumentNumber;

    protected $fillable = [
        'number', 'customer_id', 'invoice_id', 'payment_date', 'amount', 'method',
        'reference', 'currency', 'exchange_rate', 'base_amount', 'unallocated_amount',
        'fx_gain_loss', 'bank_account_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:4',
            'unallocated_amount' => 'decimal:4',
            'fx_gain_loss' => 'decimal:4',
        ];
    }

    public static function documentPrefix(): string
    {
        return 'PAY';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** One payment can settle several invoices — the normal case in wholesale. */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function allocatedAmount(): float
    {
        return round((float) $this->allocations()->sum('amount'), 2);
    }

    /** Money received but not yet applied to an invoice — customer credit. */
    public function unallocated(): float
    {
        return round((float) $this->amount - $this->allocatedAmount(), 2);
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency ?? 'USD');
    }
}
