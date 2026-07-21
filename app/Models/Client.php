<?php

namespace App\Models;

use App\Support\LogoMono;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Client / Partner shown in the "Trusted By" marquee and modal. Managed in the
 * admin (add / remove / reorder). A row shows either an uploaded logo — auto
 * converted to a one-colour mask (logo_mono) on save — or a seeded line-art
 * SVG mark.
 */
class Client extends Model
{
    protected $fillable = [
        'name', 'sub_en', 'sub_ku', 'mark', 'logo', 'logo_mono', 'sort_order', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saved(function (Client $client) {
            // wasChanged('logo') is only true on update; a fresh create needs
            // wasRecentlyCreated. Either way, (re)build the mono when a logo is
            // present, or clear it when the logo was removed.
            $logoTouched = $client->wasChanged('logo') || ($client->wasRecentlyCreated && $client->logo);
            if (! $logoTouched) {
                return;
            }

            if ($client->logo) {
                $client->regenerateMono();
            } elseif ($client->logo_mono) {
                $client->logo_mono = null;
                $client->saveQuietly();
            }
        });
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Client> */
    public function scopePublishedOrdered($query)
    {
        return $query->where('is_published', true)->orderBy('sort_order')->orderBy('id');
    }

    /** Build (or rebuild) the one-colour mono mask from the uploaded logo. */
    public function regenerateMono(): void
    {
        $disk = Storage::disk('public');
        $srcAbs = $disk->path($this->logo);
        $rel = 'clients/mono/'.pathinfo($this->logo, PATHINFO_FILENAME).'.png';

        if (LogoMono::generate($srcAbs, $disk->path($rel))) {
            $this->logo_mono = $rel;
            $this->saveQuietly();
        }
    }

    /** Public URL of the one-colour mask, if any. */
    public function monoUrl(): ?string
    {
        return $this->logo_mono ? self::resolveImage($this->logo_mono) : null;
    }

    /** Resolve a stored path/URL to a public URL (mirrors Project::resolveImage). */
    public static function resolveImage(?string $img): ?string
    {
        if (! $img) {
            return null;
        }
        if (preg_match('#^(https?:)?//#', $img) || str_starts_with($img, '/')) {
            return $img;
        }

        return asset('storage/'.ltrim($img, '/'));
    }
}
