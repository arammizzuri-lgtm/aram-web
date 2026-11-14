<?php

namespace App\Filament\Resources\Projects\RelationManagers;

use App\Filament\Pages\Tasks;
use App\Models\Task;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** The studio's tasks for one project — or one client room (both have tasks()); the same tasks as on the Tasks board. */
class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $title = 'Tasks';

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->tasks()->open()->count() ?: null;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components(Tasks::fields(links: false));
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_at')
            ->columns([
                TextColumn::make('title')->wrap(),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn (string $state) => Task::STATUSES[$state] ?? $state)
                    ->color(fn (string $state) => ['doing' => 'warning', 'done' => 'success'][$state] ?? 'gray'),
                TextColumn::make('due_at')->label('Due')->dateTime('D j M, H:i', Task::TZ)->sortable()
                    ->description(fn (Task $r) => $r->timeLeft())->color(fn (Task $r) => $r->urgency()),
            ])
            ->headerActions([
                CreateAction::make()->label('Add a task'),
                Action::make('board')->label('Open the board')->color('gray')->url(Tasks::getUrl()),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
