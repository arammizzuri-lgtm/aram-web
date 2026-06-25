<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Resources\Projects\Schemas\ProjectForm;
use App\Filament\Resources\Projects\Tables\ProjectsTable;
use App\Models\Project;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|UnitEnum|null $navigationGroup = 'Manage';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ProjectForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProjectsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    /**
     * Split the stored imgs array into two form fields: uploaded files (bare
     * paths) and pasted URLs. Used when filling the edit form.
     */
    public static function splitImages(array $data): array
    {
        $imgs = $data['imgs'] ?? [];
        $isUrl = fn ($i) => is_string($i) && preg_match('#^https?://#', $i);

        $data['uploads'] = array_values(array_filter($imgs, fn ($i) => ! $isUrl($i)));
        $data['image_links'] = array_values(array_map(
            fn ($u) => ['url' => $u],
            array_filter($imgs, $isUrl),
        ));

        return $data;
    }

    /**
     * Merge the two image form fields back into the single imgs array
     * (uploads first, then URLs) before saving.
     */
    public static function mergeImages(array $data): array
    {
        $uploads = array_values($data['uploads'] ?? []);
        $links = array_values(array_filter(array_map(
            fn ($row) => $row['url'] ?? null,
            $data['image_links'] ?? [],
        )));

        $data['imgs'] = array_values(array_merge($uploads, $links));
        unset($data['uploads'], $data['image_links']);

        return $data;
    }
}
