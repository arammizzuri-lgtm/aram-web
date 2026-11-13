<?php

namespace App\Filament\Resources\ClientRooms\RelationManagers;

use App\Models\ClientRoom;
use App\Models\RoomChange;
use App\Models\RoomStage;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The room's timeline: its stages, from the standard list or the studio's
 * own, each with dates planned and dates it really started and finished,
 * and how far along it is. Changes the client accepts push the planned dates
 * by their extra days. The client sees it at the top of their room.
 */
class StagesRelationManager extends RelationManager
{
    protected static string $relationship = 'stages';

    protected static ?string $title = 'Timeline';

    protected static ?string $modelLabel = 'stage';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Stage')->required()->maxLength(120)
                ->extraInputAttributes(['dir' => 'auto'])->columnSpanFull(),
            DatePicker::make('planned_start')->label('Planned start'),
            DatePicker::make('planned_end')->label('Planned end')
                ->helperText('Accepted changes push both by their extra days.'),
            DatePicker::make('started_on')->label('Really started'),
            DatePicker::make('finished_on')->label('Really finished')->helperText('Finished sets it to 100%.'),
            TextInput::make('percent')->label('Done')->integer()->minValue(0)->maxValue(100)->suffix('%')
                ->default(0)->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        /** @var ClientRoom $room */
        $room = $this->getOwnerRecord();
        $accepted = $room->changes()->where('status', RoomChange::ACCEPTED)->where('extra_days', '>', 0)->get();
        $day = fn ($d) => $d?->format('j M Y');
        $span = fn ($a, $b) => collect([$day($a), $day($b)])->filter()->join(' – ') ?: null;
        $finished = fn (array $data) => filled($data['finished_on'] ?? null) ? ['percent' => 100] + $data : $data;

        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Stage')->extraAttributes(['dir' => 'auto']),
                TextColumn::make('planned')->label('Planned')->placeholder('—')
                    ->state(fn (RoomStage $r) => $span($r->pushBy($accepted)->plannedStart(), $r->plannedEnd()))
                    ->description(fn (RoomStage $r) => $r->pushBy($accepted)->pushEnd ? '+'.$r->pushEnd.' days from accepted changes' : null),
                TextColumn::make('actual')->label('Really')->placeholder('—')
                    ->state(fn (RoomStage $r) => $span($r->started_on, $r->finished_on)),
                TextColumn::make('percent')->label('Done')->suffix('%'),
                TextColumn::make('state')->label('')->badge()
                    ->state(fn (RoomStage $r) => match (true) {
                        $r->pushBy($accepted)->isLate() => 'Late',
                        $r->isDone() => 'Done',
                        default => null,
                    })
                    ->color(fn (?string $state) => $state === 'Late' ? 'danger' : 'success'),
            ])
            ->headerActions([
                Action::make('standard')->label('Add the standard stages')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->visible(fn () => $room->stages()->doesntExist())
                    ->action(function () use ($room) {
                        foreach (RoomStage::STANDARD[$room->lang] ?? RoomStage::STANDARD['en'] as $i => $name) {
                            $room->stages()->create(['name' => $name, 'sort_order' => $i + 1]);
                        }
                    }),
                CreateAction::make()->label('Add a stage')
                    ->mutateDataUsing(fn (array $data) => ['sort_order' => (int) $room->stages()->max('sort_order') + 1] + $finished($data)),
            ])
            ->recordActions([
                EditAction::make()->mutateDataUsing($finished),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('No timeline yet')
            ->emptyStateDescription('Start from the standard stages — Concept, Permit, Construction drawings, Construction, Handover — in the room\'s language, then rename, add or remove. The client sees each stage\'s dates and how far along it is, and the stage under way at the top of their room.');
    }
}
