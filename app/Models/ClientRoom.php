<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * A client's private room for their project: /room/{token}, opened with a
 * 4-digit PIN that their phone then remembers for 90 days.
 *
 * Inside: every progress update of the project (including those marked
 * private), the drawings sent for them to approve or send back, the payment
 * stages, and a conversation under each update. A project can have more than
 * one room — one for each owner — and each room's conversation is its own.
 *
 * The PIN is kept encrypted, not hashed: the studio has to be able to read it
 * out to the client.
 */
class ClientRoom extends Model
{
    /** How long a phone stays let in after the PIN. */
    public const REMEMBER_DAYS = 90;

    /** Wrong PINs allowed per room before it waits a while. */
    public const PIN_TRIES = 5;

    public const CURRENCIES = ['USD' => 'US dollars ($)', 'IQD' => 'Iraqi dinars (IQD)'];

    protected $fillable = [
        'project_id', 'client_name', 'client_phone', 'token', 'pin', 'lang', 'currency', 'note', 'is_open', 'last_seen_at',
    ];

    protected $attributes = [
        'lang' => 'ku',
        'currency' => 'USD',
        'is_open' => true,
    ];

    protected $hidden = ['token', 'pin'];

    protected $casts = [
        'pin' => 'encrypted',
        'is_open' => 'boolean',
        'last_seen_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // a new room comes with its own link and PIN
        static::creating(function (ClientRoom $room) {
            $room->token = $room->token ?: self::newToken();
            $room->pin = $room->pin ?: self::newPin();
        });

        // through the models, so their files on the private disk go too
        static::deleting(function (ClientRoom $room) {
            $room->drawings()->get()->each->delete();
            $room->payments()->get()->each->delete();
        });
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public static function newPin(): string
    {
        return str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function drawings(): HasMany
    {
        return $this->hasMany(RoomDrawing::class)->latest()->orderByDesc('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(RoomPayment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(RoomComment::class)->oldest()->orderBy('id');
    }

    /** The room's address, in the client's language unless told otherwise. */
    public function url(?string $lang = null): string
    {
        return site_url('/room/'.$this->token, $lang ?? $this->lang);
    }

    /** Every progress update of its project — public and private — newest first. */
    public function updates(): Collection
    {
        return $this->project ? $this->project->updates()->get() : collect();
    }

    /**
     * What a phone keeps to say it has been let in: tied to this link and
     * this PIN, so a new link or a new PIN locks every phone out again.
     */
    public function accessKey(): string
    {
        return hash_hmac('sha256', $this->id.'|'.$this->token.'|'.$this->pin, (string) config('app.key'));
    }

    public function accessCookie(): string
    {
        return 'room_'.$this->id;
    }

    /**
     * Whether $pin is this room's. A Kurdish phone keyboard types ٤٥٦٧ (or
     * the Persian ۴۵۶۷) for 4567, so those count as the same digits.
     */
    public function pinMatches(string $pin): bool
    {
        $pin = strtr($pin, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);

        return hash_equals((string) $this->pin, (string) preg_replace('/\D/', '', $pin));
    }

    /** Client comments and drawing decisions the studio has not read yet. */
    public function unseenCount(): int
    {
        return $this->comments()->where('from_studio', false)->whereNull('seen_at')->count()
            + $this->drawings()->where('status', '!=', RoomDrawing::PENDING)->whereNull('seen_at')->count();
    }

    /** Mark everything the client has done here as read by the studio. */
    public function markSeen(): void
    {
        $this->comments()->where('from_studio', false)->whereNull('seen_at')->update(['seen_at' => now()]);
        $this->drawings()->where('status', '!=', RoomDrawing::PENDING)->whereNull('seen_at')->update(['seen_at' => now()]);
    }

    /** Rooms with something the studio has not read yet. */
    public function scopeWithUnseen(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q
            ->whereHas('comments', fn ($c) => $c->where('from_studio', false)->whereNull('seen_at'))
            ->orWhereHas('drawings', fn ($d) => $d->where('status', '!=', RoomDrawing::PENDING)->whereNull('seen_at')));
    }

    /** "$12,500" or "12,500,000 IQD" (دینار in Kurdish). */
    public function money(float|int|string|null $amount, ?string $lang = null): string
    {
        $amount = (float) $amount;
        $decimals = $this->currency === 'USD' && floor($amount) != $amount ? 2 : 0;
        $n = number_format($amount, $decimals);

        if ($this->currency === 'IQD') {
            return $n.' '.(($lang ?? site_lang()) === 'ku' ? 'دینار' : 'IQD');
        }

        return '$'.$n;
    }

    /** @return array{total: float, paid: float, percent: int} */
    public function paymentTotals(): array
    {
        $payments = $this->relationLoaded('payments') ? $this->payments : $this->payments()->get();
        $total = (float) $payments->sum('amount');
        $paid = (float) $payments->filter->isPaid()->sum('amount');

        return ['total' => $total, 'paid' => $paid, 'percent' => $total > 0 ? (int) round($paid / $total * 100) : 0];
    }

    /** A WhatsApp chat with the client, opening with $text — or null without a phone. */
    public function whatsappUrl(?string $text = null): ?string
    {
        $digits = phone_digits($this->client_phone);

        return $digits ? 'https://wa.me/'.$digits.(filled($text) ? '?text='.rawurlencode($text) : '') : null;
    }
}
