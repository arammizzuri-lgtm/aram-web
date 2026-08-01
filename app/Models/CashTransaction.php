<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Append-only cash ledger. Same discipline as stock_movements: never edited. */
class CashTransaction extends Model
{
    protected $fillable = [
        'bank_account_id', 'transaction_date', 'direction', 'amount', 'currency',
        'exchange_rate', 'base_amount', 'reference_type', 'reference_id',
        'description', 'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount' => 'decimal:4',
            'exchange_rate' => 'decimal:8',
            'base_amount' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
