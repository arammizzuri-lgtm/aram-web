<?php

namespace App\Support;

/**
 * Generates a small, web-optimised WebP thumbnail from an uploaded project
 * image. The public grid shows many covers at once, so serving the multi-MB
 * originals there is what makes the page feel slow; a ~1200px WebP is typically
 * 20–60× smaller. The original is kept untouched for the lightbox, the
 * "View full resolution" link and the watermarked download.
 */
class ProjectImage
{
    /** Longest edge of the generated thumbnail, in pixels. */
    public const MAX = 1200;

    public const QUALITY = 78;

    public static function thumbnail(string $srcAbs, string $destAbs, int $max = self::MAX, int $quality = self::QUALITY): bool
    {
        if (! is_file($srcAbs) || ! function_exists('imagecreatefromstring') || ! function_exists('imagewebp')) {
            return false;
        }

        $src = @imagecreatefromstring((string) file_get_contents($srcAbs));
        if ($src === false) {
            return false;
        }

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
        imagedestroy($src);

        @mkdir(dirname($destAbs), 0775, true);
        $ok = imagewebp($dst, $destAbs, $quality);
        imagedestroy($dst);

        return (bool) $ok;
    }
}
