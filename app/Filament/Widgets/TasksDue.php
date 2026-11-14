<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Tasks;
use App\Models\Task;
use Filament\Widgets\Widget;

/** Under "Needs attention": late, due today, the next 7 days — and the next five tasks with their countdown. */
class TasksDue extends Widget
{
    protected string $view = 'filament.widgets.tasks-due';

    protected static ?int $sort = -3;   // after NeedsAttention (same number, earlier name)

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $open = Task::query()->open()->with(['project:id,name', 'room.project:id,name'])->orderBy('due_at')->get();

        return ['counts' => Tasks::counts($open), 'next' => $open->sortByDesc('pinned')->take(5), 'url' => Tasks::getUrl()];
    }
}
