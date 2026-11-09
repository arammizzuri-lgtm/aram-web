<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A review link: a private address for one client, in their language, where
 * they write their words about the studio (and may record their voice and add
 * a photo). Works once, and for DAYS days.
 */
class ReviewRequest extends Model
{
    public const DAYS = 90;

    protected $fillable = ['token', 'name', 'lang', 'project_id', 'client_id', 'testimonial_id', 'expires_at', 'used_at'];

    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (ReviewRequest $r) {
            $r->token ??= Str::random(40);
            $r->expires_at ??= now()->addDays(self::DAYS);
            $r->lang = in_array($r->lang, ['en', 'ku', 'ar'], true) ? $r->lang : 'en';
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function testimonial(): BelongsTo
    {
        return $this->belongsTo(Testimonial::class);
    }

    /** The client's page, in their language. */
    public function url(): string
    {
        return site_url('/review/'.$this->token, $this->lang);
    }

    public function isUsed(): bool
    {
        return $this->used_at !== null;
    }

    public function isExpired(): bool
    {
        return ! $this->isUsed() && $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function status(): string
    {
        return $this->isUsed() ? 'Received' : ($this->isExpired() ? 'Expired' : 'Waiting');
    }

    /** A WhatsApp message with the link, in the client's language, ready to send. */
    public function whatsappUrl(): string
    {
        $first = trim(explode(' ', trim($this->name))[0] ?? '');
        $text = match ($this->lang) {
            'ku' => "سڵاو {$first}، زۆر سوپاس بۆ متمانەت بە ستۆدیۆی ئارام مزووری. ئەگەر کاتت هەیە، خۆشحاڵ دەبین چەند وشەیەک دەربارەی کارکردن لەگەڵمان بنووسیت — دەتوانیت دەنگیشت تۆمار بکەیت:\n",
            'ar' => "مرحباً {$first}، شكراً جزيلاً لثقتك بمكتب آرام مزوري. إن سمح وقتك، يسعدنا أن تكتب بضع كلمات عن تجربتك معنا — ويمكنك تسجيل صوتك أيضاً:\n",
            default => "Hi {$first}, thank you for trusting Aram Mizuri Architecture. If you have a minute, we would love a few words about working with us — you can record your voice too:\n",
        };

        return 'https://wa.me/?text='.rawurlencode($text.$this->url());
    }
}
