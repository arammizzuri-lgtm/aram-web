<?php

namespace App\Filament\Resources\ClientRooms\RelationManagers;

use App\Models\ClientRoom;
use App\Models\ProjectUpdate;
use App\Models\RoomComment;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The conversation in this room, under the project's progress updates: what
 * the client wrote, and the studio's answers — which appear in the room.
 */
class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = 'Conversation';

    protected static ?string $modelLabel = 'message';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('projectUpdate'))
            ->columns([
                TextColumn::make('under')->label('Under the update')->wrap()
                    ->state(fn (RoomComment $r) => self::updateLabel($r->projectUpdate)),
                TextColumn::make('from_studio')->label('From')->badge()
                    ->formatStateUsing(fn (bool $state) => $state ? 'Studio' : $this->room()->client_name)
                    ->color(fn (bool $state) => $state ? 'gray' : 'primary'),
                TextColumn::make('body')->label('Message')->wrap()->extraAttributes(['dir' => 'auto']),
                TextColumn::make('created_at')->label('When')->since()
                    ->tooltip(fn (RoomComment $r) => $r->created_at->timezone('Asia/Baghdad')->format('j M Y, H:i').' (Erbil)'),
            ])
            ->headerActions([
                Action::make('write')->label('Write to the client')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->schema([
                        Select::make('project_update_id')->label('Under the update')->required()->native(false)
                            ->options(fn () => $this->room()->updates()
                                ->mapWithKeys(fn (ProjectUpdate $u) => [$u->id => self::updateLabel($u)])->all()),
                        Textarea::make('body')->label('Message')->required()->rows(4)->maxLength(2000)
                            ->extraInputAttributes(['dir' => 'auto']),
                    ])
                    ->action(fn (array $data) => $this->post((int) $data['project_update_id'], $data['body'])),
            ])
            ->recordActions([
                Action::make('reply')->label('Answer')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->visible(fn (RoomComment $r) => ! $r->from_studio)
                    ->modalDescription(fn (RoomComment $r) => '“'.\Illuminate\Support\Str::limit($r->body, 200).'”')
                    ->schema([
                        Textarea::make('body')->label('Your answer')->required()->rows(4)->maxLength(2000)
                            ->extraInputAttributes(['dir' => 'auto']),
                    ])
                    ->action(fn (array $data, RoomComment $record) => $this->post($record->project_update_id, $data['body'])),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No conversation yet')
            ->emptyStateDescription('The client can comment under any progress update in their room; you answer here, and they see it there.');
    }

    private function room(): ClientRoom
    {
        /** @var ClientRoom */
        return $this->getOwnerRecord();
    }

    /** The studio's words, under an update of this room's project. */
    private function post(int $updateId, string $body): void
    {
        abort_unless($this->room()->project->updates()->whereKey($updateId)->exists(), 404);

        $this->room()->comments()->create([
            'project_update_id' => $updateId,
            'from_studio' => true,
            'body' => trim($body),
            'seen_at' => now(),
        ]);
        Notification::make()->success()->title('Posted in the room')
            ->body('Use WhatsApp → "Tell them there is something new" to let them know.')->send();
    }

    private static function updateLabel(?ProjectUpdate $u): string
    {
        if (! $u) {
            return '—';
        }

        return $u->date->format('j M Y').(filled($u->title_en ?: $u->title_ku) ? ' — '.($u->title_en ?: $u->title_ku) : '')
            .($u->is_private ? ' (private)' : '');
    }
}
