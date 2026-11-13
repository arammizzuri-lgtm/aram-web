<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A change to the design with a price, in a client room. The studio offers
 * it — or the client asks for it first and the studio prices it — with the
 * days it adds and, if useful, a picture or drawing of it (private disk).
 * Accepted, its price becomes a payment stage of its own; declined, the
 * client says why. Like a drawing's, the decision is the record.
 *
 * seen_at: null while the client's latest step (asking, accepting,
 * declining) has not been read by the studio.
 */
class RoomChange extends Model
{
    public const DISK = 'local';

    public const ASKED = 'asked';

    public const PRICED = 'priced';

    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    protected $fillable = [
        'client_room_id', 'title', 'details', 'note', 'asked_by_client', 'price', 'extra_days', 'file_path', 'file_name',
        'status', 'decline_note', 'decided_at', 'decided_ip', 'room_payment_id', 'seen_at',
    ];

    protected $attributes = [
        'status' => self::PRICED,
    ];

    protected $casts = [
        'asked_by_client' => 'boolean',
        'price' => 'decimal:2',
        'extra_days' => 'integer',
        'decided_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updated(function (RoomChange $c) {
            $old = $c->getOriginal('file_path');
            if ($c->wasChanged('file_path') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
        static::deleted(fn (RoomChange $c) => $c->file_path && Storage::disk(self::DISK)->delete($c->file_path));
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(RoomPayment::class, 'room_payment_id');
    }

    /** Priced, and waiting for the client to accept or decline. */
    public function isOpen(): bool
    {
        return $this->status === self::PRICED;
    }

    public function fileExists(): bool
    {
        return filled($this->file_path) && Storage::disk(self::DISK)->exists($this->file_path);
    }

    public function isImage(): bool
    {
        return (bool) preg_match('/\.(jpe?g|png|webp)$/i', (string) $this->file_path);
    }

    /** @return array<string, string> status => label, for the admin */
    public static function statuses(): array
    {
        return [
            self::ASKED => 'Asked — needs a price',
            self::PRICED => 'Waiting for the client',
            self::ACCEPTED => 'Accepted',
            self::DECLINED => 'Declined',
        ];
    }
}
