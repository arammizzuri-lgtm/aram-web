<?php

namespace App\Filament\Pages;

use App\Models\ClientRoom;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Task;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * The studio's tasks: a board (To do · Doing · Done — drag a card, or tap
 * its button on a phone) and a timeline (a bar per task from its start to
 * when it is due). Every card says how long is left. Tasks can belong to a
 * project or a client room, repeat, carry a checklist, be added from a
 * template, and come from the website by themselves (App\Support\AutoTasks).
 */
class Tasks extends Page
{
    protected string $view = 'filament.pages.tasks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Tasks';

    public const TEMPLATES = 'task_templates';

    /** Until the studio writes its own: one to start from, and to change. */
    public const STARTER_TEMPLATES = [[
        'name' => 'New house',
        'tasks' => [
            ['title' => 'Site visit and survey', 'after' => 0, 'days' => 3, 'priority' => 'normal'],
            ['title' => 'Concept design', 'after' => 3, 'days' => 14, 'priority' => 'high'],
            ['title' => 'Client review of the concept', 'after' => 17, 'days' => 5, 'priority' => 'normal'],
            ['title' => 'Permit drawings', 'after' => 22, 'days' => 14, 'priority' => 'high'],
            ['title' => 'Submit for the building permit', 'after' => 36, 'days' => 2, 'priority' => 'normal'],
            ['title' => '3D renders for the client', 'after' => 38, 'days' => 10, 'priority' => 'low'],
            ['title' => 'Construction drawings', 'after' => 38, 'days' => 30, 'priority' => 'normal'],
        ],
    ]];

    /** board | timeline */
    #[Url]
    public string $show = 'board';

    // ---- the sidebar: how many are open; red when one is late

    public static function getNavigationBadge(): ?string
    {
        return (string) Task::query()->open()->count() ?: null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Task::query()->open()->where('due_at', '<', now())->exists() ? 'danger' : 'gray';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        $late = Task::query()->open()->where('due_at', '<', now())->count();

        return $late ? $late.' late' : null;
    }

    // ---- moving cards

    public function move(int $id, string $status): void
    {
        if (isset(Task::STATUSES[$status])) {
            Task::query()->find($id)?->update(['status' => $status]);
        }
    }

    public function toggleStep(int $id, int $step): void
    {
        $task = Task::query()->find($id);
        $list = array_values($task?->checklist ?? []);
        if (! isset($list[$step])) {
            return;
        }
        $list[$step]['done'] = ! ($list[$step]['done'] ?? false);
        $task->update(['checklist' => $list]);
    }

    // ---- the forms

    /** A task's fields; without the project and room where those are already given (a project's or room's Tasks tab). */
    public static function fields(bool $links = true): array
    {
        return [Grid::make(['default' => 1, 'sm' => 2])->columnSpanFull()->schema([
            TextInput::make('title')->required()->maxLength(160)->columnSpanFull()->autofocus(),
            DateTimePicker::make('due_at')->label('Due')->required()->seconds(false)->timezone(Task::TZ)
                ->default(fn () => now(Task::TZ)->addDay()->setTime(17, 0)),
            DatePicker::make('starts_on')->label('Starts')->helperText('Where its bar begins on the timeline')
                ->default(fn () => now(Task::TZ)->toDateString()),
            ToggleButtons::make('status')->options(Task::STATUSES)->inline()->default('todo')
                ->colors(['todo' => 'gray', 'doing' => 'warning', 'done' => 'success']),
            ToggleButtons::make('priority')->options(Task::PRIORITIES)->inline()->default('normal')
                ->colors(['high' => 'danger', 'normal' => 'gray', 'low' => 'info']),
            Select::make('client_room_id')->label('Client room')->options(fn () => self::roomOptions())->searchable()->visible($links),
            Select::make('project_id')->label('Project')->options(fn () => self::projectOptions())->searchable()->visible($links)
                ->helperText('A room\'s task is its project\'s too.'),
            Select::make('repeat')->options(Task::REPEATS)->placeholder('Does not repeat')
                ->helperText('Done, the next one appears by itself.'),
            Repeater::make('checklist')->label('Checklist')->columnSpanFull()->addActionLabel('Add a step')
                ->defaultItems(0)->reorderable()->columns(['default' => 12])
                ->schema([
                    Checkbox::make('done')->hiddenLabel()->columnSpan(['default' => 1])->extraFieldWrapperAttributes(['style' => 'padding-top:.55rem']),
                    TextInput::make('text')->hiddenLabel()->required()->maxLength(200)->columnSpan(['default' => 11]),
                ]),
            Textarea::make('notes')->rows(3)->columnSpanFull(),
        ])];
    }

