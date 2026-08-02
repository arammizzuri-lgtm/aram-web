<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A copied line, not a reference to one.
 *
 * The duplication is the point: a reference would let a later edit rewrite a
 * document already in the customer's hands. Cost is absent by design — this
 * backs a customer document and must not carry purchase prices even in the
 * database.
 */
class CustomerInvoiceLine extends Model
{
    protected $fillable = [
        'customer_invoice_id', 'deal_line_id', 'description', 'description_ku',
        'specification', 'quantity', 'unit', 'unit_price', 'line_total',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'line_total' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(CustomerInvoice::class, 'customer_invoice_id');
    }

    public function dealLine(): BelongsTo
    {
        return $this->belongsTo(DealLine::class, 'deal_line_id');
    }
}
