<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Models\Concerns\HasDocumentNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class GoodsReceipt extends Model
{
    use HasDocumentNumber;

    protected $fillable = [
        'number', 'purchase_order_id', 'supplier_id', 'warehouse_id', 'received_date',
        'supplier_reference', 'status', 'posted_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'received_date' => 'date',
            'status' => DocumentStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public static function documentPrefix(): string
    {
        return 'GRN';
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function stockMovements(): MorphMany
    {
        return $this->morphMany(StockMovement::class, 'reference');
    }

    public function isPosted(): bool
    {
        return $this->status === DocumentStatus::Posted;
    }
}
