<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * A client's pencil portrait, as an SVG drawing built from strokes.
 *
 * The strokes are found in the admin's browser (public/portrait-drawer.js:
 * MediaPipe finds the face; the lines are traced from it) and arrive here as
 * plain numbers — never as markup — so the drawing the website publishes is
 * written by this class alone. Each stroke is one <path>, in the order it is
 * to be drawn, so the page can draw them line after line (voices.js);
 * data-k says what kind of stroke it is — g a guide line, l a line, h
 * hatching — which the page draws at different speeds.
 */
class PortraitDrawing
{
    public const SIZE = 512;

    private const KINDS = [
        'guide' => 'g', 'hatch' => 'h',
        'oval' => 'l', 'eyes' => 'l', 'nose' => 'l', 'mouth' => 'l', 'face' => 'l', 'hair' => 'l', 'neck' => 'l',
    ];

    private const MAX_STROKES = 4000;

    private const MAX_POINTS = 600;

    /**
     * The drawing for $payload: ['size' => 512, 'strokes' => [['k' => 'eyes',
     * 'w' => 1.8, 'o' => 0.7, 'p' => [x, y, x, y, …]], …]].
     *
     * @param  array<string, mixed>  $payload
     */
    public static function svg(array $payload): string
    {
        $strokes = $payload['strokes'] ?? null;
        if ((int) ($payload['size'] ?? 0) !== self::SIZE || ! is_array($strokes) || $strokes === [] || count($strokes) > self::MAX_STROKES) {
            throw new InvalidArgumentException('Not a portrait drawing.');
        }

        $paths = [];
        foreach ($strokes as $stroke) {
            $paths[] = self::path(is_array($stroke) ? $stroke : []);
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.self::SIZE.' '.self::SIZE.'" fill="none" stroke="#33312e" stroke-linecap="round" stroke-linejoin="round">'
            .implode('', $paths).'</svg>';
    }

    /** One stroke: a smooth curve through its points (each segment ends half-way to the next point). */
    private static function path(array $stroke): string
    {
        $kind = self::KINDS[$stroke['k'] ?? ''] ?? throw new InvalidArgumentException('Unknown stroke.');
        $p = $stroke['p'] ?? null;
        if (! is_array($p) || count($p) < 4 || count($p) % 2 !== 0 || count($p) > self::MAX_POINTS * 2) {
            throw new InvalidArgumentException('A stroke needs two points or more.');
        }
        $n = [];
        foreach ($p as $v) {
            if (! is_int($v) && ! is_float($v)) {
                throw new InvalidArgumentException('A stroke is made of numbers.');
            }
            $n[] = self::num(max(-64, min(self::SIZE + 64, (float) $v)));
        }
        $width = self::num(max(0.3, min(6, (float) ($stroke['w'] ?? 1))));
        $opacity = self::num(max(0.05, min(1, (float) ($stroke['o'] ?? 1))));

        $d = 'M'.$n[0].' '.$n[1];
        $count = count($n);
        if ($count === 4) {
            $d .= 'L'.$n[2].' '.$n[3];
        } else {
            for ($k = 2; $k + 3 < $count; $k += 2) {
                $mx = self::num(((float) $n[$k] + (float) $n[$k + 2]) / 2);
                $my = self::num(((float) $n[$k + 1] + (float) $n[$k + 3]) / 2);
                $d .= 'Q'.$n[$k].' '.$n[$k + 1].' '.$mx.' '.$my;
            }
            $d .= 'L'.$n[$count - 2].' '.$n[$count - 1];
        }

        return '<path d="'.$d.'" stroke-width="'.$width.'" stroke-opacity="'.$opacity.'" data-k="'.$kind.'"/>';
    }

    /** A number as it is written in the drawing: one decimal, no trailing zero. */
    private static function num(float $v): string
    {
        $s = rtrim(rtrim(number_format($v, 1, '.', ''), '0'), '.');

        return $s === '-0' ? '0' : $s;
    }
}
