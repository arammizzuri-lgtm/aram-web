<?php

namespace App\Support;

/**
 * Turns a client's photo into a pencil portrait: graphite lines on a
 * transparent ground, which the page lays on a disc of paper and draws
 * stroke by stroke while the client's voice plays (voices.js).
 *
 * The lines come from the classic "colour dodge" sketch: the photo divided by
 * a blurred copy of itself, which leaves paper wherever the tone is flat and
 * a line wherever it changes — the edges a hand would draw. Flat dark areas
 * (hair, a jacket, a shadow) would come out empty that way, so they are
 * shaded with diagonal hatching instead, cross-hatched where darkest, as a
 * pencil would. The tones are stretched to the photo's own range first, so
 * a dim phone photo still gives a full drawing, and the edges fade out in a
 * circle — the portrait is shown round, and the fade lets a busy room behind
 * the client fall away rather than be drawn line for line.
 */
class PortraitSketch
{
    /** Width and height of the drawing, in pixels. */
    public const SIZE = 560;

    /**
     * How hard the pencil is pressed: [line gain, hatching strength, blur
     * radius, the brightness below which shading starts]. A wider blur draws
     * broader, softer lines.
     */
    private const DETAIL = [
        'soft' => [1.6, 0.24, 7, 135.0],
        'balanced' => [2.4, 0.40, 5, 170.0],
        'strong' => [3.4, 0.56, 4, 205.0],
    ];

    /** @return array<string, string> */
    public static function details(): array
    {
        return ['soft' => 'Soft — fewer, lighter lines', 'balanced' => 'Balanced', 'strong' => 'Strong — more lines and shading'];
    }

