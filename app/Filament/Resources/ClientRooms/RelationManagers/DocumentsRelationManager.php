<?php

namespace App\Filament\Resources\ClientRooms\RelationManagers;

use App\Models\RoomDocument;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The room's documents shelf: contract, permits, invoices and other papers,
 * kept here for the client, and what the client adds themselves. The studio
 * can ask them to confirm they have read one, sees when they first opened
 * each, and is e-mailed 30 days before one expires.
 */
class DocumentsRelationManager extends RelationManager
{
    protected static string $relationship = 'documents';

    protected static ?string $title = 'Documents';

    protected static ?string $modelLabel = 'document';

    /** Documents expiring within 30 days, or expired. */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->documents()->whereNotNull('expires_on')
            ->whereDate('expires_on', '<=', today()->addDays(RoomDocument::REMIND_DAYS))->count();

        return $n ? (string) $n : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('group')->options(RoomDocument::GROUPS)->required()->default('contract'),
            TextInput::make('title')->label('Document')->required()->maxLength(160)
                ->placeholder('Design contract')->extraInputAttributes(['dir' => 'auto']),
            FileUpload::make('file_path')->label('The file — PDF or image, up to 30 MB')->required()
                ->disk(RoomDocument::DISK)->directory('rooms/documents')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(30720)->storeFileNamesIn('file_name')
                ->columnSpanFull(),
            Toggle::make('needs_reading')->label('Ask the client to confirm they have read it'),
            DatePicker::make('expires_on')->label('Expires on (optional)')
                ->helperText('You are e-mailed '.RoomDocument::REMIND_DAYS.' days before.'),
        ]);
    }

    public function table(Table $table): Table
    {
        $day = fn ($d) => $d->timezone('Asia/Baghdad')->format('j M Y');
        $seen = fn (array $data) => ['seen_at' => now()] + $data;

        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Document')->wrap()->extraAttributes(['dir' => 'auto'])
                    ->description(fn (RoomDocument $r) => $r->from_client ? 'Added by the client' : null),
                TextColumn::make('group')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state) => RoomDocument::GROUPS[$state] ?? $state),
                TextColumn::make('client')->label('The client')
                    ->state(fn (RoomDocument $r) => match (true) {
                        $r->from_client => 'Added '.$day($r->created_at),
                        $r->needs_reading && $r->read_at !== null => 'Read '.$day($r->read_at),
                        $r->needs_reading => 'Not read yet',
                        $r->first_opened_at !== null => 'Opened '.$day($r->first_opened_at),
                        default => 'Not opened yet',
                    })
                    ->color(fn (string $state) => str_starts_with($state, 'Not') ? 'gray' : 'success')
                    ->description(fn (RoomDocument $r) => $r->needs_reading && ! $r->read_at && $r->first_opened_at
                        ? 'Opened '.$day($r->first_opened_at) : null),
                TextColumn::make('expires_on')->label('Expires')->date('j M Y')->placeholder('—')
                    ->color(fn (RoomDocument $r) => $r->isExpired() ? 'danger' : ($r->expiresSoon() ? 'warning' : null)),
            ])
            ->headerActions([
                CreateAction::make()->label('Add a document')->mutateDataUsing($seen),
            ])
            ->recordActions([
                Action::make('openFile')->label('Open')
                    ->icon(Heroicon::OutlinedEye)->color('gray')
                    ->url(fn (RoomDocument $r) => route('filament.studio-aks-panel.room-document', $r), shouldOpenInNewTab: true),
                EditAction::make()->mutateDataUsing($seen),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No documents yet')
            ->emptyStateDescription('Keep the contract, permits and invoices here for the client, in their groups. Ask them to confirm they have read one, and give a permit its expiry date to be e-mailed '.RoomDocument::REMIND_DAYS.' days before. The client can add their own (a title deed, an ID) — you are e-mailed when they do.');
    }
}
