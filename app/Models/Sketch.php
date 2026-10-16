<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A sketch a visitor drew for the studio (sketch.js): a PNG on the private
 * disk, shown only at an address with its unguessable token in it — the
 * link the studio opens from an enquiry or a WhatsApp message. One never
 * sent with an enquiry is let go after KEEP_DAYS.
 */
class Sketch extends Model
{
    public const DISK = 'local';

    public const KEEP_DAYS = 90;

    protected $fillable = ['token', 'path', 'contact_message_id'];

    protected static function booted(): void
    {
        static::deleting(fn (Sketch $s) => Storage::disk(self::DISK)->delete($s->path));
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(ContactMessage::class, 'contact_message_id');
    }

    /** Keep a picture already checked and re-drawn (SketchController). */
    public static function keep(string $png): self
    {
        $token = Str::random(40);
        $path = 'sketches/'.$token.'.png';
        Storage::disk(self::DISK)->put($path, $png);

        return static::create(['token' => $token, 'path' => $path]);
    }

    /** Sketches never sent with an enquiry, from long enough ago, go. */
    public static function prune(): void
    {
        static::query()->whereNull('contact_message_id')
            ->where('created_at', '<', now()->subDays(self::KEEP_DAYS))
            ->get()->each->delete();
    }

    public function url(): string
    {
        return url('/sketches/'.$this->token);
    }
}
