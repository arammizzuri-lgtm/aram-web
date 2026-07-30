<?php

namespace App\Support;

/**
 * Generates web-optimised WebP derivatives of an uploaded project image.
 *
 * The originals are multi-MB camera/render exports. Every screen that shows
 * them scales them down anyway, so shipping the original just makes the visitor
 * download detail the browser immediately discards. We do that downscaling once,
 * on the server, and serve the result — visually identical at the size it is
 * displayed, but a fraction of the bytes.
 *
 * The original file is never modified or deleted: it stays the source for the
 * lightbox zoom, the "view full resolution" link and the watermarked download.
 *
 * Sizes are deliberately generous (2x the CSS size they are displayed at) so
 * they stay sharp on retina / high-DPI screens, which is the one way a
 * downscaled image gives itself away.
 */
class ProjectImage
{
    /**
     * Derivative variants: disk folder => [longest edge in px, WebP quality].
     *
     * - sm    thumb strip (~90px CSS) and the blurred ambient backdrop
     * - thumb grid cards (~500px CSS card, so 1200 covers 2x)
     * - lg    overlay hero (~1280px CSS frame, so 2560 covers 2x)
     *
     * Quality 82-88 keeps gradients (skies, concrete, glass) free of the
     * banding that shows up on architectural photography below ~80.
     */
    public const SIZES = [
        'sm' => [400, 82],
        'thumb' => [1200, 85],
        'lg' => [2560, 88],
    ];

    /** Longest edge of the default (grid) thumbnail, in pixels. */
    public const MAX = 1200;

    public const QUALITY = 85;

    /** Build a single derivative. */
    public static function thumbnail(string $srcAbs, string $destAbs, int $max = self::MAX, int $quality = self::QUALITY): bool
    {
        return self::derivatives($srcAbs, [[$destAbs, $max, $quality]]) === 1;
    }

    /**
     * Build several sizes from one source in a single pass.
     *
     * Decoding a multi-MB original is by far the expensive part of this, so
     * decoding it once per size did three times the necessary work. That matters
     * on a cheap shared host, where the whole backfill has to finish inside one
     * deploy step before it gets cut off.
     *
     * @param  array<int, array{0: string, 1: int, 2: int}>  $targets  [destAbs, maxEdge, quality]
     * @return int  how many files were written
     */
    public static function derivatives(string $srcAbs, array $targets): int
    {
        if ($targets === [] || ! is_file($srcAbs)
            || ! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return 0;
        }

        $src = @imagecreatefromstring((string) file_get_contents($srcAbs));
        if ($src === false) {
            return 0;
        }

        // Cameras and phones record rotation in EXIF rather than rotating the
        // pixels. Browsers honour that tag on the original, but GD hands us the
        // raw un-rotated pixels — without this the derivative would come out
        // sideways while the full-resolution version looked upright.
        $src = self::applyExifOrientation($src, $srcAbs);

        $written = 0;
        foreach ($targets as [$destAbs, $max, $quality]) {
            if (self::writeResized($src, $destAbs, $max, $quality)) {
                $written++;
            }
        }
        imagedestroy($src);

        return $written;
    }

    /** Resize an already-decoded image into one WebP file on disk. */
    private static function writeResized($src, string $destAbs, int $max, int $quality): bool
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min(1.0, $max / max($sw, $sh));   // only ever shrink
        $w = max(1, (int) round($sw * $scale));
        $h = max(1, (int) round($sh * $scale));

        $dst = imagecreatetruecolor($w, $h);
        // preserve transparency for PNG/WebP sources
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);

        @mkdir(dirname($destAbs), 0775, true);
        $ok = imagewebp($dst, $destAbs, $quality);
        imagedestroy($dst);

        return (bool) $ok;
    }

    /**
     * Rotate/flip a GD image to match the source file's EXIF Orientation tag.
     * Returns the image untouched when there is no usable tag (non-JPEG, no
     * EXIF extension, or already upright).
     *
     * @param  \GdImage  $img
     * @return \GdImage
     */
    private static function applyExifOrientation($img, string $srcAbs)
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }

        // exif_read_data emits a warning for formats that carry no EXIF block.
        $exif = @exif_read_data($srcAbs);
        $orientation = (int) ($exif['Orientation'] ?? 0);
        if ($orientation < 2 || $orientation > 8) {
            return $img;
        }

        // Values 2/4/5/7 are mirrored as well as rotated; 3/6/8 are pure rotations.
        $rotate = match ($orientation) {
            3, 4 => 180,
            5, 6 => -90,
            7, 8 => 90,
            default => 0,
        };

        if ($rotate !== 0) {
            $rotated = imagerotate($img, $rotate, 0);
            if ($rotated !== false) {
                imagedestroy($img);
                $img = $rotated;
            }
        }
        if (in_array($orientation, [2, 4, 5, 7], true) && function_exists('imageflip')) {
            imageflip($img, IMG_FLIP_HORIZONTAL);
        }

        return $img;
    }
}
