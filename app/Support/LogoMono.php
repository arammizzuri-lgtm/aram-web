<?php

namespace App\Support;

/**
 * Converts an uploaded client logo into a faithful monochrome version: the
 * background is knocked out and the artwork is rendered white-on-transparent.
 *
 * Crucially, distinct colours are mapped to distinct opacities, so a
 * multi-colour logo keeps its regions visually separated in monochrome instead
 * of merging (two colours with the same brightness no longer collapse). The
 * artwork's palette is quantised, the distinct colours are ranked by lightness
 * and spread evenly across the opacity range (polarity-aware, so both dark-ink
 * and light-ink logos come out light). Output is a height-normalised PNG so
 * every logo sits at a consistent visual weight; the site paints it white.
 */
class LogoMono
{
    private const MAX = 640;            // working cap
    private const OUT_HEIGHT = 200;     // normalise every mono to this height
    private const FLOOR = 0.40;         // faintest colour still shows at this opacity

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

        // ---- pass 1: coverage, luminance, and the artwork's colour palette ----
        $cov = [];
        $lum = [];
        $bucketOf = [];
        $bucketLum = [];              // bucket key => representative luminance
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

                // 5-bit-per-channel bucket merges near-identical colours / AA noise
                $bucket = (($r >> 3) << 10) | (($g >> 3) << 5) | ($b >> 3);
                $bucketOf[$i] = $bucket;
                if ($c >= 0.35) {
                    $bucketLum[$bucket] = $l;
                    $inkLumSum += $l; $inkCount++;
                }
            }
        }

        $meanInk = $inkCount ? $inkLumSum / $inkCount : (($bgR + $bgG + $bgB) / 3 < 128 ? 200 : 60);
        $invert = $meanInk < 128;     // dark artwork → invert so it renders light

        // ---- rank distinct colours by lightness, spread across the opacity range ----
        // Sort by luminance (ties broken by bucket key), then assign each a unique
        // evenly-spaced opacity — guaranteeing different colours ⇒ different opacity.
        uksort($bucketLum, function ($k1, $k2) use ($bucketLum) {
            return ($bucketLum[$k1] <=> $bucketLum[$k2]) ?: ($k1 <=> $k2);
        });
        $keys = array_keys($bucketLum);
        $n = count($keys);
        $bucketOpacity = [];
        foreach ($keys as $idx => $key) {
            $norm = $n > 1 ? $idx / ($n - 1) : 1.0;   // 0 = darkest … 1 = lightest
            $shade = $invert ? (1 - $norm) : $norm;   // dominant ink → most opaque
            $bucketOpacity[$key] = self::FLOOR + (1 - self::FLOOR) * $shade;
        }

        // ---- pass 2: paint white; alpha = coverage × the colour's shade ----
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
                $op = $bucketOpacity[$bucketOf[$i]] ?? self::fallbackShade($lum[$i], $invert);
                $alpha01 = min(1.0, $c * $op);
                $gdA = (int) round(127 * (1 - $alpha01));
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

    /** Shade for edge/AA pixels whose colour never made the ink palette. */
    private static function fallbackShade(float $lum, bool $invert): float
    {
        $s = $invert ? (1 - $lum / 255) : ($lum / 255);

        return self::FLOOR + (1 - self::FLOOR) * $s;
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
