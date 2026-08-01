<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use HasDocumentNumber;

    protected $fillable = [
        'number', 'expense_category_id', 'expense_date', 'description', 'supplier_id',
        'vendor_name', 'amount', 'currency', 'exchange_rate', 'base_amount',
        'payment_method', 'bank_account_id', 'shipment_id', 'is_allocated_to_shipment',
        'status', 'reference', 'notes', 'created_by', 'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:4',
            'is_allocated_to_shipment' => 'boolean',
            'approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $expense) {
            $expense->base_amount = (float) $expense->amount * (float) $expense->exchange_rate;
        });
    }

    public static function documentPrefix(): string
    {
        return 'EXP';
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** Logistics spend that belongs inside a container's cost, not general overhead. */
    public function scopeAllocatableToShipment(Builder $query): Builder
    {
        return $query->whereHas('category', fn (Builder $q) => $q->where('is_shipment_allocatable', true));
    }
}
