<?php

namespace App\Models;

use App\Support\Articles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * A drawing traced in the admin (Portfolio → Drawings), over a private scan of the survey:
 *  - with no base, a new plan, which an essay's "Drawing" section can show;
 *  - with a base (a key of an essay's drawings, e.g. h318), a correction: while it is live its walls
 *    are drawn on the essay in place of the traced ones, and the doors, windows and rooms of the
 *    original stay on top.
 * `data` is the tracer's (DrawingTracer), all in cm like the essays' drawings:
 *   walls [[[x,y]…]…] closed outlines, read even-odd · openings [{k: door|window|gap, a, b, t, flip}]
 *   along a wall's middle, t its thickness · lines [{g: fix|stair|over|ctx, p: [[x,y]…]}]
 *   · rooms [{x, y, en, ku, ar}] · north (degrees, or null) · scan {m, c}: the scan's pixels to cm.
 * Drawn by journal/drawing, as the essays' drawings are.
 */
class Drawing extends Model
{
    public const DISK = 'local';

    public const LINES = ['fix' => 'Fixture', 'stair' => 'Stair', 'over' => 'Overhead (dashed)', 'ctx' => 'Context'];

    protected $fillable = ['title_en', 'title_ku', 'title_ar', 'base', 'scan', 'data', 'is_live'];

    protected $casts = ['data' => 'array', 'is_live' => 'boolean'];

    protected static function booted(): void
    {
        // a scan replaced in the admin, or the drawing deleted: its file goes
        static::updated(function (Drawing $d) {
            $old = $d->getOriginal('scan');
            if ($d->wasChanged('scan') && $old) {
                Storage::disk(self::DISK)->delete($old);
            }
        });
        static::deleted(fn (Drawing $d) => $d->scan && Storage::disk(self::DISK)->delete($d->scan));
    }

    /* ── The essays' drawings ────────────────────────────────────────── */

    /** Every drawing of the essays, by key, as its file has it (no corrections). */
    public static function code(): array
    {
        $all = [];
        foreach (Articles::all() as $a) {
            $all += Articles::raw($a);
        }

        return $all;
    }

    /** The drawings an essay's "Drawing" section can show: the essays' own, then the new ones traced here. */
    public static function options(): array
    {
        $o = [];
        foreach (Articles::all() as $a) {
            foreach (array_keys(Articles::raw($a)) as $k) {
                $o['code:'.$k] = $a['title'][0].' · '.$k;
            }
        }
        foreach (self::whereNull('base')->orderBy('title_en')->get() as $d) {
            $o['db:'.$d->id] = 'Traced · '.$d->title_en;
        }

        return $o;
    }

    /** A section's pick (options()) as journal/drawing takes it, corrections in, or null if it is gone or empty. */
    public static function resolve(?string $ref): ?array
    {
        [$kind, $key] = explode(':', (string) $ref, 2) + [1 => ''];
        if ($kind === 'code') {
            foreach (Articles::all() as $a) {
                if (isset(Articles::raw($a)[$key])) {
                    return Articles::drawings($a)[$key];
                }
            }

            return null;
        }

        return $kind === 'db' ? self::whereNull('base')->find((int) $key)?->toDwg() : null;
    }

    /** The essays' drawings with the live corrections' walls in place of theirs. */
    public static function corrected(array $dwgs): array
    {
        if (! Schema::hasTable('drawings')) {
            return $dwgs;   // before the migration has run on a deploy
        }
        foreach (self::where('is_live', true)->whereIn('base', array_keys($dwgs))->get() as $c) {
            $dwgs[$c->base] = $c->applyTo($dwgs[$c->base]);
        }

        return $dwgs;
    }

    /** A drawing's wall path (M L H V Z, as the essays' files have it) as outlines of points. */
    public static function outlines(string $path): array
    {
        $out = [];
        $cur = [];
        $x = $y = 0.0;
        preg_match_all('/([MLHVZ])([^MLHVZ]*)/i', $path, $m, PREG_SET_ORDER);
        foreach ($m as [, $cmd, $args]) {
            preg_match_all('/-?\d*\.?\d+(?:e-?\d+)?/i', $args, $n);
            $n = array_map('floatval', $n[0]);
            switch (strtoupper($cmd)) {
                case 'M':
                    if (count($cur) > 2) {
                        $out[] = $cur;
                    }
                    $cur = [];
                    // the pairs after an M are lines on from it
                    for ($i = 0; $i + 1 < count($n); $i += 2) {
                        $cur[] = [$x = $n[$i], $y = $n[$i + 1]];
                    }
                    break;
                case 'L':
                    for ($i = 0; $i + 1 < count($n); $i += 2) {
                        $cur[] = [$x = $n[$i], $y = $n[$i + 1]];
                    }
                    break;
                case 'H':
                    foreach ($n as $v) {
                        $cur[] = [$x = $v, $y];
                    }
                    break;
                case 'V':
                    foreach ($n as $v) {
                        $cur[] = [$x, $y = $v];
                    }
                    break;
                case 'Z':
                    if (count($cur) > 1 && $cur[0] === end($cur)) {
                        array_pop($cur);
                    }
                    if (count($cur) > 2) {
                        $out[] = $cur;
                    }
                    $cur = [];
                    break;
            }
        }
        if (count($cur) > 2) {
            $out[] = $cur;
        }

        return $out;
    }

    /* ── Drawn ───────────────────────────────────────────────────────── */

    /** A correction drawn on its original: the walls traced here, the original's openings and names kept, anything added on top. */
    public function applyTo(array $dwg): array
    {
        $g = $this->layers();
        if ($g['wall'] !== '') {
            $dwg['d']['wall'] = $g['wall'];
        }
        foreach (['fix', 'stair', 'over', 'ctx', 'open', 'leaf', 'glass', 'cut'] as $k) {
            $dwg['d'][$k] = trim(($dwg['d'][$k] ?? '').$g[$k]);
        }
        $dwg['text'] = array_merge($dwg['text'], $this->roomTexts());

        return $dwg;
    }

    /** A new plan as journal/drawing takes it, sized to what is drawn, with a scale and a north point; null while empty. */
    public function toDwg(): ?array
    {
        $pts = $this->points();
        if (! $pts) {
            return null;
        }
        $xs = array_column($pts, 0);
        $ys = array_column($pts, 1);
        [$x0, $y0, $x1, $y1] = [min($xs), min($ys), max($xs), max($ys)];
        [$w, $h] = [max($x1 - $x0, 1), max($y1 - $y0, 1)];
        $fs = round(max(max($w, $h) / 60, 8), 1);
        $m = $fs * 3;

        // the scale bar and the north point below the plan
        $sy = round($y1 + $fs * 3.5, 1);
        $len = $w < 600 ? 100 : ($w < 3000 ? 500 : 1000);
        $tick = $fs / 2;
        $scale = 'M'.$x0.' '.$sy.'H'.($x0 + $len);
        for ($i = 0; $i <= 5; $i++) {
            $t = in_array($i, [0, 5], true) ? $tick : $tick * .6;
            $scale .= 'M'.round($x0 + $len * $i / 5, 1).' '.round($sy - $t, 1).'V'.round($sy + $t, 1);
        }
        $nr = round($fs * 1.3, 1);
        $np = [round($x1 - $nr, 1), $sy];
        $north = $this->data['north'] ?? null;
        $nd = '';
        if (is_numeric($north)) {
            $r = deg2rad((float) $north);
            [$ux, $uy] = [sin($r), -cos($r)];   // north, clockwise from up
            [$px, $py] = [-$uy, $ux];
            $tip = [$np[0] + $ux * $nr, $np[1] + $uy * $nr];
            $nd = 'M'.round($np[0] - $nr, 1).' '.$np[1].'a'.$nr.' '.$nr.' 0 1 0 '.($nr * 2).' 0a'.$nr.' '.$nr.' 0 1 0 '.(-$nr * 2).' 0'
                .'M'.self::p([$np[0] - $ux * $nr, $np[1] - $uy * $nr]).'L'.self::p($tip)
                .'M'.self::p([$tip[0] - $ux * $nr * .6 + $px * $nr * .38, $tip[1] - $uy * $nr * .6 + $py * $nr * .38]).'L'.self::p($tip)
                .'L'.self::p([$tip[0] - $ux * $nr * .6 - $px * $nr * .38, $tip[1] - $uy * $nr * .6 - $py * $nr * .38]);
        }

        $g = $this->layers();
        $text = $this->roomTexts();
        $text[] = [$x0, round($sy + $fs * 1.9, 1), 'p-dim', '0', 'middle'];
        $text[] = [$x0 + $len, round($sy + $fs * 1.9, 1), 'p-dim', ($len / 100).' m', 'middle'];

        return [
            'vb' => [round($x0 - $m, 1), round($y0 - $m, 1), round($w + 2 * $m, 1), round($sy + $fs * 3 - ($y0 - $m), 1)],
            'fs' => $fs, 'W' => round($w, 1), 'H' => round($h, 1),
            'north' => is_numeric($north) ? (float) $north : null, 'np' => $np, 'nr' => $nr, 'wallH' => 0,
            'court' => null, 'title' => [$x0, $sy], 'areas' => [], 'bb' => [$x0, $y0, $w, $h],
            'd' => $g + ['axis' => '', 'bubble' => '', 'arrow' => '', 'dim' => '', 'north' => $nd, 'scale' => $scale],
            'text' => $text,
        ];
    }

    /** The traced walls, openings and lines as journal/drawing's path groups. */
    public function layers(): array
    {
        $data = $this->data ?? [];
        $g = array_fill_keys(['wall', 'fix', 'stair', 'over', 'ctx', 'open', 'leaf', 'glass', 'cut'], '');

        foreach ($data['walls'] ?? [] as $poly) {
            if (count($poly) > 2) {
                $g['wall'] .= 'M'.implode('L', array_map([self::class, 'p'], $poly)).'Z';
            }
        }
        foreach ($data['lines'] ?? [] as $l) {
            if (isset(self::LINES[$l['g'] ?? '']) && count($l['p'] ?? []) > 1) {
                $g[$l['g']] .= 'M'.implode('L', array_map([self::class, 'p'], $l['p']));
            }
        }
        foreach ($data['openings'] ?? [] as $o) {
            [$ax, $ay] = $o['a'];
            [$bx, $by] = $o['b'];
            $len = hypot($bx - $ax, $by - $ay);
            if ($len < 1) {
                continue;
            }
            [$ux, $uy] = [($bx - $ax) / $len, ($by - $ay) / $len];
            [$nx, $ny] = [-$uy, $ux];
            $h = max((float) ($o['t'] ?? 45), 4) / 2;
            $at = fn (array $q, float $s) => [$q[0] + $nx * $s, $q[1] + $ny * $s];

            // the gap: the wall's fill and faces covered, a jamb at each end
            $c = $h + 3;
            $g['cut'] .= 'M'.self::p($at($o['a'], -$c)).'L'.self::p($at($o['b'], -$c)).'L'.self::p($at($o['b'], $c)).'L'.self::p($at($o['a'], $c)).'Z';
            $g['open'] .= 'M'.self::p($at($o['a'], -$h)).'L'.self::p($at($o['a'], $h)).'M'.self::p($at($o['b'], -$h)).'L'.self::p($at($o['b'], $h));

            if (($o['k'] ?? '') === 'door') {
                // hinged at a, swinging to the side `flip` picks, closing on b
                $s = empty($o['flip']) ? 1 : -1;
                $hinge = $at($o['a'], $s * $h);
                $end = $at($o['a'], $s * ($h + $len));
                $shut = $at($o['b'], $s * $h);
                $cross = ($end[0] - $hinge[0]) * ($shut[1] - $hinge[1]) - ($end[1] - $hinge[1]) * ($shut[0] - $hinge[0]);
                $r = round($len, 1);
                $g['leaf'] .= 'M'.self::p($hinge).'L'.self::p($end);
                $g['open'] .= 'M'.self::p($end).'A'.$r.' '.$r.' 0 0 '.($cross > 0 ? 1 : 0).' '.self::p($shut);
            } elseif (($o['k'] ?? '') === 'window') {
                $q = min(4, $h / 3);
                $g['open'] .= 'M'.self::p($at($o['a'], $h)).'L'.self::p($at($o['b'], $h)).'M'.self::p($at($o['a'], -$h)).'L'.self::p($at($o['b'], -$h));
                $g['glass'] .= 'M'.self::p($at($o['a'], $q)).'L'.self::p($at($o['b'], $q)).'M'.self::p($at($o['a'], -$q)).'L'.self::p($at($o['b'], -$q));
            }
        }

        return $g;
    }

    /** The rooms' names, in the page's language; one starting with @ is a word of journal/drawing's own (@court). */
    private function roomTexts(): array
    {
        return array_values(array_map(fn (array $r) => [
            round((float) $r['x'], 1), round((float) $r['y'], 1), 'p-room',
            str_starts_with((string) ($r['en'] ?? ''), '@') ? $r['en'] : bi($r['en'] ?? '', $r['ku'] ?? null, $r['ar'] ?? null), 'middle',
        ], array_filter($this->data['rooms'] ?? [], fn ($r) => filled($r['en'] ?? null) && isset($r['x'], $r['y']))));
    }

    /** Every point drawn, to size a new plan's sheet. */
    private function points(): array
    {
        $d = $this->data ?? [];
        $pts = array_merge(...array_values(array_filter($d['walls'] ?? [], 'is_array')), ...array_map(fn ($l) => $l['p'] ?? [], $d['lines'] ?? []));
        foreach ($d['openings'] ?? [] as $o) {
            $pts[] = $o['a'];
            $pts[] = $o['b'];
        }
        foreach ($d['rooms'] ?? [] as $r) {
            $pts[] = [$r['x'], $r['y']];
        }

        return array_values(array_filter($pts, fn ($p) => is_array($p) && count($p) > 1));
    }

    private static function p(array $q): string
    {
        return round((float) $q[0], 1).' '.round((float) $q[1], 1);
    }

    public function scanExists(): bool
    {
        return filled($this->scan) && Storage::disk(self::DISK)->exists($this->scan);
    }
}
