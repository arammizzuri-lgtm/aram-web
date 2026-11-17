<?php

namespace App\Filament\Resources\Drawings\Pages;

use App\Filament\Resources\Drawings\DrawingResource;
use App\Models\Drawing;
use App\Support\Articles;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditDrawing extends EditRecord
{
    protected static string $resource = DrawingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // a correction taken off the essay: the original's walls are drawn again, the tracing is kept
            Action::make('original')->label('Back to the original')->icon(Heroicon::OutlinedArrowUturnLeft)->color('gray')
                ->visible(fn (Drawing $record) => $record->base && $record->is_live)
                ->requiresConfirmation()
                ->modalDescription('The essay shows the original walls again. Your tracing is kept here, to put live again later.')
                ->action(function (Drawing $record) {
                    $record->update(['is_live' => false]);
                    $this->refreshFormData(['is_live']);
                    Notification::make()->title('The essay shows the original walls again')->success()->send();
                }),
            Action::make('open')->label('Open the essay')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                ->visible(fn (Drawing $record) => (bool) $record->base)
                ->url(function (Drawing $record) {
                    foreach (Articles::all() as $a) {
                        if (isset(Articles::raw($a)[$record->base])) {
                            return Articles::url($a, 'en');
                        }
                    }

                    return null;
                }, shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
