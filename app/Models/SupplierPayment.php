<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupplierPayment extends Model
{
    use HasDocumentNumber;

    protected $fillable = [
        'number', 'supplier_id', 'supplier_bill_id', 'payment_date', 'amount', 'method',
        'reference', 'currency', 'exchange_rate', 'base_amount', 'fx_gain_loss',
        'bank_account_id', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:4',
            // The rate on the payment date differs from the rate frozen on the
            // order; that gap is real money and is reported, not absorbed.
            'fx_gain_loss' => 'decimal:4',
        ];
    }

    public static function documentPrefix(): string
    {
        return 'SPY';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(SupplierBill::class, 'supplier_bill_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(SupplierPaymentAllocation::class);
    }
}
