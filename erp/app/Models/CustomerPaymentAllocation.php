<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Which invoices a payment covers.
 *
 * A payment may cover several invoices, or only part of one, or none at all.
 * The unmatched remainder is never lost — it is credit, which is how "pays for
 * the goods now, the shipping later" needs no special handling.
 *
 * `was_suggested` records whether the one-click suggestion was accepted or the
 * split was decided by hand, so a later question about why money landed where
 * it did has an answer.
 */
class CustomerPaymentAllocation extends Model
{
    protected $fillable = [
        'customer_payment_id', 'customer_invoice_id', 'amount', 'base_amount',
        'was_suggested',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:4',
            'base_amount' => 'decimal:4',
            'was_suggested' => 'boolean',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(CustomerPayment::class, 'customer_payment_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }
}
