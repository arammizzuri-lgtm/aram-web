<?php

namespace App\Filament\Forms\Components;

use App\Models\Project;
use Filament\Forms\Components\Field;

/**
 * Lets the editor choose which of a project's images is the grid cover and set
 * its focal point by clicking the spot to keep in view. It stores nothing
 * itself ({@see setUp()} marks it non-dehydrated); it drives the sibling
 * `cover`, `cover_x` and `cover_y` fields directly. Works from the saved
 * images, so upload + save first, then pick the cover here.
 */
class CoverPicker extends Field
{
    protected string $view = 'filament.forms.components.cover-picker';

    protected function setUp(): void
    {
        parent::setUp();
        $this->dehydrated(false);
    }

    /** @return array<int, array{value: string, url: ?string}> */
    public function getImages(): array
    {
        $record = $this->getRecord();
        if (! $record instanceof Project) {
            return [];
        }

        return collect($record->imgs ?? [])
            ->map(fn ($img) => ['value' => (string) $img, 'url' => $record->thumbUrl($img)])
            ->filter(fn ($i) => $i['url'] !== null && $i['url'] !== '')
            ->values()
            ->all();
    }
}
