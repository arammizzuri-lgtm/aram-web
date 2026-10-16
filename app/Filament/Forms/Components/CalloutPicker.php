<?php

namespace App\Filament\Forms\Components;

use App\Models\Project;
use Filament\Forms\Components\Field;

/**
 * Notes on a project's photographs, placed the way a drawing's callouts are
 * (the project page draws them as numbered points with a leader to the
 * words): pick a photo, click the spot, write the note in English and
 * Kurdish; drag a point to move it. Its state is the `callouts` list —
 * [{img, x, y, en, ku}] — kept in step by Alpine (callout-picker-assets).
 * Works from the saved photographs, like the cover picker.
 */
class CalloutPicker extends Field
{
    protected string $view = 'filament.forms.components.callout-picker';

    protected function setUp(): void
    {
        parent::setUp();
        $this->default([]);
    }

    /** @return array<int, array{value: string, thumb: ?string, large: ?string}> */
    public function getImages(): array
    {
        $record = $this->getRecord();
        if (! $record instanceof Project) {
            return [];
        }

        return collect($record->imgs ?? [])
            ->map(fn ($img) => [
                'value' => (string) $img,
                'thumb' => $record->variantUrl($img, 'sm'),
                'large' => $record->variantUrl($img, 'thumb'),
            ])
            ->filter(fn ($i) => filled($i['thumb']))
            ->values()->all();
    }
}
