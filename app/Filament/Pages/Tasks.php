<?php

namespace App\Filament\Pages;

use App\Models\ClientRoom;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Task;
use App\Support\QuickTask;
use BackedEnum;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
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

    public const LABELS = 'task_labels';

    public const LABEL_COLOURS = [
        'blue' => '#5aa9e6', 'green' => '#3fbf7f', 'orange' => '#e8833a', 'purple' => '#a585ec',
        'pink' => '#e86aa6', 'teal' => '#2bb3a6', 'yellow' => '#F5C518', 'gray' => '#9a9a9a',
    ];

    /** Until the studio sets its own. A label's key stays when it is renamed, so its tasks keep it. */
    public const STARTER_LABELS = [
        ['key' => 'drawing', 'name' => 'Drawing', 'colour' => 'blue'],
        ['key' => 'site', 'name' => 'Site', 'colour' => 'orange'],
        ['key' => 'permit', 'name' => 'Permit', 'colour' => 'purple'],
        ['key' => 'client', 'name' => 'Client', 'colour' => 'green'],
        ['key' => 'admin', 'name' => 'Admin', 'colour' => 'gray'],
    ];

    public const FILE_TYPES = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];

    /** For the busy meter: a full day, and a full week (Sunday to Thursday). shortcut: fixed, a setting if the studio's hours differ */
    public const DAY_MINUTES = 8 * 60;

    public const WEEK_MINUTES = 40 * 60;

    /** board | timeline | calendar */
    #[Url]
    public string $show = 'board';

    /** The calendar's month, "2026-11" ('' for this month) */
    #[Url]
    public string $month = '';

    /** Only the tasks with this label ('' for all) */
    #[Url]
    public string $label = '';

    /** The quick-add line, read as it is typed (App\Support\QuickTask) */
    public string $quick = '';

    /** A photo taken from a card's camera button, on its way onto the task */
    public $photo = null;

    public function attachPhoto(int $id): void
    {
        $task = Task::query()->find($id);
        if (! $task || Validator::make(['photo' => $this->photo], ['photo' => ['required', 'file', 'mimetypes:'.implode(',', self::FILE_TYPES), 'max:30720']])->fails()) {
            $this->reset('photo');
            Notification::make()->danger()->title('That could not be added: a photo or PDF, up to 30 MB')->send();

            return;
        }
        $path = $this->photo->store('tasks', Task::DISK);
        $task->update([
            'attachments' => [...$task->attachments ?? [], $path],
            'attachment_names' => [...$task->attachment_names ?? [], $path => $this->photo->getClientOriginalName()],
        ]);
        $this->reset('photo');
        Notification::make()->success()->title('Added to “'.Str::limit($task->title, 40).'”')->send();
    }

    public function quickAdd(?string $text = null): void
    {
        $q = QuickTask::parse($text ?? $this->quick);
        if ($q['title'] === '') {
            Notification::make()->warning()->title('Write what to do — and a day or time if it has one')->send();

            return;
        }
        $task = Task::create([
            'title' => Str::limit($q['title'], 160, ''),
            'due_at' => $q['due_at']->copy()->utc(),
            'priority' => $q['priority'],
            'labels' => $q['labels'],
            'client_room_id' => $q['client_room_id'],
            'project_id' => $q['project_id'],
        ]);
        $this->quick = '';
        $this->offerUndo('Added “'.$task->title.'” — due '.$task->due_at->timezone(Task::TZ)->format('D j M, H:i'), [], $task->id);
    }

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
        $task = Task::query()->find($id);
        if (! $task || ! isset(Task::STATUSES[$status]) || $task->status === $status) {
            return;
        }
        $before = $task->getAttributes();
        $task->update(['status' => $status]);
        $this->offerUndo('Moved to '.Task::STATUSES[$status].($task->madeNext ? ' — the next one is in To do' : ''), $before, $task->madeNext);
    }

    public const SNOOZES = [1 => '1 day', 3 => '3 days', 7 => '1 week'];

    public function snooze(int $id, int $days): void
    {
        $task = Task::query()->open()->find($id);
        if (! $task || ! isset(self::SNOOZES[$days])) {
            return;
        }
        $before = $task->getAttributes();
        $task->update(['due_at' => $task->snoozed($days)->utc()]);
        $this->offerUndo('Snoozed to '.$task->due_at->timezone(Task::TZ)->format('D j M, H:i'), $before);
    }

    /** Dropped on another day of the calendar: due that day, at the same time. */
    public function moveToDay(int $id, string $day): void
    {
        $task = Task::query()->find($id);
        if (! $task || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            return;
        }
        $was = $task->due_at->copy()->timezone(Task::TZ);
        $to = Carbon::parse($day, Task::TZ)->setTime($was->hour, $was->minute);
        if ($to->eq($was)) {
            return;
        }
        $before = $task->getAttributes();
        $task->update(['due_at' => $to->copy()->utc()]);
        $this->offerUndo('Moved to '.$to->format('D j M'), $before);
    }

    /** The timeline: a bar dragged some days on, its start with it — or only its end, for a longer or shorter task. */
    public function shiftOnTimeline(int $id, int $days, bool $endOnly = false): void
    {
        $task = Task::query()->open()->find($id);
        if (! $task || $days === 0 || abs($days) > 366) {
            return;
        }
        $before = $task->getAttributes();
        $due = $task->due_at->copy()->timezone(Task::TZ)->addDays($days);
        $start = $endOnly ? $task->starts_on : $task->startsAt()->copy()->timezone(Task::TZ)->addDays($days)->startOfDay();
        $task->update([
            'due_at' => $due->copy()->utc(),
            // an end dragged back past the start takes the start with it
            'starts_on' => $start ? min($start->toDateString(), $due->toDateString()) : null,
        ]);
        $this->offerUndo(($endOnly ? 'Due ' : 'Moved to ').$due->format('D j M'), $before);
    }

    public function toggleTimer(int $id): void
    {
        $task = Task::query()->open()->find($id);
        if ($task) {
            $task->timer_started_at ? $task->stopTimer() : $task->startTimer();
        }
    }

    public function togglePin(int $id): void
    {
        $task = Task::query()->find($id);
        $task?->update(['pinned' => ! $task->pinned]);
    }

    // ---- undo: the last move, snooze or delete, for as long as its message shows

    /** @param  array  $before  the task's row as it was (raw, as stored), or a list of rows */
    private function offerUndo(string $title, array $before, ?int $made = null): void
    {
        $token = Str::random(16);
        session()->put('task_undo', ['token' => $token, 'tasks' => array_is_list($before) ? $before : [$before], 'made' => $made]);
        Notification::make()->success()->title($title)->duration(8000)
            ->actions([Action::make('undo')->label('Undo')->link()->dispatch('undo-task', ['token' => $token])->close()])
            ->send();
    }

    #[On('undo-task')]
    public function undo(string $token): void
    {
        $undo = session('task_undo');
        if (($undo['token'] ?? null) !== $token) {
            Notification::make()->warning()->title('That can no longer be undone')->send();

            return;
        }
        session()->forget('task_undo');
        DB::transaction(function () use ($undo) {
            if ($undo['made']) {
                Task::query()->whereKey($undo['made'])->delete();
            }
            // as it was, row for row: back in place, or back at all if it was deleted (none: it was just added)
            if ($undo['tasks'] ?? null) {
                Task::query()->upsert($undo['tasks'], ['id']);
            }
        });
        Notification::make()->success()->title('Undone')->send();
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
            ToggleButtons::make('labels')->multiple()->inline()->columnSpanFull()
                ->options(fn () => self::labels()->map(fn (array $l) => $l['name'])),
            Select::make('client_room_id')->label('Client room')->options(fn () => self::roomOptions())->searchable()->visible($links),
            Select::make('project_id')->label('Project')->options(fn () => self::projectOptions())->searchable()->visible($links)
                ->helperText('A room\'s task is its project\'s too.'),
            Select::make('repeat')->options(Task::REPEATS)->placeholder('Does not repeat')
                ->helperText('Done, the next one appears by itself.'),
            // kept in minutes, written in hours
            TextInput::make('estimate_minutes')->label('Estimate')->numeric()->minValue(0)->step(0.25)->suffix('hours')
                ->helperText('How long you think it takes.')
                ->formatStateUsing(fn ($state) => $state ? round($state / 60, 2) : null)
                ->dehydrateStateUsing(fn ($state) => filled($state) ? (int) round($state * 60) : null),
            TextInput::make('spent_minutes')->label('Time spent')->numeric()->minValue(0)->step(0.25)->suffix('hours')
                ->helperText('The timer adds to it — or write it in.')
                ->formatStateUsing(fn ($state) => $state ? round($state / 60, 2) : null)
                ->dehydrateStateUsing(fn ($state) => (int) round(((float) $state) * 60)),
            Repeater::make('checklist')->label('Checklist')->columnSpanFull()->addActionLabel('Add a step')
                ->defaultItems(0)->reorderable()->columns(['default' => 12])
                ->schema([
                    Checkbox::make('done')->hiddenLabel()->columnSpan(['default' => 1])->extraFieldWrapperAttributes(['style' => 'padding-top:.55rem']),
                    TextInput::make('text')->hiddenLabel()->required()->maxLength(200)->columnSpan(['default' => 11]),
                ]),
            Textarea::make('notes')->rows(3)->columnSpanFull(),
            FileUpload::make('attachments')->label('Photos & files — PDF or image, up to 30 MB each')->multiple()->appendFiles()
                ->disk(Task::DISK)->directory('tasks')->visibility('private')
                ->acceptedFileTypes(self::FILE_TYPES)->maxSize(30720)->storeFileNamesIn('attachment_names')
                // a phone photo is shrunk before it is sent; a PDF goes as it is
                ->imageResizeMode('contain')->imageResizeTargetWidth('2000')->imageResizeTargetHeight('2000')
                ->panelLayout('grid')->openable()->downloadable()->columnSpanFull(),
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
            ActionGroup::make([$this->templatesAction(), $this->labelsAction()])
                ->label('Set up')->icon(Heroicon::OutlinedAdjustmentsHorizontal)->button()->color('gray'),
        ];
    }

    protected function labelsAction(): Action
    {
        return Action::make('labels')->label('Labels')->icon(Heroicon::OutlinedTag)->modalWidth('2xl')->modalSubmitActionLabel('Save')
            ->modalDescription('Colours to sort tasks by — Drawing, Site, Permit… Renaming one keeps it on its tasks.')
            ->fillForm(fn () => ['labels' => self::labels()->values()->all()])
            ->schema([
                Repeater::make('labels')->hiddenLabel()->addActionLabel('Add a label')->reorderable()->columns(['default' => 2])
                    ->schema([
                        Hidden::make('key'),
                        TextInput::make('name')->required()->maxLength(30)->hiddenLabel()->placeholder('Name'),
                        Select::make('colour')->hiddenLabel()->required()->default('blue')->native(false)->allowHtml()
                            ->options(collect(self::LABEL_COLOURS)->map(fn (string $hex, string $name) => '<span style="display:inline-block;width:.7em;height:.7em;margin-inline-end:.45em;border-radius:50%;background:'.$hex.'"></span>'.ucfirst($name))),
                    ]),
            ])
            ->action(function (array $data) {
                $list = collect($data['labels'] ?? [])->values()
                    ->map(fn (array $l) => ['key' => $l['key'] ?: Str::lower(Str::random(8)), 'name' => $l['name'], 'colour' => $l['colour']])->all();
                SiteSetting::put(self::LABELS, json_encode($list, JSON_UNESCAPED_UNICODE), 'tasks');
                Notification::make()->success()->title('Labels saved')->send();
            });
    }

    /** @return Collection<string, array{key: string, name: string, colour: string}> keyed by key */
    public static function labels(): Collection
    {
        return collect(json_decode(setting(self::LABELS), true) ?: self::STARTER_LABELS)->keyBy('key');
    }

    protected function templatesAction(): Action
    {
        return
            Action::make('templates')->label('Templates')->icon(Heroicon::OutlinedRectangleStack)->modalWidth('4xl')->modalSubmitActionLabel('Save')
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
                });
    }

    /** A card or a bar opened: edit, or delete. */
    public function openTaskAction(): Action
    {
        $task = fn (array $arguments) => Task::query()->findOrFail($arguments['task'] ?? 0);

        return Action::make('openTask')->modalHeading('Task')->modalWidth('2xl')->modalSubmitActionLabel('Save')
            ->fillForm(fn (array $arguments) => $task($arguments)->only([
                'title', 'due_at', 'starts_on', 'status', 'priority', 'labels', 'client_room_id', 'project_id', 'repeat', 'checklist', 'notes',
                'estimate_minutes', 'spent_minutes', 'attachments', 'attachment_names',
            ]))
            ->schema(self::fields())
            ->extraModalFooterActions(fn (array $arguments) => [
                Action::make('shiftRest')->label('Shift the rest')->color('gray')->link()
                    ->visible(fn () => ($t = $task($arguments))->project_id && $t->status !== 'done')
                    ->modalHeading(fn () => 'Shift the rest of '.$task($arguments)->project?->name)
                    ->modalDescription(function () use ($task, $arguments) {
                        $t = $task($arguments);
                        $n = self::laterTasks($t)->count() - 1;

                        return $n ? "“{$t->title}” and the {$n} open ".Str::plural('task', $n).' due after it move by the same number of days.'
                            : "Nothing is due after “{$t->title}”: only it moves.";
                    })
                    ->modalSubmitActionLabel('Shift')->cancelParentActions()
                    ->fillForm(fn () => ['days' => ($t = $task($arguments))->isLate() ? (int) ceil($t->due_at->diffInDays(now())) : 1])
                    ->schema([TextInput::make('days')->label('Days')->integer()->required()->minValue(-365)->maxValue(365)->notIn([0])
                        ->helperText('Less than 0 brings them sooner. Each keeps its time of day.')])
                    ->action(function (array $data) use ($task, $arguments) {
                        $before = self::shiftRest($task($arguments), (int) $data['days']);
                        $this->offerUndo('Moved '.count($before).' '.Str::plural('task', count($before)).' '.Str::plural('day', abs((int) $data['days']), true).' '.($data['days'] > 0 ? 'later' : 'sooner'), $before);
                    }),
                Action::make('deleteTask')->label('Delete')->color('danger')->link()
                    ->requiresConfirmation()->modalHeading('Delete this task?')->cancelParentActions()
                    ->action(function () use ($task, $arguments) {
                        $t = $task($arguments);
                        $before = $t->getAttributes();
                        $t->delete();
                        $this->offerUndo('Task deleted', $before);
                    }),
            ])
            ->action(function (array $data, array $arguments) use ($task) {
                $task($arguments)->update($data);
                Notification::make()->success()->title('Saved')->send();
            });
    }

    /** It and the open tasks of its project due at or after it */
    public static function laterTasks(Task $task): Collection
    {
        return Task::query()->open()->where('project_id', $task->project_id)->where('due_at', '>=', $task->due_at)->get();
    }

    /** A step running late: it and every later open task of its project move the same number of days. @return array their rows as they were */
    public static function shiftRest(Task $task, int $days): array
    {
        $tasks = self::laterTasks($task);
        $before = $tasks->map->getAttributes()->all();
        DB::transaction(fn () => $tasks->each(fn (Task $t) => $t->update([
            'due_at' => $t->due_at->copy()->addDays($days),
            'starts_on' => $t->starts_on?->copy()->addDays($days),
        ])));

        return $before;
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
        $all = Task::query()->with(['project:id,name', 'room.project:id,name'])
            ->where(fn ($q) => $q->open()->orWhere('done_at', '>=', now()->subDays(14)))
            ->orderBy('due_at')->get();
        $tasks = $this->label === '' ? $all : $all->filter(fn (Task $t) => in_array($this->label, $t->labels ?? [], true));
        // how full the days are counts all the work, whatever label is shown
        $busy = self::busy($all);

        return [
            'preview' => trim($this->quick) !== '' ? QuickTask::parse($this->quick) : null,
            'timing' => $all->first(fn (Task $t) => $t->timer_started_at !== null),
            'labels' => self::labels(),
            'labelCounts' => $all->where('status', '!=', 'done')->flatMap(fn (Task $t) => $t->labels ?? [])->countBy(),
            'columns' => collect(Task::STATUSES)->map(fn ($label, $status) => $tasks->where('status', $status)
                // pinned on top, the rest by when they are due (a stable sort keeps that order)
                ->when($status === 'done', fn ($c) => $c->sortByDesc('done_at'), fn ($c) => $c->sortByDesc('pinned'))),
            'counts' => self::counts($tasks->where('status', '!=', 'done')),
            'busyWeek' => self::busyWeek($busy),
            'focus' => $this->show === 'today' ? self::focus($all) : null,
            'timeline' => $this->show === 'timeline' ? self::timeline($tasks, $busy) : null,
            'calendar' => $this->show === 'calendar' ? $this->calendar($busy) : null,
        ];
    }

    /** Today, calmly (every label): what is late, due by tonight, pinned or being timed — and how many were done today (Erbil). */
    public static function focus(Collection $all): array
    {
        $tonight = now(Task::TZ)->endOfDay();

        return [
            'tasks' => $all->where('status', '!=', 'done')
                ->filter(fn (Task $t) => $t->due_at->lte($tonight) || $t->pinned || $t->timer_started_at)->values(),
            'done' => $all->where('status', 'done')->filter(fn (Task $t) => $t->done_at?->gte($tonight->copy()->startOfDay()))->count(),
        ];
    }

    /** Work still to do (from the estimates), in minutes by Erbil day: the day it is due, or today when it is late. */
    public static function busy(Collection $tasks): Collection
    {
        $today = now(Task::TZ)->toDateString();

        return $tasks->filter(fn (Task $t) => $t->remaining() > 0)
            ->groupBy(fn (Task $t) => max($today, $t->due_at->copy()->timezone(Task::TZ)->toDateString()))
            ->map(fn (Collection $g) => $g->sum(fn (Task $t) => $t->remaining()));
    }

    /** 1 up to half full · 2 up to full · 3 more than fits */
    public static function busyLevel(int $minutes, int $full): int
    {
        return $minutes > $full ? 3 : ($minutes > $full / 2 ? 2 : 1);
    }

    /** The first of this week and the five after (Sunday to Saturday) with more work than a week holds: [from, to, minutes], or null. */
    public static function busyWeek(Collection $busy): ?array
    {
        $sunday = now(Task::TZ)->startOfWeek(CarbonInterface::SUNDAY);
        for ($w = 0; $w < 6; $w++, $sunday->addWeek()) {
            $saturday = $sunday->copy()->addDays(6);
            $minutes = $busy->filter(fn ($m, $day) => $day >= $sunday->toDateString() && $day <= $saturday->toDateString())->sum();
            if ($minutes > self::WEEK_MINUTES) {
                return ['from' => $sunday->copy(), 'to' => $saturday, 'minutes' => $minutes];
            }
        }

        return null;
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

    /** The month's weeks, Sunday to Saturday (as the weekly report counts them), each day with the tasks due on it (Erbil days). */
    public function calendar(Collection $busy): array
    {
        $first = Carbon::parse(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $this->month) ? $this->month.'-01' : 'first day of this month', Task::TZ)->startOfDay();
        $from = $first->copy()->startOfWeek(CarbonInterface::SUNDAY);
        $to = $first->copy()->endOfMonth()->endOfWeek(CarbonInterface::SATURDAY);
        $byDay = Task::query()->with(['project:id,name', 'room.project:id,name'])
            ->whereBetween('due_at', [$from->copy()->utc(), $to->copy()->utc()])->orderBy('due_at')->get()
            ->when($this->label !== '', fn ($c) => $c->filter(fn (Task $t) => in_array($this->label, $t->labels ?? [], true)))
            ->groupBy(fn (Task $t) => $t->due_at->copy()->timezone(Task::TZ)->toDateString());
        $today = now(Task::TZ)->toDateString();

        $days = [];
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $key = $d->toDateString();
            $days[] = ['date' => $d->copy(), 'key' => $key, 'in' => $d->month === $first->month, 'today' => $key === $today, 'tasks' => $byDay[$key] ?? collect(), 'busy' => $busy[$key] ?? 0];
        }
        $weeks = array_map(fn (array $week) => array_sum(array_column($week, 'busy')), array_chunk($days, 7));

        return ['month' => $first, 'prev' => $first->copy()->subMonth()->format('Y-m'), 'next' => $first->copy()->addMonth()->format('Y-m'), 'days' => $days, 'weeks' => $weeks];
    }

    /**
     * Bars on a span from a week ago to a little after the last due day (at
     * most four months), grouped by project, each placed in percent.
     */
    public static function timeline(Collection $tasks, ?Collection $busy = null): array
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

        // the busy meter: a strip under the dates, each day as full as its work
        $heat = collect($busy)->map(function (int $minutes, string $day) use ($at) {
            $d = Carbon::parse($day, Task::TZ);

            return ['left' => $at($d), 'width' => $at($d->copy()->addDay()) - $at($d), 'level' => self::busyLevel($minutes, self::DAY_MINUTES),
                'title' => $d->format('D j M').': '.Task::hm($minutes).' of work'];
        })->filter(fn (array $h) => $h['width'] > 0)->values();

        return ['groups' => $groups, 'ticks' => $ticks, 'today' => $at(now()), 'heat' => $heat, 'days' => $span / 86400];
    }
}
