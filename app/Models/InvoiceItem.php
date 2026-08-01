<?php

namespace App\Models;

use App\Models\Concerns\CalculatesLineTotal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use CalculatesLineTotal;

    protected $fillable = [
        'invoice_id', 'sales_order_item_id', 'product_id', 'description',
        'quantity', 'unit_price', 'discount_rate', 'tax_rate', 'line_total',
        'unit_cost_base', 'shipment_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:4',
            'discount_rate' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'line_total' => 'decimal:2',
            // COGS frozen when the invoice was posted. A later revaluation must
            // not rewrite the margin on an invoice already sent to a customer.
            'unit_cost_base' => 'decimal:4',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function costOfGoods(): float
    {
        return round((float) $this->quantity * (float) $this->unit_cost_base, 4);
    }

    public function grossProfit(): float
    {
        return round((float) $this->line_total - $this->costOfGoods(), 4);
    }
}
