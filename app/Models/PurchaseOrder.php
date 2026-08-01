<?php

namespace App\Models;

use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\HasDocumentNumber;
use App\Models\Concerns\HasLineItems;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrder extends Model
{
    use HasDocumentNumber, HasLineItems;

    protected $fillable = [
        'number', 'supplier_id', 'warehouse_id', 'order_date', 'expected_date',
        'status', 'subtotal', 'discount_total', 'tax_total', 'total', 'notes', 'created_by',
        'currency', 'exchange_rate', 'base_total', 'incoterm', 'supplier_reference',
        'deposit_percent', 'deposit_due_date', 'balance_due_date', 'expected_ship_date',
        'port_of_loading', 'payment_terms_days', 'approved_by', 'approved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'expected_ship_date' => 'date',
            'deposit_due_date' => 'date',
            'balance_due_date' => 'date',
            'approved_at' => 'datetime',
            'closed_at' => 'datetime',
            'status' => PurchaseOrderStatus::class,
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'total' => 'decimal:2',
            'base_total' => 'decimal:4',
            'deposit_percent' => 'decimal:2',
        ];
    }

    /** How much of the ordered quantity has physically arrived. */
    public function receivedPercent(): float
    {
        $ordered = (float) $this->items()->sum('quantity');

        if ($ordered <= 0) {
            return 0.0;
        }

        return round((float) $this->items()->sum('received_quantity') / $ordered * 100, 1);
    }

    public static function documentPrefix(): string
    {
        return 'PO';
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
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(SupplierBill::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isFullyReceived(): bool
    {
        return $this->items()->whereColumn('received_quantity', '<', 'quantity')->doesntExist();
    }

    public function hasAnyReceipt(): bool
    {
        return $this->items()->where('received_quantity', '>', 0)->exists();
    }

    /** Moves the order between open states based on how much has arrived. */
    public function syncReceiptStatus(): void
    {
        if (in_array($this->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Cancelled], true)) {
            return;
        }

        $this->status = match (true) {
            $this->isFullyReceived() => PurchaseOrderStatus::Received,
            $this->hasAnyReceipt() => PurchaseOrderStatus::PartiallyReceived,
            default => PurchaseOrderStatus::Sent,
        };

        $this->saveQuietly();
    }
}