    public static function projectOptions(): Collection
    {
        return Project::query()->orderBy('name')->pluck('name', 'id');
    }

    public static function roomOptions(): Collection
    {
        return ClientRoom::query()->with('project:id,name')->get()
            ->mapWithKeys(fn (ClientRoom $r) => [$r->id => $r->client_name.' — '.($r->project?->name ?? 'room')]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('newTask')->label('New task')->icon(Heroicon::OutlinedPlus)->modalWidth('2xl')
                ->schema(self::fields())
                ->action(function (array $data) {
                    Task::create($data);
                    Notification::make()->success()->title('Task added')->send();
                }),
            Action::make('fromTemplate')->label('From a template')->icon(Heroicon::OutlinedSquare2Stack)->color('gray')
                ->modalDescription('Adds the template\'s tasks, their dates counted from the day you choose.')
                ->schema([
                    Select::make('template')->options(fn () => collect(self::templates())->pluck('name'))->required()->native(false),
                    DatePicker::make('start')->label('Counting from')->required()->default(fn () => now(Task::TZ)->toDateString()),
                    Select::make('client_room_id')->label('Client room')->options(fn () => self::roomOptions())->searchable(),
                    Select::make('project_id')->label('Project')->options(fn () => self::projectOptions())->searchable(),
                ])
                ->action(function (array $data) {
                    $n = self::applyTemplate(self::templates()[$data['template']] ?? null, Carbon::parse($data['start'], Task::TZ),
                        $data['client_room_id'] ?? null, $data['project_id'] ?? null);
                    Notification::make()->success()->title($n.' tasks added')->send();
                }),
            Action::make('templates')->label('Templates')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->color('gray')->modalWidth('4xl')
                ->modalDescription('Your usual sets of tasks. "After" and "Takes" are days: after the start day, and how long it has.')
                ->fillForm(fn () => ['templates' => self::templates()])
                ->schema([
                    Repeater::make('templates')->hiddenLabel()->addActionLabel('Add a template')->collapsible()
                        ->itemLabel(fn (array $state) => $state['name'] ?? null)
                        ->schema([
                            TextInput::make('name')->required()->maxLength(60),
                            Repeater::make('tasks')->addActionLabel('Add a task')->reorderable()->columns(['default' => 2, 'md' => 6])
                                ->schema([
                                    TextInput::make('title')->required()->maxLength(160)->columnSpan(['default' => 2, 'md' => 3]),
                                    TextInput::make('after')->numeric()->minValue(0)->default(0)->required()->suffix('days'),
                                    TextInput::make('days')->label('Takes')->numeric()->minValue(1)->default(1)->required()->suffix('days'),
                                    Select::make('priority')->options(Task::PRIORITIES)->default('normal')->required()->native(false),
                                ]),
                        ]),
                ])
                ->action(function (array $data) {
                    $list = collect($data['templates'] ?? [])->values()
                        ->map(fn (array $t) => ['name' => $t['name'], 'tasks' => array_values($t['tasks'] ?? [])])->all();
                    SiteSetting::put(self::TEMPLATES, json_encode($list, JSON_UNESCAPED_UNICODE), 'tasks');
                    Notification::make()->success()->title('Templates saved')->send();
                }),
        ];
    }

    /** A card or a bar opened: edit, or delete. */
    public function openTaskAction(): Action
    {
        $task = fn (array $arguments) => Task::query()->findOrFail($arguments['task'] ?? 0);

        return Action::make('openTask')->modalHeading('Task')->modalWidth('2xl')->modalSubmitActionLabel('Save')
            ->fillForm(fn (array $arguments) => $task($arguments)->only([
                'title', 'due_at', 'starts_on', 'status', 'priority', 'client_room_id', 'project_id', 'repeat', 'checklist', 'notes',
            ]))
            ->schema(self::fields())
            ->extraModalFooterActions(fn (array $arguments) => [
                Action::make('deleteTask')->label('Delete')->color('danger')->link()
                    ->requiresConfirmation()->modalHeading('Delete this task?')->cancelParentActions()
                    ->action(function () use ($task, $arguments) {
                        $task($arguments)->delete();
                        Notification::make()->success()->title('Task deleted')->send();
                    }),
            ])
            ->action(function (array $data, array $arguments) use ($task) {
                $task($arguments)->update($data);
                Notification::make()->success()->title('Saved')->send();
            });
    }

