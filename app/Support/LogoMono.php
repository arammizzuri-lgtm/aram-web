<?php

namespace App\Support;

/**
 * Converts an uploaded client logo into a faithful monochrome version for the
 * dark glass UI: the flat background is knocked out and the artwork is rendered
 * white-on-transparent.
 *
 * Detail is preserved by working per-pixel and continuously (every edge,
 * gradient and thin line survives — no posterising), while colours are kept
 * apart by folding chroma into the tone: two colours with the same brightness
 * still land at different opacities. Contrast is stretched to the artwork's own
 * range so faint internal detail reads, and the result is polarity-aware — a
 * dark-ink logo is inverted so it, too, comes out light; a light badge keeps
 * its dark interior as a see-through cut-out. Output is height-normalised so
 * every logo carries a consistent visual weight.
 */
class LogoMono
{
    private const MAX = 640;
    private const OUT_HEIGHT = 220;

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

        // ---- pass 1: coverage, luminance, chroma; gather ink stats ----
        $cov = [];
        $lum = [];
        $chr = [];
        $hist = array_fill(0, 256, 0);   // luminance histogram of ink pixels
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
                    $c = self::smooth(0.05, 0.26, $dist) * $opacity;
                }

                $i = $y * $w + $x;
                $cov[$i] = $c;
                $l = 0.299 * $r + 0.587 * $g + 0.114 * $b;
                $lum[$i] = $l;
                $mx = max($r, $g, $b); $mn = min($r, $g, $b);
                $chr[$i] = ($mx - $mn) / 255;

                if ($c >= 0.45) {
                    $hist[(int) round($l)]++;
                    $inkLumSum += $l; $inkCount++;
                }
            }
        }

        $meanInk = $inkCount ? $inkLumSum / $inkCount : (($bgR + $bgG + $bgB) / 3 < 128 ? 200 : 60);
        $invert = $meanInk < 128;   // dark-dominant artwork → render inverted (light)

        // contrast-stretch endpoints: 2nd / 98th percentile of ink luminance
        [$loL, $hiL] = self::percentiles($hist, $inkCount, 0.02, 0.98);
        $span = max(1.0, $hiL - $loL);

        // ---- pass 2: continuous tone (detail) + chroma (colour separation) ----
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
                $ln = min(1.0, max(0.0, ($lum[$i] - $loL) / $span));   // stretched 0..1
                $base = $invert ? (1 - $ln) : $ln;                     // light-biased ink
                // chroma lifts saturated colours so they don't collapse into an
                // equally-bright grey, and keeps mid-tone colours visible
                $tone = min(1.0, $base * 0.86 + $chr[$i] * 0.42);
                $alpha01 = min(1.0, $c * $tone);
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

    /** Return [lowLum, highLum] at the given cumulative fractions of an ink histogram. */
    private static function percentiles(array $hist, int $total, float $lowF, float $highF): array
    {
        if ($total < 1) {
            return [0.0, 255.0];
        }
        $lowTarget = $total * $lowF;
        $highTarget = $total * $highF;
        $cum = 0; $lo = 0; $hi = 255; $gotLo = false;
        for ($l = 0; $l < 256; $l++) {
            $cum += $hist[$l];
            if (! $gotLo && $cum >= $lowTarget) { $lo = $l; $gotLo = true; }
            if ($cum >= $highTarget) { $hi = $l; break; }
        }

        return [(float) $lo, (float) max($lo + 1, $hi)];
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
