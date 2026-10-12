<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * Interactive Leaflet map for choosing a project's pin position by clicking /
 * dragging, instead of typing coordinates. It does not persist a value itself
 * ({@see setUp()} marks it non-dehydrated); it reads and writes the sibling
 * `lat` / `lng` fields directly via Livewire, so the same pin drives the
 * public site map. Erbil-centred with the site's dark CartoDB tiles.
 */
class MapPicker extends Field
{
    protected string $view = 'filament.forms.components.map-picker';

    /** Default map centre — Erbil, Kurdistan Region. */
    public array $center = [36.19, 44.01];

    public int $zoom = 8;

    protected function setUp(): void
    {
        parent::setUp();

        // The map drives the sibling lat/lng fields; it has no own value to save.
        $this->dehydrated(false);
    }

    public function getCenter(): array
    {
        return $this->center;
    }

    public function getZoom(): int
    {
        return $this->zoom;
    }

    /**
     * The keyed CARTO tile URL, or null when CARTO_BASEMAPS_KEY is unset —
     * keyless CARTO tiles are only an "API KEY REQUIRED" watermark.
     */
    public function getTiles(): ?string
    {
        return carto_tiles('dark_all');
    }
}
