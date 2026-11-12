<?php

namespace App\Filament\Resources\Presentations;

use App\Filament\Resources\Presentations\Pages\ManagePresentations;
use App\Models\Presentation;
use App\Models\PresentationPreset;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Project presentations: the list of them — make, rename, duplicate, delete,
 * find by project or client. A presentation itself is worked on in the
 * full-screen editor (PresentationController, public/js/presentations).
 */
class PresentationResource extends Resource
{
    protected static ?string $model = Presentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Clients';

    protected static ?int $navigationSort = 8;

    protected static ?string $navigationLabel = 'Project presentations';

    protected static ?string $modelLabel = 'presentation';

    protected static ?string $pluralModelLabel = 'project presentations';

    /** The three templates that ship with the editor (public/js/presentations/model.js). */
    public const TEMPLATES = [
        'minimal-white' => 'Minimal white',
        'editorial' => 'Editorial architecture',
        'dark-portfolio' => 'Dark portfolio',
    ];

    /** Renaming: the one thing changed outside the editor. */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Name')->required()->maxLength(160)->columnSpanFull(),
        ]);
    }

    /** A new presentation: its name, the template it starts in and its page format. */
    public static function newForm(): array
    {
        $defaults = json_decode(PresentationPreset::brand()->data, true)['defaults'] ?? [];
        $saved = PresentationPreset::where('type', 'theme')->orderBy('name')->pluck('name', 'id')
            ->mapWithKeys(fn (string $name, int $id) => ['saved:'.$id => $name.' (yours)'])->all();

        return [
            TextInput::make('name')->label('Project name')->required()->maxLength(160)
                ->placeholder('Villa in Ankawa')->columnSpanFull()
                ->helperText('It goes on the cover; everything can be changed in the editor.'),
            Select::make('theme')->label('Template')->options(self::TEMPLATES + $saved)->native(false)
                ->default($defaults['theme'] ?? 'minimal-white')->selectablePlaceholder(false)->required()->columnSpanFull(),
            Select::make('size')->label('Page size')->options(['A3' => 'A3', 'A4' => 'A4'])->native(false)
                ->default($defaults['size'] ?? 'A3')->selectablePlaceholder(false)->required(),
            Select::make('orient')->label('Orientation')->options(['landscape' => 'Landscape', 'portrait' => 'Portrait'])->native(false)
                ->default($defaults['orient'] ?? 'landscape')->selectablePlaceholder(false)->required(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->recordUrl(fn (Presentation $record) => $record->editorUrl())
            ->columns([
                TextColumn::make('name')->label('Presentation')->wrap()->sortable()
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(fn (Builder $q) => $q
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('project_name', 'like', "%{$search}%")
                        ->orWhere('client_name', 'like', "%{$search}%")))
                    ->description(fn (Presentation $r) => $r->project_name !== $r->name ? $r->project_name : null),
                TextColumn::make('client_name')->label('Client')->placeholder('—')->sortable()->visibleFrom('md'),
                TextColumn::make('page_count')->label('Pages')->alignEnd()->visibleFrom('md'),
                TextColumn::make('updated_at')->label('Last worked on')->since()->sortable(),
            ])
            ->recordActions([
                Action::make('open')->label('Open')->icon(Heroicon::OutlinedPencilSquare)
                    ->url(fn (Presentation $record) => $record->editorUrl()),
                EditAction::make()->label('Rename')->icon(Heroicon::OutlinedTag)->modalHeading('Rename the presentation'),
                Action::make('duplicate')->label('Duplicate')->icon(Heroicon::OutlinedDocumentDuplicate)->color('gray')
                    ->action(function (Presentation $record) {
                        $copy = $record->duplicate();
                        Notification::make()->success()->title('“'.$copy->name.'” is ready')
                            ->body('Open it and put the new project\'s information and drawings in — the original is not touched.')->send();
                    }),
                DeleteAction::make()
                    ->modalDescription('Its pages are deleted for good. A file another presentation also uses is kept for that one.'),
            ])
            ->emptyStateHeading('No presentations yet')
            ->emptyStateDescription('Make one for a project: pick a template, fill in the project\'s information, upload the drawings and renders, and download a PDF for the client.');
    }

    public static function getPages(): array
    {
        return ['index' => ManagePresentations::route('/')];
    }
}
