<?php

namespace App\Filament\Resources\Presentations\Pages;

use App\Filament\Resources\Presentations\PresentationResource;
use App\Models\Presentation;
use App\Models\Project;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Icons\Heroicon;

class ManagePresentations extends ManageRecords
{
    protected static string $resource = PresentationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('brand')->label('Branding & templates')->icon(Heroicon::OutlinedSwatch)->color('gray')
                ->url(route('filament.studio-aks-panel.presentation-brand')),
            CreateAction::make()->label('New presentation')
                ->modalHeading('A presentation for a project')
                ->schema(fn (): array => PresentationResource::newForm())
                ->createAnother(false)
                ->using(function (array $data) {
                    $presentation = Presentation::create([
                        'name' => $data['name'],
                        'project_name' => $data['name'],
                        'doc' => Presentation::blank($data['name'], $data['theme'], $data['size'], $data['orient'], $data['lang'] ?? 'en'),
                    ]);
                    if ($project = Project::find($data['project'] ?? null)) {
                        $presentation->seedFrom($project);
                    }

                    return $presentation;
                })
                // straight into the editor
                ->successNotification(null)
                ->successRedirectUrl(fn (Presentation $record) => $record->editorUrl()),
        ];
    }
}
