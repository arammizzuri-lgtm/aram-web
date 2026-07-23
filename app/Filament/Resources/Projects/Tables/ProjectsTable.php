<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Models\Category;
use App\Models\Project;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            // Drag-to-reorder is only useful if every project is reachable in
            // one drag: while reordering, show the whole list on a single page
            // so a project can be dragged from the last page to the first.
            ->paginatedWhileReordering(false)
            ->columns([
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Project $record) => $record->coverUrl())
                    ->height(44)
                    ->extraImgAttributes(['style' => 'width:64px;object-fit:cover;border-radius:6px;']),
                // Position in the public grid. Hidden by default — the row
                // order already shows it — but handy while reorganising.
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->badge()->color('gray')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('num')->label('No.')->sortable()->toggleable(),
                TextColumn::make('name')
                    ->searchable()->sortable()
                    ->description(fn (Project $record) => $record->location),
                TextColumn::make('categories')
                    ->label('Categories')
                    ->badge()
                    ->state(fn (Project $record) => $record->categoryLabels())
                    ->color('primary'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match (Status::meta()[$state]['tone'] ?? null) {
                        'done' => 'success',
                        'build' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('year')->alignEnd()->toggleable(),
                ToggleColumn::make('is_published')->label('Live'),
            ])
            ->filters([
                // Matches a project that carries any of the selected categories.
                SelectFilter::make('categories')
                    ->label('Category')
                    ->multiple()
                    ->options(fn () => Category::options())
                    ->query(function (Builder $query, array $data) {
                        $keys = array_filter((array) ($data['values'] ?? []));
                        if (! $keys) {
                            return $query;
                        }

                        return $query->where(function (Builder $q) use ($keys) {
                            foreach ($keys as $key) {
                                $q->orWhereJsonContains('categories', $key);
                            }
                        });
                    }),
                TernaryFilter::make('is_published')->label('Published'),
            ])
            ->recordActions([
                // Jump a project straight to either end of the grid. Dragging
                // handles small nudges; these handle "put this one first" —
                // and they keep working when the list is filtered or searched.
                Action::make('moveToTop')
                    ->label('Move to start')
                    ->tooltip('Move to the start of the grid')
                    ->icon(Heroicon::OutlinedChevronDoubleUp)
                    ->iconButton()
                    ->color('gray')
                    ->action(function (Project $record) {
                        $record->moveToTop();
                        Notification::make()->success()
                            ->title($record->name.' moved to the start')
                            ->send();
                    }),
                Action::make('moveToBottom')
                    ->label('Move to end')
                    ->tooltip('Move to the end of the grid')
                    ->icon(Heroicon::OutlinedChevronDoubleDown)
                    ->iconButton()
                    ->color('gray')
                    ->action(function (Project $record) {
                        $record->moveToBottom();
                        Notification::make()->success()
                            ->title($record->name.' moved to the end')
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
