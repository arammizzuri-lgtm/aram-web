<?php

namespace App\Filament\Resources\Essays\Pages;

use App\Filament\Resources\Essays\EssayResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEssay extends CreateRecord
{
    protected static string $resource = EssayResource::class;
}
