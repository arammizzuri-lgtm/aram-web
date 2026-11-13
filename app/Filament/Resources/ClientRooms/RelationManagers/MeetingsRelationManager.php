<?php

namespace App\Filament\Resources\ClientRooms\RelationManagers;

use App\Models\RoomMeeting;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Meeting notes: written here after a meeting, numbered decisions and a
 * photo or PDF if there is one. The client agrees in their room, or says
 * what is not right; corrected here, the notes are asked again. Agreed
 * notes stay as they were.
 */
class MeetingsRelationManager extends RelationManager
{
    protected static string $relationship = 'meetings';

    protected static ?string $title = 'Meetings';

    protected static ?string $modelLabel = 'meeting';

    /** Notes the client says are not right, still to correct. */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $n = $ownerRecord->meetings()->where('status', RoomMeeting::OBJECTED)->count();

        return $n ? (string) $n : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'danger';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('met_on')->label('Date of the meeting')->required()->default(now('Asia/Baghdad')->toDateString()),
            TextInput::make('title')->label('Title (optional)')->maxLength(160)
                ->placeholder('Site visit — kitchen')->extraInputAttributes(['dir' => 'auto']),
            TextInput::make('attendees')->label('Who came (optional)')->maxLength(255)->columnSpanFull()
                ->placeholder('Mr Karwan, Aram, the contractor')->extraInputAttributes(['dir' => 'auto']),
            Textarea::make('decisions')->label('What was decided — one per line, shown numbered')
                ->required()->rows(6)->maxLength(10000)->columnSpanFull()
                ->extraInputAttributes(['dir' => 'auto']),
            FileUpload::make('file_path')->label('A photo or PDF of the notes (optional) — up to 30 MB')
                ->disk(RoomMeeting::DISK)->directory('rooms/meetings')->visibility('private')
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(30720)->storeFileNamesIn('file_name')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        // whatever the studio saves waits for the client again, with their old answer gone (it is in the e-mail)
        $ask = fn (array $data) => ['status' => RoomMeeting::PENDING, 'client_note' => null, 'decided_at' => null, 'decided_ip' => null, 'seen_at' => now()] + $data;

        return $table
            ->defaultSort('met_on', 'desc')
            ->columns([
                TextColumn::make('met_on')->label('Meeting')->date('j M Y')
                    ->description(fn (RoomMeeting $r) => $r->title),
                TextColumn::make('decisions')->label('Decided')->wrap()
                    ->formatStateUsing(fn (RoomMeeting $r) => collect($r->decisionList())->map(fn ($d, $i) => ($i + 1).'. '.$d)->join("\n"))
                    ->extraAttributes(['dir' => 'auto', 'style' => 'white-space: pre-line'])
                    ->description(fn (RoomMeeting $r) => $r->attendees ? 'Present: '.$r->attendees : null, position: 'above'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => RoomMeeting::statuses()[$state] ?? $state)
                    ->color(fn (string $state) => [RoomMeeting::AGREED => 'success', RoomMeeting::OBJECTED => 'danger'][$state] ?? 'warning')
                    ->description(fn (RoomMeeting $r) => $r->decided_at
                        ? $r->decided_at->timezone('Asia/Baghdad')->format('j M Y, H:i').' (Erbil)' : null),
                TextColumn::make('client_note')->label('What is not right')->wrap()->placeholder('—')
                    ->extraAttributes(['dir' => 'auto']),
            ])
            ->headerActions([
                CreateAction::make()->label('Write meeting notes')->mutateDataUsing($ask),
            ])
            ->recordActions([
                Action::make('openFile')->label('Open')
                    ->icon(Heroicon::OutlinedEye)->color('gray')
                    ->visible(fn (RoomMeeting $r) => filled($r->file_path))
                    ->url(fn (RoomMeeting $r) => route('filament.studio-aks-panel.room-meeting', $r), shouldOpenInNewTab: true),
                EditAction::make()
                    ->label(fn (RoomMeeting $r) => $r->status === RoomMeeting::OBJECTED ? 'Correct and ask again' : 'Edit')
                    ->visible(fn (RoomMeeting $r) => $r->status !== RoomMeeting::AGREED)
                    ->mutateDataUsing($ask),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No meeting notes yet')
            ->emptyStateDescription('After a meeting, write down what was decided, one point per line, and add a photo or PDF of the notes if you have one. The client agrees in their room or says what is not right — you are e-mailed either way.');
    }
}
