<?php

namespace App\Filament\Resources\JobOpenings\Pages;

use App\Filament\Resources\JobOpenings\JobOpeningResource;
use Filament\Resources\Pages\CreateRecord;

class CreateJobOpening extends CreateRecord
{
    protected static string $resource = JobOpeningResource::class;
}
