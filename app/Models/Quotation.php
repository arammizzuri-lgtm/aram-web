<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use App\Models\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quotation extends Model
{
    use HasDocumentNumber, HasLineItems;

    protected $fillable = [
        'number', 'customer_id', 'quote_date', 'valid_until', 'status',
        'currency', 'exchange_rate', 'base_total', 'price_tier_id', 'sales_rep_id',
        'subtotal', 'discount_total', 'tax_total', 'total', 'notes', 'terms', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'quote_date' => 'date',
            'valid_until' => 'date',
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'base_total' => 'decimal:4',
        ];
    }

    public static function documentPrefix(): string
    {
        return 'QUO';
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
