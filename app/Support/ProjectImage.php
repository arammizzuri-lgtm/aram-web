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

    /** Link-preview (og:image) size — the 1.91:1 frame every platform crops to. */
    public const SOCIAL_W = 1200;

    public const SOCIAL_H = 630;

    /**
     * Build the image a shared link previews with: a 1200x630 JPEG cut from
     * the source around its focal point.
     *
     * JPEG rather than WebP because WhatsApp, the way most of this studio's
     * links travel, does not reliably preview WebP. Cropping here rather than
     * letting each platform do it keeps the subject in frame: left to
     * themselves they cut from the centre, which takes the top off a tower.
     *
     * @param  int  $focalX  focal point across, in % (0 = left edge)
     * @param  int  $focalY  focal point down, in % (0 = top edge)
     */
    public static function socialCard(string $srcAbs, string $destAbs, int $focalX = 50, int $focalY = 50): bool
    {
        if (! is_file($srcAbs) || ! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return false;
        }

        $src = @imagecreatefromstring((string) file_get_contents($srcAbs));
        if ($src === false) {
            return false;
        }
        $src = self::applyExifOrientation($src, $srcAbs);

        $sw = imagesx($src);
        $sh = imagesy($src);
        $ratio = self::SOCIAL_W / self::SOCIAL_H;

        // The largest 1.91:1 window that fits, slid toward the focal point and
        // kept inside the picture.
        if ($sw / $sh > $ratio) {
            $cw = (int) round($sh * $ratio);
            $ch = $sh;
        } else {
            $cw = $sw;
            $ch = (int) round($sw / $ratio);
        }
        $cx = (int) round(min(max($sw * $focalX / 100 - $cw / 2, 0), $sw - $cw));
        $cy = (int) round(min(max($sh * $focalY / 100 - $ch / 2, 0), $sh - $ch));

        $dst = imagecreatetruecolor(self::SOCIAL_W, self::SOCIAL_H);
        // JPEG has no transparency: lay transparent sources on the site's black
        imagefill($dst, 0, 0, imagecolorallocate($dst, 9, 9, 9));
        imagecopyresampled($dst, $src, 0, 0, $cx, $cy, self::SOCIAL_W, self::SOCIAL_H, $cw, $ch);
        imagedestroy($src);

        @mkdir(dirname($destAbs), 0775, true);
        imageinterlace($dst, true);   // progressive: the preview sharpens as it arrives
        $ok = imagejpeg($dst, $destAbs, 82);
        imagedestroy($dst);

        return (bool) $ok;
    }
}
