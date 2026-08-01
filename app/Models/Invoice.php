<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\HasDocumentNumber;
use App\Models\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasDocumentNumber, HasLineItems;

    protected $fillable = [
        'number', 'customer_id', 'sales_order_id', 'invoice_date', 'due_date', 'status',
        'invoice_type', 'currency', 'exchange_rate', 'base_total', 'price_tier_id', 'sales_rep_id',
        'subtotal', 'discount_total', 'tax_total', 'total', 'amount_paid',
        'cogs_total_base', 'gross_profit_base', 'margin_percent',
        'notes', 'terms', 'posted_at', 'related_invoice_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'posted_at' => 'datetime',
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'base_total' => 'decimal:4',
            'cogs_total_base' => 'decimal:4',
            'gross_profit_base' => 'decimal:4',
            'margin_percent' => 'decimal:2',
        ];
    }

    public static function documentPrefix(): string
    {
        return 'INV';
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function amountDue(): float
    {
        return round((float) $this->total - (float) $this->amount_paid, 2);
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->isPast()
            && $this->amountDue() > 0.005;
    }

    /** Days past due, for the aging buckets. */
    public function daysOverdue(): int
    {
        return $this->isOverdue() ? (int) $this->due_date->diffInDays(now()) : 0;
    }

    public function scopeOutstanding(Builder $query): Builder
    {
        return $query->whereNotIn('status', [DocumentStatus::Cancelled->value])
            ->whereColumn('amount_paid', '<', 'total');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->outstanding()->whereDate('due_date', '<', today());
    }
}
