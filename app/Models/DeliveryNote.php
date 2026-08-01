<?php

namespace App\Models;

use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryNote extends Model
{
    use HasDocumentNumber;

    protected $fillable = [
        'number', 'sales_order_id', 'customer_id', 'warehouse_id', 'delivery_date',
        'status', 'posted_at', 'shipping_address', 'carrier', 'tracking_number',
        'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'delivery_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public static function documentPrefix(): string
    {
        return 'DN';
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryNoteItem::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
