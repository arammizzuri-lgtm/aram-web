<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
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
 *
 * A match is only worth anything while both ends of it are there — see the
 * scope below.
 */
class CustomerPaymentAllocation extends Model
{
    /**
     * A match counts only while the payment and the invoice both do.
     *
     * Deleting either used to leave this row behind, still counting. The
     * dashboard read the invoices — which respect deletion — against the
     * matches, which did not, and reported that a customer with no invoices at
     * all was owed four thousand dollars.
     *
     * Not deleted along with them, on purpose: deleting is reversible here, and
     * a payment restored six weeks later should come back matched to the same
     * invoices it always was. So the rows stay and simply stop counting, which
     * is the same thing soft deletion does everywhere else.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('bothEndsPresent', function (Builder $query): void {
            $query->whereHas('payment')->whereHas('invoice');
        });
    }

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
