<?php

namespace App\Filament\Forms\Components;

use App\Models\Drawing;
use Filament\Forms\Components\Field;

/**
 * The plan tracer (public/drawing-tracer.js): walls, openings, lines, rooms and north traced over
 * the drawing's private scan. Its state is the drawing's `data` (App\Models\Drawing says what is in it).
 */
class DrawingTracer extends Field
{
    protected string $view = 'filament.forms.components.drawing-tracer';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
    }

    /** The scan, through the admin-only route, or null. */
    public function getScanUrl(): ?string
    {
        $r = $this->getRecord();

        return $r instanceof Drawing && $r->scanExists()
            ? route('filament.studio-aks-panel.drawing-scan', $r).'?v='.substr(md5((string) $r->scan), 0, 8)
            : null;
    }

    /** For a correction: the original's walls as outlines, its other lines to trace against, and its sheet. */
    public function getBase(): ?array
    {
        $r = $this->getRecord();
        $dwg = $r instanceof Drawing && $r->base ? (Drawing::code()[$r->base] ?? null) : null;
        if (! $dwg) {
            return null;
        }

        return [
            'walls' => Drawing::outlines($dwg['d']['wall']),
            'ref' => implode('', array_map(fn ($g) => $dwg['d'][$g] ?? '', ['ctx', 'fix', 'stair', 'over', 'open', 'leaf', 'glass'])),
            'vb' => $dwg['vb'],
        ];
    }
}
