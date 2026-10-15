<?php

namespace App\Models;

use App\Support\PortraitSketch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A client's words about the studio. Shown in the homepage quotes, and on the
 * page of the project it is about, if it names one.
 *
 * It can also carry the client's voice note and a pencil portrait drawn from
 * their photo. The photo is kept on the private disk and never published;
 * the drawing is made from it on save. Neither voice nor drawing is shown
 * until the client's agreement is ticked (media_consent).
 */
class Testimonial extends Model
{
    /** Where the client's photo is kept: private, like a room's drawings. */
    public const PHOTO_DISK = 'local';

    protected $fillable = [
        'quote_en', 'quote_ku', 'person_name', 'person_name_ku', 'person_role_en', 'person_role_ku',
        'client_id', 'project_id', 'sort_order', 'is_published',
        'voice', 'portrait', 'sketch_detail', 'media_consent',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'media_consent' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::created(function (Testimonial $t) {
            if ($t->portrait) {
                $t->redrawPortrait();
            }
        });

        static::updated(function (Testimonial $t) {
            // a voice or photo replaced or removed: the old file goes with it.
            // Read before the redraw below, whose own save forgets what changed.
            $oldVoice = $t->getOriginal('voice');
            if ($t->wasChanged('voice') && $oldVoice) {
                Storage::disk('public')->delete($oldVoice);
            }
            $oldPhoto = $t->getOriginal('portrait');
            if ($t->wasChanged('portrait') && $oldPhoto) {
                Storage::disk(self::PHOTO_DISK)->delete($oldPhoto);
            }

            // a new photo, or the same one drawn with a different pencil
            if ($t->wasChanged(['portrait', 'sketch_detail'])) {
                $t->redrawPortrait();
            }
        });

        static::deleted(function (Testimonial $t) {
            foreach ([$t->voice, $t->portrait_sketch] as $file) {
                if ($file) {
                    Storage::disk('public')->delete($file);
                }
            }
            if ($t->portrait) {
                Storage::disk(self::PHOTO_DISK)->delete($t->portrait);
            }
        });
    }

    /**
     * Draw (or redraw) the pencil portrait from the photo, or drop it when
     * the photo has gone. Each drawing gets a new name, so a redrawn one is
     * never served from a cache as the old.
     */
    public function redrawPortrait(): void
    {
        $old = $this->portrait_sketch;
        $new = null;

        if ($this->portrait) {
            $ext = function_exists('imagewebp') ? 'webp' : 'png';
            $rel = 'testimonials/sketches/'.Str::random(32).'.'.$ext;
            $photo = Storage::disk(self::PHOTO_DISK)->path($this->portrait);
            if (PortraitSketch::generate($photo, Storage::disk('public')->path($rel), $this->sketch_detail ?: 'balanced')) {
                $new = $rel;
            }
        }

        $this->portrait_sketch = $new;
        $this->saveQuietly();
        if ($old && $old !== $new) {
            Storage::disk('public')->delete($old);
        }
    }

    /** The pencil portrait's address, once the client has agreed to it being shown. */
    public function sketchUrl(): ?string
    {
        return $this->media_consent && $this->portrait_sketch ? asset('storage/'.$this->portrait_sketch) : null;
    }

    /** The voice note's address, once the client has agreed to it being heard. */
    public function voiceUrl(): ?string
    {
        return $this->media_consent && $this->voice ? asset('storage/'.$this->voice) : null;
    }

    /** Whether there is a voice or a portrait to show with the words. */
    public function hasMedia(): bool
    {
        return $this->sketchUrl() !== null || $this->voiceUrl() !== null;
    }

    /**
     * The voice note's type, for the page to ask the browser whether it can
     * play it. WhatsApp's own .opus files are Opus in an Ogg wrapper, which
     * older iPhones cannot play; the player hides itself there.
     */
    public function voiceType(): string
    {
        return match (strtolower(pathinfo((string) $this->voice, PATHINFO_EXTENSION))) {
            'opus' => 'audio/ogg; codecs=opus',
            'ogg', 'oga' => 'audio/ogg',
            'mp3' => 'audio/mpeg',
            'm4a', 'mp4', 'aac' => 'audio/mp4',
            'wav' => 'audio/wav',
            'webm' => 'audio/webm',
            default => '',
        };
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<Testimonial>  $query */
    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Only quotes that have something to say in this language. A quote is the
     * client's own words, so one written in English is never shown on the
     * Kurdish site in its place.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Testimonial>  $query
     */
    public function scopeInLanguage($query, string $lang)
    {
        $column = $lang === 'ku' ? 'quote_ku' : 'quote_en';

        return $query->whereNotNull($column)->where($column, '!=', '');
    }

    public function quote(string $lang): string
    {
        return (string) ($lang === 'ku' ? $this->quote_ku : $this->quote_en);
    }

    public function personName(string $lang): string
    {
        return $lang === 'ku' && filled($this->person_name_ku) ? $this->person_name_ku : $this->person_name;
    }

    /** "Owner, Shaqlawa Villa" — the role, then the client it speaks for. */
    public function attribution(string $lang): string
    {
        $role = $lang === 'ku' && filled($this->person_role_ku) ? $this->person_role_ku : $this->person_role_en;

        return collect([$role, $this->client?->name])->filter()->implode($lang === 'ku' ? '، ' : ', ');
    }
}
