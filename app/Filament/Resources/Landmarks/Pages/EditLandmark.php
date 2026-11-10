<?php

namespace App\Filament\Resources\Landmarks\Pages;

use App\Filament\Resources\Landmarks\LandmarkResource;
use App\Models\Landmark;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditLandmark extends EditRecord
{
    protected static string $resource = LandmarkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the page as it is saved now, published or not — a signed link, good for a day
            Action::make('preview')->label('Preview')->icon(Heroicon::OutlinedEye)->color('gray')
                ->url(fn (Landmark $record) => $record->previewUrl(), shouldOpenInNewTab: true),
            Action::make('open')->label('Open page')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (Landmark $record) => $record->url('en'), shouldOpenInNewTab: true)
                ->visible(fn (Landmark $record) => $record->is_published),
            DeleteAction::make(),
        ];
    }
}
