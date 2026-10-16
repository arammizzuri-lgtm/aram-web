<?php

namespace App\Filament\Resources\Greetings\Pages;

use App\Filament\Resources\Greetings\GreetingResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditGreeting extends EditRecord
{
    protected static string $resource = GreetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')->label('Preview the card page')->icon(Heroicon::OutlinedEye)->color('gray')
                ->url(fn () => site_url('/card', 'en').'?greeting='.$this->record->id, shouldOpenInNewTab: true),
            DeleteAction::make(),
        ];
    }
}
