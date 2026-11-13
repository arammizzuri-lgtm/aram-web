<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A document on a client room's shelf (private disk), in one of the fixed
 * groups. The studio adds them, or the client does (from_client). The studio
 * can ask the client to confirm they have read one (read_at) and give it an
 * expiry date; it is e-mailed 30 days before (App\Support\DocumentReminders).
 * first_opened_at: when the client first opened it.
 * seen_at: null while the studio has not seen the client's upload or reading.
 */
class RoomDocument extends Model
{
    public const DISK = 'local';

    /** The shelf's groups, in order, with their admin labels (the room's are site texts room_doc_{group}). */
    public const GROUPS = ['contract' => 'Contract', 'permits' => 'Permits', 'invoices' => 'Invoices', 'other' => 'Other'];

    public const REMIND_DAYS = 30;

    protected $fillable = [
        'client_room_id', 'group', 'title', 'file_path', 'file_name', 'from_client', 'needs_reading', 'read_at', 'read_ip',
        'expires_on', 'reminded_at', 'first_opened_at', 'seen_at',
    ];

    protected $attributes = [
        'group' => 'other',
    ];

    protected $casts = [
        'from_client' => 'boolean',
        'needs_reading' => 'boolean',
        'read_at' => 'datetime',
        'expires_on' => 'date',
        'reminded_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // a new expiry date (a renewed permit) is reminded of again
        static::updating(function (RoomDocument $d) {
            if ($d->isDirty('expires_on')) {
                $d->reminded_at = null;
            }
        });
        static::updated(function (RoomDocument $d) {
            $old = $d->getOriginal('file_path');
            if ($d->wasChanged('file_path') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
        static::deleted(fn (RoomDocument $d) => $d->file_path && Storage::disk(self::DISK)->delete($d->file_path));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    /** Expiring within REMIND_DAYS (or expired), not yet reminded of, in an open room. */
    public function scopeDueForReminder(Builder $query): Builder
    {
        return $query->whereNotNull('expires_on')->whereNull('reminded_at')
            ->whereDate('expires_on', '<=', today()->addDays(self::REMIND_DAYS))
            ->whereHas('room', fn ($room) => $room->where('is_open', true));
    }

    public function fileExists(): bool
    {
        return filled($this->file_path) && Storage::disk(self::DISK)->exists($this->file_path);
    }

    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp)$/i', (string) $this->file_path);
    }

    public function isExpired(): bool
    {
        return $this->expires_on !== null && $this->expires_on->lt(today());
    }

    public function expiresSoon(): bool
    {
        return $this->expires_on !== null && ! $this->isExpired() && $this->expires_on->lte(today()->addDays(self::REMIND_DAYS));
    }
}
