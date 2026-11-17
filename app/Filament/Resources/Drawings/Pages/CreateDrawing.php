<?php

namespace App\Filament\Resources\Drawings\Pages;

use App\Filament\Resources\Drawings\DrawingResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDrawing extends CreateRecord
{
    protected static string $resource = DrawingResource::class;

    // straight on to the tracer
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
