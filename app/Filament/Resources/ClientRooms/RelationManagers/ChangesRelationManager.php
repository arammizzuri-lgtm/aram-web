<?php

namespace App\Filament\Resources\ClientRooms\RelationManagers;

use App\Models\ClientRoom;
use App\Models\RoomChange;
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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Changes with a price: offered to the client here, or asked for by them in
 * their room and given a price here. They accept — and the price becomes a
 * payment stage of its own — or decline, saying why. A decided change stays
 * as it was.
 */
class ChangesRelationManager extends RelationManager
{
    protected static string $relationship = 'changes';

    protected static ?string $title = 'Changes';

    protected static ?string $modelLabel = 'change';

    /** Changes the client asked for that still need a price. */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->changes()->where('status', RoomChange::ASKED)->count();

        return $n ? (string) $n : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function form(Schema $schema): Schema
    {
        $usd = $this->getOwnerRecord()->currency !== 'IQD';

        return $schema->components([
            TextInput::make('title')->label('The change')->required()->maxLength(160)
                ->placeholder('A wider kitchen window')
                ->extraInputAttributes(['dir' => 'auto'])->columnSpanFull(),
            Textarea::make('details')->rows(3)->columnSpanFull()
                ->label(fn (?RoomChange $record) => $record?->asked_by_client ? 'What they asked' : 'What it involves (optional)')
                ->disabled(fn (?RoomChange $record) => (bool) $record?->asked_by_client)
                ->extraInputAttributes(['dir' => 'auto']),
            TextInput::make('price')->numeric()->minValue(0)->required()
                ->prefix($usd ? '$' : null)->suffix($usd ? null : 'IQD')
                ->helperText('0 if it costs nothing extra. Accepted, it becomes a payment stage of its own.'),
            TextInput::make('extra_days')->label('Days it adds')->integer()->minValue(0)->maxValue(3650)->placeholder('0'),
            Textarea::make('note')->label('A note for the client (optional)')->rows(2)
                ->extraInputAttributes(['dir' => 'auto'])->columnSpanFull(),
            FileUpload::make('file_path')->label('A picture or drawing of it (optional) — PDF or image, up to 30 MB')
                ->disk(RoomChange::DISK)->directory('rooms/changes')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(30720)->storeFileNamesIn('file_name')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var ClientRoom $room */
        $room = $this->getOwnerRecord();
        // whatever the studio saves is priced, and read: it waits for the client
        $priced = fn (array $data) => ['status' => RoomChange::PRICED, 'seen_at' => now()] + $data;

        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Change')->wrap()
                    ->description(fn (RoomChange $r) => $r->details ? Str::limit($r->details, 160) : null)
                    ->extraAttributes(['dir' => 'auto']),
                TextColumn::make('asked_by_client')->label('From')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'The client' : 'The studio')
                    ->color(fn (bool $state) => $state ? 'info' : 'gray'),
                TextColumn::make('price')->placeholder('—')
                    ->formatStateUsing(fn ($state) => $room->money($state, 'en'))
                    ->description(fn (RoomChange $r) => $r->extra_days ? '+'.$r->extra_days.' days' : null),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => RoomChange::statuses()[$state] ?? $state)
                    ->color(fn (string $state) => [RoomChange::ASKED => 'danger', RoomChange::ACCEPTED => 'success', RoomChange::DECLINED => 'gray'][$state] ?? 'warning')
                    ->description(fn (RoomChange $r) => $r->decided_at
                        ? $r->decided_at->timezone('Asia/Baghdad')->format('j M Y, H:i').' (Erbil)' : null),
                TextColumn::make('decline_note')->label('Why they declined')->wrap()->placeholder('—')
                    ->extraAttributes(['dir' => 'auto']),
            ])
            ->headerActions([
                CreateAction::make()->label('Offer a change')->mutateDataUsing($priced),
            ])
            ->recordActions([
                Action::make('openFile')->label('Open')
                    ->icon(Heroicon::OutlinedEye)->color('gray')
                    ->visible(fn (RoomChange $r) => filled($r->file_path))
                    ->url(fn (RoomChange $r) => route('filament.studio-aks-panel.room-change', $r), shouldOpenInNewTab: true),
                EditAction::make()
                    ->label(fn (RoomChange $r) => $r->status === RoomChange::ASKED ? 'Give it a price' : 'Edit')
                    ->icon(fn (RoomChange $r) => $r->status === RoomChange::ASKED ? Heroicon::OutlinedCurrencyDollar : Heroicon::OutlinedPencilSquare)
                    ->visible(fn (RoomChange $r) => in_array($r->status, [RoomChange::ASKED, RoomChange::PRICED], true))
                    ->mutateDataUsing($priced),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No changes yet')
            ->emptyStateDescription('Offer one with its price and the days it adds — or price one the client asks for in their room. They accept it (it becomes a payment stage of its own) or decline it, saying why. You are e-mailed either way.');
    }
}
