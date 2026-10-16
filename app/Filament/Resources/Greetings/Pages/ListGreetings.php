<?php

namespace App\Filament\Resources\Greetings\Pages;

use App\Filament\Resources\Greetings\GreetingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGreetings extends ListRecords
{
    protected static string $resource = GreetingResource::class;

    protected ?string $subheading = 'Between its dates, the homepage offers the card and visitors make it with their own name, to send on WhatsApp.';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
