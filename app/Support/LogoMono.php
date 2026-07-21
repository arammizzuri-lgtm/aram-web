<?php

namespace App\Support;

/**
 * Converts an uploaded client logo into a single-colour "mono" mask that keeps
 * the logo's exact shape but flattens it to one colour on a transparent
 * background. The site paints it white (and gold on hover) via a CSS mask, so
 * every logo reads as a minimal one-colour mark in the same visual language.
 *
 * Two paths, chosen automatically:
 *  - transparent source  → recolour every visible pixel, keeping its alpha
 *    (preserves anti-aliasing and any interior holes exactly);
 *  - opaque source       → knock out the flat background (sampled from the
 *    corners) and keep the "ink" as the mask.
 *
 * Output is white-on-transparent PNG; only the alpha channel matters when it
 * is used as a CSS mask, so the ink colour is decided in CSS.
 */
class LogoMono
{
    private const MAX = 512;   // cap the working/output size — logos render small

    /** Generate a mono PNG at $destAbs from the image at $srcAbs. Returns success. */
    public static function generate(string $srcAbs, string $destAbs): bool
    {
        if (! is_file($srcAbs) || ! function_exists('imagecreatefromstring')) {
            return false;
        }

        $src = @imagecreatefromstring((string) file_get_contents($srcAbs));
        if ($src === false) {
            return false;
        }

        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min(1.0, self::MAX / max($sw, $sh));
        $w = max(1, (int) round($sw * $scale));
        $h = max(1, (int) round($sh * $scale));

        // work on a scaled, alpha-aware copy
        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($src);

        $transparentSource = self::cornersAreTransparent($img, $w, $h);
        [$bgR, $bgG, $bgB] = self::cornerAverage($img, $w, $h);

        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = ($rgba >> 24) & 0x7F;          // 0 opaque … 127 transparent
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;

                if ($transparentSource) {
                    // keep original coverage, just paint it white
                    $outA = $a;
                } else {
                    if ($a > 110) {                 // already transparent
                        $outA = 127;
                    } else {
                        // ink = how far this pixel is from the flat background
                        $dist = sqrt((($r - $bgR) ** 2) + (($g - $bgG) ** 2) + (($b - $bgB) ** 2)) / 441.673;
                        $ink = self::smooth(0.10, 0.32, $dist);
                        $outA = (int) round(127 * (1 - $ink));
                    }
                }

                if ($outA < 127) {
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 255, 255, 255, $outA));
                }
            }
        }
        imagedestroy($img);

        [$out, $w, $h] = self::trim($out, $w, $h);

        @mkdir(dirname($destAbs), 0775, true);
        $ok = imagepng($out, $destAbs);
        imagedestroy($out);

        return (bool) $ok;
    }

    /** True if the four corners are (near-)transparent → source already has alpha. */
    private static function cornersAreTransparent($img, int $w, int $h): bool
    {
        $pts = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
        $clear = 0;
        foreach ($pts as [$x, $y]) {
            if (((imagecolorat($img, $x, $y) >> 24) & 0x7F) > 100) {
                $clear++;
            }
        }

        return $clear >= 3;
    }

    /** Average RGB of the four corners — the presumed flat background colour. */
    private static function cornerAverage($img, int $w, int $h): array
    {
        $pts = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];
        $r = $g = $b = 0;
        foreach ($pts as [$x, $y]) {
            $c = imagecolorat($img, $x, $y);
            $r += ($c >> 16) & 0xFF;
            $g += ($c >> 8) & 0xFF;
            $b += $c & 0xFF;
        }

        return [$r / 4, $g / 4, $b / 4];
    }

    /** Smoothstep between edges e0 and e1. */
    private static function smooth(float $e0, float $e1, float $x): float
    {
        $t = max(0.0, min(1.0, ($x - $e0) / max(1e-6, $e1 - $e0)));

        return $t * $t * (3 - 2 * $t);
    }

    /** Crop transparent margins and add ~7% breathing room. Returns [img, w, h]. */
    private static function trim($img, int $w, int $h): array
    {
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ((((imagecolorat($img, $x, $y) >> 24) & 0x7F)) < 120) {
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        if ($maxX < 0) {
            return [$img, $w, $h];   // nothing found — leave as-is
        }

        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;
        $pad = (int) round(max($cw, $ch) * 0.07);
        $nw = $cw + $pad * 2;
        $nh = $ch + $pad * 2;

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopy($dst, $img, $pad, $pad, $minX, $minY, $cw, $ch);
        imagedestroy($img);

        return [$dst, $nw, $nh];
    }
}
