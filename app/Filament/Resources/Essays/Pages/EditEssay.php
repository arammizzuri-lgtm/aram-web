<?php

namespace App\Filament\Resources\Essays\Pages;

use App\Filament\Resources\Essays\EssayResource;
use App\Models\Essay;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditEssay extends EditRecord
{
    protected static string $resource = EssayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // the page as it is saved now, published or not — a signed link, good for a day
            Action::make('preview')->label('Preview')->icon(Heroicon::OutlinedEye)->color('gray')
                ->url(fn (Essay $record) => $record->previewUrl(), shouldOpenInNewTab: true),
            Action::make('open')->label('Open page')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->url(fn (Essay $record) => $record->url('en'), shouldOpenInNewTab: true)
                ->visible(fn (Essay $record) => $record->is_published),
            DeleteAction::make(),
        ];
    }
}
