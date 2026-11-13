<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One finish to choose in a client room — "Kitchen worktop" — and its
 * options (RoomChoiceOption). The client picks one, with a note if they
 * like; the pick is final and dated, like a drawing's approval, until the
 * studio reopens it. An option's extra cost becomes a payment stage.
 *
 * seen_at: null while the studio has not read the client's pick.
 */
class RoomChoice extends Model
{
    public const PENDING = 'pending';

    public const CHOSEN = 'chosen';

    protected $fillable = [
        'client_room_id', 'title', 'note', 'status', 'chosen_option_id', 'client_note',
        'decided_at', 'decided_ip', 'room_payment_id', 'seen_at',
    ];

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected $casts = [
        'decided_at' => 'datetime',
        'seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // through the models, so the option pictures go too
        static::deleting(fn (RoomChoice $c) => $c->options()->get()->each->delete());
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(ClientRoom::class, 'client_room_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(RoomChoiceOption::class)->orderBy('sort_order')->orderBy('id');
    }

    public function chosen(): BelongsTo
    {
        return $this->belongsTo(RoomChoiceOption::class, 'chosen_option_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** What the client was shown: a pick is taken only while it still holds. */
    public function version(): string
    {
        return substr(md5($this->options->map(fn (RoomChoiceOption $o) => $o->id.':'.$o->extra_cost)->join('|')), 0, 12);
    }

    /**
     * Back to choosing: the pick is cleared, and its payment stage removed
     * unless it has been paid. Returns whether a paid stage was kept.
     */
    public function reopen(): bool
    {
        $payment = $this->room_payment_id ? RoomPayment::find($this->room_payment_id) : null;
        $keptPaid = (bool) $payment?->isPaid();
        if ($payment && ! $keptPaid) {
            $payment->delete();
        }
        $this->update([
            'status' => self::PENDING, 'chosen_option_id' => null, 'client_note' => null,
            'decided_at' => null, 'decided_ip' => null, 'room_payment_id' => null, 'seen_at' => now(),
        ]);

        return $keptPaid;
    }
}
