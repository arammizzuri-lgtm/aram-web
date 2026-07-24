<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Models\Category;
use App\Models\Project;
use App\Models\Status;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Columns\ViewColumn;
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
            // The studio curates the public order by dragging rows, which only
            // works when every project is on one page — so no pagination.
            ->paginated(false)
            ->columns([
                // Drag handle. Server-rendered so it survives Livewire's
                // re-render after a drop; the dragging itself is wired up in
                // resources/views/filament/tables/projects-drag.blade.php.
                ViewColumn::make('reorder')
                    ->label('')
                    ->view('filament.tables.reorder-handle')
                    ->extraCellAttributes(['class' => 'am-reorder-cell'])
                    ->extraHeaderAttributes(['class' => 'am-reorder-cell']),
                ImageColumn::make('cover')
                    ->label('')
                    ->state(fn (Project $record) => $record->coverUrl())
                    ->height(38)
                    ->extraImgAttributes(['style' => 'width:40px;object-fit:cover;border-radius:6px;']),
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
                TextColumn::make('year')->alignEnd()->toggleable()
                    ->extraCellAttributes(['style' => 'white-space:nowrap']),
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
                // Edit stays one click; the rest live in a compact "⋮" menu so
                // every data column fits on screen without sideways scrolling.
                EditAction::make()->iconButton(),
                ActionGroup::make([
                    // Jump a project straight to either end of the running
                    // order. Dragging handles the nudges; these are for "put
                    // this one first/last" and still work while filtered.
                    Action::make('moveToTop')
                        ->label('Move to start')
                        ->icon(Heroicon::OutlinedChevronDoubleUp)
                        ->action(function (Project $record) {
                            $record->moveToTop();
                            Notification::make()->success()
                                ->title($record->name.' moved to the start')
                                ->send();
                        }),
                    Action::make('moveToBottom')
                        ->label('Move to end')
                        ->icon(Heroicon::OutlinedChevronDoubleDown)
                        ->action(function (Project $record) {
                            $record->moveToBottom();
                            Notification::make()->success()
                                ->title($record->name.' moved to the end')
                                ->send();
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
