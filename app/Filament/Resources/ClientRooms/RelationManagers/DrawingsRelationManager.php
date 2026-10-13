<?php

namespace App\Filament\Resources\ClientRooms\RelationManagers;

use App\Models\RoomDrawing;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The drawings sent to this client, and what they decided. A decision is a
 * record and stays as it was given: a revision goes out as a new drawing.
 */
class DrawingsRelationManager extends RelationManager
{
    protected static string $relationship = 'drawings';

    protected static ?string $title = 'Drawings';

    protected static ?string $modelLabel = 'drawing';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')->required()->maxLength(160)
                ->placeholder('Ground floor plan — option B')
                ->extraInputAttributes(['dir' => 'auto'])->columnSpanFull(),
            Textarea::make('note')->label('A note for the client (optional)')->rows(3)
                ->extraInputAttributes(['dir' => 'auto'])->columnSpanFull(),
            FileUpload::make('file_path')->label('The drawing — PDF or image, up to 30 MB')->required()
                ->disk(RoomDrawing::DISK)->directory('rooms/drawings')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(30720)->storeFileNamesIn('file_name')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->wrap()
                    ->description(fn (RoomDrawing $r) => $r->file_name),
                TextColumn::make('created_at')->label('Sent')->date('j M Y'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => RoomDrawing::statuses()[$state] ?? $state)
                    ->color(fn (string $state) => [RoomDrawing::APPROVED => 'success', RoomDrawing::CHANGES => 'danger'][$state] ?? 'warning')
                    ->description(fn (RoomDrawing $r) => $r->decided_at
                        ? $r->decided_at->timezone('Asia/Baghdad')->format('j M Y, H:i').' (Erbil)' : null),
                TextColumn::make('decision_note')->label('Their words')->wrap()->placeholder('—')
                    ->extraAttributes(['dir' => 'auto']),
            ])
            ->headerActions([
                CreateAction::make()->label('Send a drawing'),
            ])
            ->recordActions([
                Action::make('openFile')->label('Open')
                    ->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn (RoomDrawing $r) => route('filament.studio-aks-panel.room-drawing', $r), shouldOpenInNewTab: true),
                EditAction::make()->visible(fn (RoomDrawing $r) => $r->isPending()),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No drawings yet')
            ->emptyStateDescription('Send one: the client opens it in their room, and approves it or asks for changes. You are e-mailed either way.');
    }
}
