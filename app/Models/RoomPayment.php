<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One stage of a client's payments — "Concept design", "Permit drawings"… —
 * what it comes to, when it is due, when it was paid, and the receipt if the
 * studio attached one (private disk). Nothing is paid through the site: this
 * is the studio's record, shown to the client.
 */
class RoomPayment extends Model
{
    public const DISK = 'local';

    protected $fillable = [
        'client_room_id', 'label', 'amount', 'due_on', 'paid_on', 'receipt_path', 'receipt_name', 'sort_order',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_on' => 'date',
        'paid_on' => 'date',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::updated(function (RoomPayment $p) {
            $old = $p->getOriginal('receipt_path');
            if ($p->wasChanged('receipt_path') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
        static::deleted(fn (RoomPayment $p) => $p->receipt_path && Storage::disk(self::DISK)->delete($p->receipt_path));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    public function isPaid(): bool
    {
        return $this->paid_on !== null;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_on !== null && $this->due_on->lt(today());
    }

    /** paid | overdue | due */
    public function state(): string
    {
        return $this->isPaid() ? 'paid' : ($this->isOverdue() ? 'overdue' : 'due');
    }

    public function receiptExists(): bool
    {
        return filled($this->receipt_path) && Storage::disk(self::DISK)->exists($this->receipt_path);
    }
}
