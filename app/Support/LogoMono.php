<?php

namespace App\Support;

/**
 * Converts an uploaded client logo into a faithful monochrome version: the
 * background is knocked out and the artwork is rendered white-on-transparent,
 * but internal tone/detail is preserved (via a luminance map) rather than
 * flattened to a solid silhouette — so detailed, multi-colour logos still read
 * like themselves, just in one tone. Displayed white on the dark glass.
 *
 * Pipeline: knock out the flat background → keep each pixel's coverage → map
 * its luminance to a light tone (polarity-aware, so dark-ink and light-ink
 * logos both come out light) → trim + pad. Output is a normalised-height PNG so
 * every logo sits at a consistent visual weight.
 */
class LogoMono
{
    private const MAX = 640;            // working cap
    private const OUT_HEIGHT = 200;     // normalise every mono to this height

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

        $img = imagecreatetruecolor($w, $h);
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
        imagecopyresampled($img, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($src);

        $transparentSource = self::cornersAreTransparent($img, $w, $h);
        [$bgR, $bgG, $bgB] = self::cornerAverage($img, $w, $h);

        // ---- pass 1: coverage + luminance, and decide polarity ----
        $cov = [];   // 0..1 how much this pixel belongs to the artwork
        $lum = [];   // 0..255 luminance
        $inkLumSum = 0.0; $inkCount = 0;

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $rgba = imagecolorat($img, $x, $y);
                $a = ($rgba >> 24) & 0x7F;
                $r = ($rgba >> 16) & 0xFF;
                $g = ($rgba >> 8) & 0xFF;
                $b = $rgba & 0xFF;
                $opacity = 1 - $a / 127;

                if ($transparentSource) {
                    $c = $opacity;
                } else {
                    $dist = sqrt((($r - $bgR) ** 2) + (($g - $bgG) ** 2) + (($b - $bgB) ** 2)) / 441.673;
                    $c = self::smooth(0.06, 0.28, $dist) * $opacity;
                }

                $i = $y * $w + $x;
                $cov[$i] = $c;
                $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $lum[$i] = $l;
                if ($c > 0.5) { $inkLumSum += $l; $inkCount++; }
            }
        }

        $inkAvg = $inkCount ? $inkLumSum / $inkCount : (($bgR + $bgG + $bgB) / 3 < 128 ? 200 : 60);
        $invert = $inkAvg < 128;   // dark artwork → invert so it renders light

        // ---- pass 2: paint white, alpha carries coverage × tone ----
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));

        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                $i = $y * $w + $x;
                $c = $cov[$i];
                if ($c < 0.03) {
                    continue;
                }
                $ink = $invert ? (255 - $lum[$i]) : $lum[$i];      // "strength" of the ink
                $tone = self::smooth(0.06, 0.9, $ink / 255);        // 0..1, keeps mid detail
                $alpha01 = $c * (0.32 + 0.68 * $tone);              // floor so faint detail survives
                $gdA = (int) round(127 * (1 - min(1.0, $alpha01)));
                if ($gdA < 127) {
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 255, 255, 255, $gdA));
                }
            }
        }
        imagedestroy($img);

        [$out, $w, $h] = self::trim($out, $w, $h);
        [$out, $w, $h] = self::normaliseHeight($out, $w, $h);

        @mkdir(dirname($destAbs), 0775, true);
        $ok = imagepng($out, $destAbs);
        imagedestroy($out);

        return (bool) $ok;
    }

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

    private static function smooth(float $e0, float $e1, float $x): float
    {
        $t = max(0.0, min(1.0, ($x - $e0) / max(1e-6, $e1 - $e0)));

        return $t * $t * (3 - 2 * $t);
    }

    /** Crop transparent margins and add ~8% breathing room. */
    private static function trim($img, int $w, int $h): array
    {
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $w; $x++) {
                if ((((imagecolorat($img, $x, $y) >> 24) & 0x7F)) < 122) {
                    if ($x < $minX) $minX = $x;
                    if ($x > $maxX) $maxX = $x;
                    if ($y < $minY) $minY = $y;
                    if ($y > $maxY) $maxY = $y;
                }
            }
        }
        if ($maxX < 0) {
            return [$img, $w, $h];
        }

        $cw = $maxX - $minX + 1;
        $ch = $maxY - $minY + 1;
        $pad = (int) round(max($cw, $ch) * 0.08);
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

    /** Scale to a fixed output height so every logo has consistent visual weight. */
    private static function normaliseHeight($img, int $w, int $h): array
    {
        if ($h === self::OUT_HEIGHT) {
            return [$img, $w, $h];
        }
        $nh = self::OUT_HEIGHT;
        $nw = max(1, (int) round($w * ($nh / $h)));

        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);

        return [$dst, $nw, $nh];
    }
}
