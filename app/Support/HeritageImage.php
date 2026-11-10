<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The photographs of Kurdistan's architectural heritage (/kurdistan-heritage).
 *
 * Every landmark photograph is a file on the public disk under heritage/ —
 * the studio's uploads from the admin, and the credited photographs the
 * collection was seeded with. Each is served in three sizes, built once as
 * WebP beside the original (heritage/_v/<size>/…) by the same resizer the
 * project photos use (ProjectImage): a card or a map pop-up takes the small
 * one, a gallery cell the medium, a full-width photograph or the lightbox the
 * large. The browser picks by srcset, so no screen downloads more than it
 * shows. A size that has not been built yet falls back to the original.
 *
 * Like the project images, a reference that is a full URL or an absolute path
 * is served as it is, untouched.
 */
class HeritageImage
{
    /** Derivative sizes: name => [longest edge in px, WebP quality]. */
    public const SIZES = [
        'sm' => [480, 80],    // cards, map pop-ups, thumbnails
        'md' => [1000, 82],   // gallery cells, inset photographs
        'lg' => [1800, 84],   // full-width photographs, the lightbox
    ];

    public const DIR = 'heritage';

    /** Whether a reference is one of our uploads (not a URL or absolute path). */
    public static function isUpload(?string $file): bool
    {
        return is_string($file) && $file !== ''
            && ! preg_match('#^(https?:)?//#', $file)
            && ! str_starts_with($file, '/');
    }

    /** Disk path of one derivative of an upload, or null for anything we cannot resize. */
    public static function variantRel(?string $file, string $size): ?string
    {
        if (! self::isUpload($file) || ! isset(self::SIZES[$size])) {
            return null;
        }
        $rel = ltrim($file, '/');
        if (str_starts_with($rel, self::DIR.'/')) {
            $rel = substr($rel, strlen(self::DIR) + 1);
        }
        $dir = pathinfo($rel, PATHINFO_DIRNAME);
        $name = pathinfo($rel, PATHINFO_FILENAME);

        return self::DIR.'/_v/'.$size.'/'.($dir !== '.' && $dir !== '' ? $dir.'/' : '').$name.'.webp';
    }

    /**
     * Public URL of a photograph at a size — the derivative when it has been
     * built, the original otherwise (slower, never broken).
     */
    public static function url(?string $file, string $size = 'lg'): ?string
    {
        if (! $file) {
            return null;
        }
        if (! self::isUpload($file)) {
            return $file;
        }
        $disk = Storage::disk('public');
        $rel = self::variantRel($file, $size);
        if ($rel !== null && $disk->exists($rel)) {
            return $disk->url($rel);
        }

        return $disk->url(ltrim($file, '/'));
    }

    /**
     * A srcset over every size that exists, each with its real width, so the
     * browser can choose by the width it will draw — plus the original as the
     * widest candidate. Empty for a URL that is not ours.
     *
     * @param  int|null  $w  the original's width, when known (gives it a descriptor)
     */
    public static function srcset(?string $file, ?int $w = null): string
    {
        if (! self::isUpload($file)) {
            return '';
        }
        $disk = Storage::disk('public');
        $out = [];
        foreach (self::SIZES as $size => [$max]) {
            $rel = self::variantRel($file, $size);
            if ($rel !== null && $disk->exists($rel)) {
                $out[] = $disk->url($rel).' '.($w !== null ? min($w, $max) : $max).'w';
            }
        }
        if ($w !== null && ($out === [] || $w > self::SIZES['lg'][0])) {
            $out[] = $disk->url(ltrim($file, '/')).' '.$w.'w';
        }

        return implode(', ', $out);
    }

    /** [width, height] of an upload, read from the file; null when it cannot be read. */
    public static function dimensions(?string $file): ?array
    {
        if (! self::isUpload($file)) {
            return null;
        }
        $disk = Storage::disk('public');
        $rel = ltrim($file, '/');
        if (! $disk->exists($rel)) {
            return null;
        }
        $info = @getimagesize($disk->path($rel));

        return $info ? [(int) $info[0], (int) $info[1]] : null;
    }

    /**
     * Build the derivatives still missing for a list of uploads. A size the
     * original is already small enough for is skipped when the original is
     * WebP itself (the seeded photographs are stored at the large size).
     *
     * @param  iterable<int, ?string>  $files
     * @return int  how many files were written
     */
    public static function buildVariants(iterable $files): int
    {
        $disk = Storage::disk('public');
        $written = 0;
        foreach ($files as $file) {
            if (! self::isUpload($file)) {
                continue;
            }
            $rel = ltrim($file, '/');
            if (! $disk->exists($rel)) {
                continue;
            }
            $src = $disk->path($rel);
            $info = @getimagesize($src);
            $edge = $info ? max((int) $info[0], (int) $info[1]) : null;
            $isWebp = strtolower(pathinfo($rel, PATHINFO_EXTENSION)) === 'webp';

            $targets = [];
            foreach (self::SIZES as $size => [$max, $quality]) {
                $vrel = self::variantRel($file, $size);
                if ($vrel === null || $disk->exists($vrel)) {
                    continue;
                }
                if ($isWebp && $edge !== null && $edge <= $max) {
                    continue;   // the original already is this size, or smaller
                }
                $targets[] = [$disk->path($vrel), $max, $quality];
            }
            if ($targets !== []) {
                $written += ProjectImage::derivatives($src, $targets);
            }
        }

        return $written;
    }
}