    /** @return int how many tasks it added */
    public static function applyTemplate(?array $template, CarbonInterface $start, ?int $room = null, ?int $project = null): int
    {
        foreach ($template['tasks'] ?? [] as $t) {
            $from = $start->copy()->startOfDay()->addDays((int) $t['after']);
            Task::create([
                'title' => $t['title'],
                'priority' => $t['priority'] ?? 'normal',
                'starts_on' => $from->toDateString(),
                // the end of its last day, Erbil time, stored as UTC
                'due_at' => $from->copy()->addDays(max(1, (int) $t['days']) - 1)->setTime(17, 0)->utc(),
                'client_room_id' => $room,
                'project_id' => $project,
            ]);
        }

        return count($template['tasks'] ?? []);
    }

    public static function templates(): array
    {
        return json_decode(setting(self::TEMPLATES), true) ?: self::STARTER_TEMPLATES;
    }

    protected function getViewData(): array
    {
        $tasks = Task::query()->with(['project:id,name', 'room.project:id,name'])
            ->where(fn ($q) => $q->open()->orWhere('done_at', '>=', now()->subDays(14)))
            ->orderBy('due_at')->get();
        return [
            'columns' => collect(Task::STATUSES)->map(fn ($label, $status) => $tasks->where('status', $status)
                ->when($status === 'done', fn ($c) => $c->sortByDesc('done_at'))),
            'counts' => self::counts($tasks->where('status', '!=', 'done')),
            'timeline' => $this->show === 'timeline' ? self::timeline($tasks) : null,
        ];
    }

    /** Of the open tasks: how many, how many late, due later today, and due in the 7 days after (Erbil days). */
    public static function counts(Collection $open): array
    {
        $endOfToday = now(Task::TZ)->endOfDay();

        return [
            'open' => $open->count(),
            'late' => $open->filter->isLate()->count(),
            'today' => $open->filter(fn (Task $t) => ! $t->isLate() && $t->due_at->lte($endOfToday))->count(),
            'week' => $open->filter(fn (Task $t) => $t->due_at->gt($endOfToday) && $t->due_at->lte($endOfToday->copy()->addDays(7)))->count(),
        ];
    }

    /**
     * Bars on a span from a week ago to a little after the last due day (at
     * most four months), grouped by project, each placed in percent.
     */
    public static function timeline(Collection $tasks): array
    {
        $from = now(Task::TZ)->startOfDay()->subDays(7);
        $last = $tasks->where('status', '!=', 'done')->max('due_at') ?? now();
        $to = Carbon::instance($last)->timezone(Task::TZ)->max(now(Task::TZ)->addDays(21))->min(now(Task::TZ)->addDays(120))->endOfDay()->addDays(2);
        $span = $from->diffInSeconds($to);
        $at = fn (CarbonInterface $d) => max(0, min(100, $from->diffInSeconds($d, false) / $span * 100));

        $groups = $tasks->filter(fn (Task $t) => $t->due_at->gte($from) || $t->status !== 'done')
            ->groupBy(fn (Task $t) => $t->project?->name ?? $t->room?->project?->name ?? 'Studio')
            ->sortBy(fn (Collection $g, string $name) => [$name === 'Studio', $g->min(fn (Task $t) => $t->due_at->timestamp)])
            ->map(fn (Collection $g) => $g->map(function (Task $t) use ($at) {
                $left = $at($t->startsAt());

                return ['task' => $t, 'left' => $left, 'width' => max(1.2, $at($t->due_at) - $left)];
            }));

        $ticks = [];
        for ($d = $from->copy(); $d->lt($to); $d->addDays(7)) {
            $ticks[] = ['left' => $at($d), 'label' => $d->format('j M')];
        }

        return ['groups' => $groups, 'ticks' => $ticks, 'today' => $at(now())];
    }
}
