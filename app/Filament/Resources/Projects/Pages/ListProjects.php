<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * Ignore an empty reorder instead of running it.
     *
     * Filament builds the reorder as a `CASE … END` expression over the given
     * keys; an empty list produces `case end`, which SQLite rejects with a
     * syntax error. The custom drag handles never send an empty order, but this
     * is the last line of defence so a stray call can't 500 the page.
     *
     * @param  array<int, int|string>  $order
     */
    public function reorderTable(array $order, int | string | null $draggedRecordKey = null): void
    {
        if ($order === []) {
            return;
        }

        parent::reorderTable($order, $draggedRecordKey);
    }
}