    public static function generate(string $srcAbs, string $destAbs, string $detail = 'balanced'): bool
    {
        if (! is_file($srcAbs) || ! function_exists('imagecreatefromstring')) {
            return false;
        }
        $src = @imagecreatefromstring((string) file_get_contents($srcAbs));
        if ($src === false) {
            return false;
        }
        $src = self::upright($src, $srcAbs);

        [$gainLines, $hatching, $radius, $shadeFrom] = self::DETAIL[$detail] ?? self::DETAIL['balanced'];
        $n = self::SIZE;
        $lum = self::squareLuminance($src, $n);
        imagedestroy($src);

        // each working copy is let go as soon as the next is made: shared
        // hosting gives a request little memory, and these are large
        $smooth = self::blur(self::stretch($lum), $n, 1, 1);  // takes the JPEG grain out before the dodge finds it
        unset($lum);

        $inv = [];
        foreach ($smooth as $i => $v) {
            $inv[$i] = 255 - $v;
        }
        $wide = self::blur($inv, $n, $radius, 3);           // three box passes ≈ a gaussian
        unset($inv);

        $out = imagecreatetruecolor($n, $n);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        $clear = imagecolorallocatealpha($out, 34, 32, 30, 127);
        imagefill($out, 0, 0, $clear);

        $half = $n / 2;
        $period = 6.0;                                       // hatching pitch, px
        for ($y = 0; $y < $n; $y++) {
            // a slight wobble, so the hatching reads as drawn rather than ruled
            $wobble = 1.4 * sin($y * 0.045);
            for ($x = 0; $x < $n; $x++) {
                $i = $y * $n + $x;
                $base = $smooth[$i];

                // the dodge: paper where flat, a line where the tone changes
                $dodge = min(255.0, $base * 255.0 / max(1.0, 255.0 - $wide[$i]));
                $line = max(0.0, (255.0 - $dodge) - 6.0) * $gainLines / 255.0;
                $line = min(1.0, $line);

                // shading for the flat dark areas the dodge leaves empty
                $dark = self::smooth($shadeFrom, 40.0, $base);
                $tone = 0.0;
                if ($dark > 0.0) {
                    $one = self::stroke(($x - $y + $wobble) / $period);
                    $cross = $base < 80 ? self::stroke(($x + $y - $wobble) / $period) * self::smooth(80.0, 30.0, $base) : 0.0;
                    $tone = $dark * $hatching * max($one, $cross);
                }

                $ink = 1.0 - (1.0 - $line) * (1.0 - $tone);

                // fade out towards the edge of the circle the portrait is shown in
                $r = sqrt(($x - $half) ** 2 + ($y - $half) ** 2) / $half;
                $ink *= self::smooth(1.0, 0.74, $r);

                if ($ink > 0.02) {
                    $a = 127 - (int) round(min(1.0, $ink) * 127);
                    imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, 34, 32, 30, $a));
                }
            }
        }

        @mkdir(dirname($destAbs), 0775, true);
        $ok = str_ends_with(strtolower($destAbs), '.webp') && function_exists('imagewebp')
            ? imagewebp($out, $destAbs, 86)
            : imagepng($out, $destAbs, 9);
        imagedestroy($out);

        return (bool) $ok;
    }

    /** Phone photos are often stored sideways, with a note to turn them. */
    private static function upright(\GdImage $img, string $path): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $img;
        }
        $exif = @exif_read_data($path);
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle === 0) {
            return $img;
        }
        $turned = imagerotate($img, $angle, 0);
        if ($turned === false) {
            return $img;
        }
        imagedestroy($img);

        return $turned;
    }

    /**
     * The photo cut to a centred square — a little above centre on an
     * upright photo, where the face usually is — scaled to $n, as luminance.
     *
     * @return array<int, float>
     */
    private static function squareLuminance(\GdImage $src, int $n): array
    {
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $x0 = (int) (($w - $side) / 2);
        $y0 = (int) (($h - $side) * 0.3);

        $sq = imagecreatetruecolor($n, $n);
        imagecopyresampled($sq, $src, 0, 0, $x0, $y0, $n, $n, $side, $side);

        $lum = [];
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                $c = imagecolorat($sq, $x, $y);
                $lum[] = 0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF);
            }
        }
        imagedestroy($sq);

        return $lum;
    }

    /**
     * Stretch the tones to the photo's own range (1st–99th percentile).
     *
     * @param  array<int, float>  $lum
     * @return array<int, float>
     */
    private static function stretch(array $lum): array
    {
        $hist = array_fill(0, 256, 0);
        foreach ($lum as $v) {
            $hist[(int) $v]++;
        }
        $total = count($lum);
        $lo = 0; $hi = 255; $cum = 0;
        for ($l = 0; $l < 256; $l++) {
            $cum += $hist[$l];
            if ($cum <= $total * 0.01) {
                $lo = $l;
            }
            if ($cum >= $total * 0.99) {
                $hi = $l;
                break;
            }
        }
        $span = max(40, $hi - $lo);                      // never blow a near-flat photo up into noise

        foreach ($lum as $i => $v) {
            $lum[$i] = max(0.0, min(255.0, ($v - $lo) * 255.0 / $span));
        }

        return $lum;
    }

    /**
     * Box blur of radius $r, $passes times over (three passes approach a
     * gaussian), with running sums so the cost does not grow with $r.
     *
     * @param  array<int, float>  $px
     * @return array<int, float>
     */
    private static function blur(array $px, int $n, int $r, int $passes): array
    {
        $win = 2 * $r + 1;
        $last = $n - 1;
        for ($p = 0; $p < $passes; $p++) {
            // along each row, then down each column: $step is the distance
            // between neighbours, $lineStep between one line and the next
            foreach ([[1, $n], [$n, 1]] as [$step, $lineStep]) {
                $out = $px;
                for ($a = 0; $a < $n; $a++) {
                    $o = $a * $lineStep;
                    // edge pixels repeat beyond the border
                    $sum = 0.0;
                    for ($b = -$r; $b <= $r; $b++) {
                        $sum += $px[$o + max(0, min($last, $b)) * $step];
                    }
                    for ($b = 0; $b < $n; $b++) {
                        $out[$o + $b * $step] = $sum / $win;
                        $sum += $px[$o + min($last, $b + $r + 1) * $step] - $px[$o + max(0, $b - $r) * $step];
                    }
                }
                $px = $out;
            }
        }

        return $px;
    }

    /** A pencil stroke across a hatching period: dark in the middle, fading out to either side. */
    private static function stroke(float $t): float
    {
        $d = abs($t - floor($t) - 0.5) * 2;               // 0 at the stroke's centre, 1 between strokes

        return self::smooth(0.75, 0.25, $d);
    }

    /** Smoothstep from $e0 to $e1 (either direction). */
    private static function smooth(float $e0, float $e1, float $x): float
    {
        $t = max(0.0, min(1.0, ($x - $e0) / ($e1 - $e0)));

        return $t * $t * (3 - 2 * $t);
    }
}
