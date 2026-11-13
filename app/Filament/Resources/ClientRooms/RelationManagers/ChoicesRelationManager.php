<?php

namespace App\Filament\Resources\ClientRooms\RelationManagers;

use App\Models\ClientRoom;
use App\Models\RoomChoice;
use App\Models\RoomChoiceOption;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Finish choices: the options for one decision — a worktop, tiles, a
 * colour — each with a picture, what it adds to the price and its supplier.
 * The client picks one in their room, final and dated; reopening it here
 * lets them pick again. An extra cost becomes a payment stage.
 */
class ChoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'choices';

    protected static ?string $title = 'Choices';

    protected static ?string $modelLabel = 'choice';

    public function form(Schema $schema): Schema
    {
        $usd = $this->getOwnerRecord()->currency !== 'IQD';

        return $schema->components([
            TextInput::make('title')->label('What to choose')->required()->maxLength(160)
                ->placeholder('Kitchen worktop')
                ->extraInputAttributes(['dir' => 'auto'])->columnSpanFull(),
            Textarea::make('note')->label('A note for the client (optional)')->rows(2)
                ->extraInputAttributes(['dir' => 'auto'])->columnSpanFull(),
            Repeater::make('options')->relationship()->label('Options')
                ->orderColumn('sort_order')->minItems(2)->defaultItems(2)
                ->columns(['default' => 1, 'md' => 2])
                ->schema([
                    TextInput::make('name')->required()->maxLength(160)->placeholder('Calacatta marble')
                        ->extraInputAttributes(['dir' => 'auto']),
                    TextInput::make('extra_cost')->label('Adds to the price')->numeric()->minValue(0)->default(0)->required()
                        ->prefix($usd ? '$' : null)->suffix($usd ? null : 'IQD')
                        ->helperText('0 if it is included. Chosen, an extra cost becomes a payment stage.'),
                    Select::make('partner_id')->label('Supplier (optional)')
                        ->relationship('partner', 'name', fn ($query) => $query->where('is_application', false)->orderBy('name'))
                        ->searchable()->preload()
                        ->helperText('From Partners: the client sees its name and phone.'),
                    Textarea::make('details')->label('Details (optional)')->rows(2)
                        ->extraInputAttributes(['dir' => 'auto']),
                    FileUpload::make('image_path')->label('Picture (optional)')->image()
                        ->disk(RoomChoiceOption::DISK)->directory('rooms/choices')->visibility('private')
                        ->maxSize(10240)->columnSpanFull(),
                ])
                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                ->addActionLabel('Add an option')
                ->collapsible()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var ClientRoom $room */
        $room = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('id')
            ->modifyQueryUsing(fn ($query) => $query->with('chosen.partner')->withCount('options'))
            ->columns([
                TextColumn::make('title')->label('Choice')->wrap()
                    ->description(fn (RoomChoice $r) => $r->options_count.' options')
                    ->extraAttributes(['dir' => 'auto']),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => $state === RoomChoice::CHOSEN ? 'Chosen' : 'Waiting for the client')
                    ->color(fn (string $state) => $state === RoomChoice::CHOSEN ? 'success' : 'warning')
                    ->description(fn (RoomChoice $r) => $r->decided_at
                        ? $r->decided_at->timezone('Asia/Baghdad')->format('j M Y, H:i').' (Erbil)' : null),
                TextColumn::make('chosen.name')->label('Their pick')->placeholder('—')->wrap()
                    ->description(fn (RoomChoice $r) => $r->chosen ? collect([
                        $r->chosen->extra_cost > 0 ? '+'.$room->money($r->chosen->extra_cost, 'en') : 'Included',
                        $r->chosen->partner?->name,
                    ])->filter()->join(' · ') : null)
                    ->extraAttributes(['dir' => 'auto']),
                TextColumn::make('client_note')->label('Their note')->wrap()->placeholder('—')
                    ->extraAttributes(['dir' => 'auto']),
            ])
            ->headerActions([
                CreateAction::make()->label('Add a choice')
                    ->mutateDataUsing(fn (array $data) => ['seen_at' => now()] + $data),
            ])
            ->recordActions([
                Action::make('reopen')->label('Reopen')
                    ->icon(Heroicon::OutlinedArrowPath)->color('warning')
                    ->visible(fn (RoomChoice $r) => ! $r->isPending())
                    ->requiresConfirmation()
                    ->modalDescription('The client picks again. The payment stage for its extra cost is removed, unless it has been paid.')
                    ->action(function (RoomChoice $r) {
                        $keptPaid = $r->reopen();
                        Notification::make()->success()->title('Reopened: the client can choose again')
                            ->body($keptPaid ? 'Its payment stage had been paid, so it stays — settle it with the client.' : null)
                            ->send();
                    }),
                EditAction::make()->visible(fn (RoomChoice $r) => $r->isPending()),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No choices yet')
            ->emptyStateDescription('Set out the options for a finish — a worktop, tiles, a colour — with pictures, prices and suppliers. The client picks one in their room; you are e-mailed, and an extra cost becomes a payment stage.');
    }
}
