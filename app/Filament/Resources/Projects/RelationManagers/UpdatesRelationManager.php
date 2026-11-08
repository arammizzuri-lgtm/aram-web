<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Models\ProjectUpdate;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Construction progress, under the project it belongs to: a dated note and
 * a few site photographs per visit. Shown newest first on the project page —
 * or, marked private, only in the project's client rooms.
 */
class UpdatesRelationManager extends RelationManager
{
    protected static string $relationship = 'updates';

    protected static ?string $title = 'Construction progress';

    protected static ?string $modelLabel = 'progress update';

    public function form(Schema $schema): Schema
    {
        $rtl = ['dir' => 'rtl', 'lang' => 'ckb'];

        return $schema->columns(2)->components([
            DatePicker::make('date')->required()->default(now())->native(false)
                ->displayFormat('j M Y')->columnSpanFull(),
            TextInput::make('title_en')->label('Headline (English)')->maxLength(160)
                ->placeholder('Structure complete to roof level'),
            TextInput::make('title_ku')->label('Headline (Kurdish)')->maxLength(160)
                ->extraInputAttributes($rtl),
            TextInput::make('title_ar')->label('Headline (Arabic)')->maxLength(160)
                ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])->columnSpanFull(),
            Textarea::make('note_en')->label('Note (English)')->rows(3),
            Textarea::make('note_ku')->label('Note (Kurdish)')->rows(3)
                ->extraInputAttributes($rtl),
            Textarea::make('note_ar')->label('Note (Arabic)')->rows(3)
                ->extraInputAttributes(['dir' => 'rtl', 'lang' => 'ar'])->columnSpanFull(),
            FileUpload::make('images')->label('Site photographs')
                ->multiple()->image()->reorderable()->appendFiles()
                ->directory('projects/updates')->disk('public')->visibility('public')
                ->panelLayout('grid')->imagePreviewHeight('110')->openable()
                ->maxFiles(12)
                ->columnSpanFull(),
            Toggle::make('is_private')->label('Private — only in this project\'s client rooms')
                ->helperText('Left off, the update is on the project\'s public page too (if the project is published).')
                ->live()->columnSpanFull(),
            // the build journal (/journal): this update as a post of its own
            Toggle::make('in_journal')->label('Publish in the Journal')
                ->helperText('Also shown as a post on the studio\'s build journal (/journal), with its own page. Ask the owner first. Not possible for a private update.')
                ->disabled(fn (callable $get) => (bool) $get('is_private'))
                ->dehydrateStateUsing(fn ($state, callable $get) => (bool) $state && ! $get('is_private'))
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title_en')
            ->defaultSort('date', 'desc')
            ->columns([
                ImageColumn::make('preview')->label('')
                    ->state(fn (ProjectUpdate $r) => $r->imageUrls('sm')[0] ?? null)
                    ->height(44)->extraImgAttributes(['style' => 'width:66px;object-fit:cover;border-radius:6px;']),
                TextColumn::make('date')->date('j M Y')->sortable(),
                TextColumn::make('title_en')->label('Headline')->placeholder('—')->wrap()
                    ->description(fn (ProjectUpdate $r) => $r->note_en ? \Illuminate\Support\Str::limit($r->note_en, 80) : null),
                TextColumn::make('photos')->label('Photos')
                    ->state(fn (ProjectUpdate $r) => count((array) $r->images)),
                IconColumn::make('in_journal')->label('Journal')->boolean()
                    ->trueIcon('heroicon-o-newspaper')->falseIcon('heroicon-o-minus')
                    ->trueColor('success')->falseColor('gray'),
                IconColumn::make('is_private')->label('Private')->boolean()
                    ->trueIcon('heroicon-o-lock-closed')->falseIcon('heroicon-o-globe-alt')
                    ->trueColor('warning')->falseColor('gray'),
            ])
            ->headerActions([
                CreateAction::make()->label('Add progress update'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No progress updates yet')
            ->emptyStateDescription('For a project being built: add a dated note and a few site photos after each visit. They appear on the project page, newest first — or, marked private, only in its client rooms.');
    }
}
