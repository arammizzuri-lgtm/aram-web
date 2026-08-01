<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use App\Models\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    use HasDocumentNumber, HasLineItems;

    protected $fillable = [
        'number', 'customer_id', 'quotation_id', 'warehouse_id', 'order_date',
        'delivery_date', 'status', 'currency', 'exchange_rate', 'base_total',
        'price_tier_id', 'sales_rep_id', 'subtotal', 'discount_total', 'tax_total', 'total',
        'shipping_address', 'delivery_address', 'is_reserved', 'reserved_at',
        'credit_approved_by', 'credit_approved_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'delivery_date' => 'date',
            'reserved_at' => 'datetime',
            'credit_approved_at' => 'datetime',
            'is_reserved' => 'boolean',
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
        return 'SO';
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function priceTier(): BelongsTo
    {
        return $this->belongsTo(PriceTier::class, 'price_tier_id');
    }

    public function salesRep(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sales_rep_id');
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function deliveryNotes(): HasMany
    {
        return $this->hasMany(DeliveryNote::class);
    }

    /** Whether confirming this order would push the customer past their credit limit. */
    public function breachesCreditLimit(): bool
    {
        $customer = $this->customer;

        if ($customer === null || (float) $customer->credit_limit <= 0) {
            return false;
        }

        return $customer->outstandingBalance() + (float) $this->total > (float) $customer->credit_limit;
    }
}
