<?php

namespace Tests\Feature;

use App\Filament\Pages\Tasks;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\RelationManagers\TasksRelationManager;
use App\Filament\Widgets\TasksDue;
use App\Models\ClientRoom;
use App\Models\Project;
use App\Models\RoomChange;
use App\Models\RoomMeeting;
use App\Models\SiteSetting;
use App\Models\Task;
use App\Models\User;
use App\Models\VisitRequest;
use App\Mail\MorningTasksMail;
use App\Mail\WeeklyReportMail;
use App\Support\DocumentReminders;
use App\Support\MorningTasks;
use App\Support\QuickTask;
use App\Support\StudioMail;
use App\Support\TaskCalendar;
use App\Support\TaskReminders;
use App\Support\WeeklyReport;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** The studio's tasks: time left, the board, templates, the timeline, and the tasks the website makes by itself. */
class TasksTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-20 09:00');   // UTC: 12:00 in Erbil
        Mail::fake();
        $this->project = Project::create(['name' => 'Ankawa House', 'category' => 'residential', 'status' => 'Under Construction', 'size' => 'default', 'is_published' => true]);
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']));
    }

    private function room(): ClientRoom
    {
        return ClientRoom::create(['project_id' => $this->project->id, 'client_name' => 'Shilan', 'client_phone' => '0750 123 4567', 'lang' => 'en']);
    }

    public function test_time_left_reads_plainly_and_turns_red_when_late(): void
    {
        $due = fn (string $at, string $status = 'todo') => Task::create(['title' => 'x', 'due_at' => $at, 'status' => $status]);

        $this->assertSame(['3 days left', 'gray'], [$due('2026-11-23 11:00')->timeLeft(), $due('2026-11-23 11:00')->urgency()]);
        $this->assertSame(['1 day left', 'info'], [$due('2026-11-21 12:00')->timeLeft(), $due('2026-11-21 12:00')->urgency()]);
        $this->assertSame(['Due in 5 hours', 'warning'], [$due('2026-11-20 14:00')->timeLeft(), $due('2026-11-20 14:00')->urgency()]);
        $this->assertSame('Due in 40 minutes', $due('2026-11-20 09:40')->timeLeft());
        $this->assertSame(['2 days late', 'danger'], [$due('2026-11-18 08:00')->timeLeft(), $due('2026-11-18 08:00')->urgency()]);
        $this->assertSame(['Done 20 Nov', 'gray'], [$due('2026-11-18 08:00', 'done')->timeLeft(), $due('2026-11-18 08:00', 'done')->urgency()]);
    }

    public function test_the_board_adds_moves_and_ticks_and_a_repeating_task_comes_back(): void
    {
        $page = Livewire::test(Tasks::class)->assertOk()->assertSee('Nothing here');
        $page->callAction('newTask', [
            'title' => 'Weekly site visit', 'due_at' => '2026-11-22 10:00', 'starts_on' => '2026-11-22', 'status' => 'todo',
            'priority' => 'high', 'project_id' => $this->project->id, 'repeat' => 'weekly',
            'checklist' => [['text' => 'Photos', 'done' => false], ['text' => 'Measure the slab', 'done' => false]],
        ])->assertHasNoActionErrors();

        $task = Task::sole();
        $this->assertSame('2026-11-22 07:00:00', $task->getRawOriginal('due_at'));   // 10:00 in Erbil
        $this->assertSame([0, 2], $task->steps());
        $this->assertSame('1', Tasks::getNavigationBadge());

        $page->call('move', $task->id, 'doing')->call('toggleStep', $task->id, 1)
            ->assertSee('Weekly site visit')->assertSee('☑ 1/2')->assertSee('Ankawa House')->assertSee('↻ Every week');
        $this->assertSame(['doing', [0, 1]], [$task->fresh()->status, [(int) $task->fresh()->checklist[0]['done'], (int) $task->fresh()->checklist[1]['done']]]);

        $page->call('move', $task->id, 'done');
        $done = $task->fresh();
        $this->assertSame(['done', null], [$done->status, $done->repeat]);
        $this->assertNotNull($done->done_at);
        $next = Task::query()->open()->sole();
        $this->assertSame(['Weekly site visit', 'weekly', '2026-11-29 07:00:00', [false, false]],
            [$next->title, $next->repeat, $next->getRawOriginal('due_at'), array_column($next->checklist, 'done')]);

        // opened and changed; a made-up status is refused
        $page->callAction('openTask', ['title' => 'Site visit', 'status' => 'doing'], ['task' => $next->id])->assertHasNoActionErrors();
        $page->call('move', $next->id, 'archived');
        $this->assertSame(['Site visit', 'doing'], [$next->fresh()->title, $next->fresh()->status]);
    }

    public function test_a_pinned_task_stays_on_top_of_its_column_and_of_the_dashboard_card(): void
    {
        $soon = Task::create(['title' => 'Ring the plumber', 'due_at' => '2026-11-21 09:00']);
        $later = Task::create(['title' => 'Order the tiles', 'due_at' => '2026-12-20 09:00']);
        $page = Livewire::test(Tasks::class)->assertSeeInOrder(['Ring the plumber', 'Order the tiles']);

        $page->call('togglePin', $later->id)->assertSeeInOrder(['Order the tiles', 'Ring the plumber'])->assertSee('Unpin');
        $this->assertTrue($later->fresh()->pinned);
        Livewire::test(TasksDue::class)->assertSeeInOrder(['Order the tiles', 'Ring the plumber']);

        $page->call('togglePin', $later->id)->assertSeeInOrder(['Ring the plumber', 'Order the tiles']);
        $this->assertFalse($later->fresh()->pinned);
        $this->assertFalse($soon->fresh()->pinned);
    }

    public function test_snooze_keeps_the_time_of_day_and_counts_a_late_task_from_today(): void
    {
        $late = Task::create(['title' => 'Late', 'due_at' => '2026-11-18 08:00']);     // 11:00 Erbil, two days ago
        $later = Task::create(['title' => 'Later', 'due_at' => '2026-11-23 11:00']);
        $done = Task::create(['title' => 'Done', 'due_at' => '2026-11-18 08:00', 'status' => 'done']);

        Livewire::test(Tasks::class)->assertSee('Snooze')->assertSee('Sat 21 Nov')   // the late one's "1 day", shown before it is chosen
            ->call('snooze', $late->id, 1)->call('snooze', $later->id, 7)
            ->call('snooze', $done->id, 1)->call('snooze', $later->id, 30);   // done, or a made-up length: left alone

        $this->assertSame(['2026-11-21 08:00:00', '2026-11-30 11:00:00', '2026-11-18 08:00:00'],
            [$late->fresh()->getRawOriginal('due_at'), $later->fresh()->getRawOriginal('due_at'), $done->fresh()->getRawOriginal('due_at')]);
    }

    public function test_undo_takes_back_the_last_move_snooze_or_delete(): void
    {
        $undo = fn ($page) => $page->dispatch('undo-task', token: session('task_undo')['token']);
        $task = Task::create(['title' => 'Weekly check', 'due_at' => '2026-11-22 10:00', 'repeat' => 'weekly', 'pinned' => true,
            'checklist' => [['text' => 'Photos', 'done' => true]]]);
        $page = Livewire::test(Tasks::class);

        // done: the next one appears; undone, it goes again and this one is as it was
        $page->call('move', $task->id, 'done')->assertNotified('Moved to Done — the next one is in To do');
        $this->assertSame(2, Task::count());
        $undo($page)->assertNotified('Undone');
        $this->assertSame([1, 'todo', 'weekly', null, true], [Task::count(), $task->fresh()->status, $task->fresh()->repeat, $task->fresh()->done_at, $task->fresh()->pinned]);

        // snoozed, and back
        $page->call('snooze', $task->id, 7);
        $undo($page);
        $this->assertSame('2026-11-22 10:00:00', $task->fresh()->getRawOriginal('due_at'));

        // deleted, and back with the same id and its checklist
        $page->callAction([TestAction::make('openTask')->arguments(['task' => $task->id]), TestAction::make('deleteTask')])->assertNotified('Task deleted');
        $this->assertNull(Task::find($task->id));
        $undo($page);
        $this->assertSame([['text' => 'Photos', 'done' => true]], Task::findOrFail($task->id)->checklist);

        // only once, and only the last
        $page->dispatch('undo-task', token: 'made-up')->assertNotified('That can no longer be undone');
        $this->assertNull(session('task_undo'));
    }

    public function test_labels_colour_the_cards_filter_the_page_and_can_be_renamed(): void
    {
        $page = Livewire::test(Tasks::class)->callAction('newTask', ['title' => 'Survey the plot', 'due_at' => '2026-11-22 10:00', 'labels' => ['site', 'client']])
            ->assertHasNoActionErrors();
        Task::create(['title' => 'Draw the plans', 'due_at' => '2026-11-25 10:00', 'labels' => ['drawing']]);
        $this->assertSame(['site', 'client'], Task::query()->where('title', 'Survey the plot')->sole()->labels);

        $page->call('$refresh')->assertSee('Survey the plot')->assertSee('Draw the plans')
            ->assertSeeHtml('<span class="tk-chip" style="--c: #e8833a">Site</span>')
            ->set('label', 'site')->assertSee('Survey the plot')->assertDontSee('Draw the plans')
            ->set('show', 'timeline')->assertSee('Survey the plot')->assertDontSee('Draw the plans');

        // renamed, the tasks keep it; a new one gets a key of its own
        $page->callAction('labels', ['labels' => [
            ['key' => 'site', 'name' => 'On site', 'colour' => 'teal'],
            ['key' => null, 'name' => 'Urgent', 'colour' => 'pink'],
        ]])->assertHasNoActionErrors();
        $labels = Tasks::labels();
        $this->assertSame('On site', $labels['site']['name']);
        $this->assertMatchesRegularExpression('/^[a-z0-9]{8}$/', $labels->firstWhere('name', 'Urgent')['key']);
        Livewire::test(Tasks::class)->assertSeeHtml('<span class="tk-chip" style="--c: #2bb3a6">On site</span>');
    }

    public function test_quick_add_reads_a_task_from_one_line(): void
    {
        $room = $this->room();   // Shilan, of Ankawa House
        $read = function (string $text) {
            $q = QuickTask::parse($text);

            return [$q['title'], $q['due_at']->format('D j M Y H:i'), $q['labels'], $q['priority'], $q['client_room_id'], $q['project_id']];
        };
        // it is Friday 20 Nov 2026, 12:00 in Erbil
        $this->assertSame(['Call Dara', 'Sat 21 Nov 2026 16:00', ['client'], 'high', null, $this->project->id], $read('Call Dara tomorrow 4pm #client !high @Ankawa'));
        $this->assertSame(['Send the drawings', 'Fri 20 Nov 2026 17:00', [], 'normal', $room->id, null], $read('send the drawings @shilan fri'));
        $this->assertSame(['Site visit', 'Mon 23 Nov 2026 09:00', [], 'normal', null, null], $read('site visit on monday at 9'));
        $this->assertSame('Sat 5 Dec 2026 17:00', $read('pay the engineer 5/12')[1]);
        $this->assertSame(['Renew the permit', 'Sun 3 Jan 2027 17:00'], array_slice($read('renew the permit by 3 jan'), 0, 2));   // passed this year: next year's
        $this->assertSame('Thu 26 Nov 2026 17:00', $read('order tiles nov 26th')[1]);
        $this->assertSame(['Ring the plumber', 'Sat 21 Nov 2026 10:30'], array_slice($read('ring the plumber 10:30'), 0, 2));   // passed today: tomorrow's
        $this->assertSame(['Order tiles', 'Fri 4 Dec 2026 17:00', ['site']], array_slice($read('Order tiles in 2 weeks #site'), 0, 3));
        $this->assertSame(['Check the e-mails', 'Sat 21 Nov 2026 17:00'], array_slice($read('check the e-mails'), 0, 2));
        $this->assertSame(['Fix the sun shading', 'Fri 20 Nov 2026 20:00'], array_slice($read('fix the sun shading tonight'), 0, 2));   // "sun" alone is a word
        $this->assertSame(['Tidy the library', 'low'], [$read('!low tidy the library')[0], $read('!low tidy the library')[3]]);
        $this->assertSame(['Ask about #balcony glass', []], [$read('ask about #balcony glass')[0], $read('ask about #balcony glass')[2]]);   // no such label: kept

        // typed on the page: a preview, then Enter adds it; Undo takes it away
        $page = Livewire::test(Tasks::class)->set('quick', 'Call Dara tomorrow 4pm #client')
            ->assertSee('Due Sat 21 Nov, 16:00')->assertSee('Enter to add');
        $page->call('quickAdd')->assertSet('quick', '')->assertNotified('Added “Call Dara” — due Sat 21 Nov, 16:00');
        $this->assertSame(['Call Dara', '2026-11-21 13:00:00', ['client']], [Task::sole()->title, Task::sole()->getRawOriginal('due_at'), Task::sole()->labels]);
        $page->dispatch('undo-task', token: session('task_undo')['token']);
        $this->assertSame(0, Task::count());
        $page->call('quickAdd', '#client tomorrow')->assertNotified('Write what to do — and a day or time if it has one');
        $this->assertSame(0, Task::count());
    }

    public function test_the_timer_counts_time_one_task_at_a_time_against_its_estimate(): void
    {
        $page = Livewire::test(Tasks::class)->callAction('newTask', ['title' => 'Concept design', 'due_at' => '2026-11-27 17:00',
            'project_id' => $this->project->id, 'estimate_minutes' => 1.5])->assertHasNoActionErrors();
        $a = Task::sole();
        $b = Task::create(['title' => 'Call the engineer', 'due_at' => '2026-11-21 17:00', 'project_id' => $this->project->id]);
        $this->assertSame([90, 0], [$a->estimate_minutes, $a->spent_minutes]);   // written in hours

        // started: begun; another started: the first one stops with its time
        $page->call('toggleTimer', $a->id);
        $this->assertSame('doing', $a->fresh()->status);
        $this->travel(50)->minutes();
        $page->call('toggleTimer', $b->id)->assertSee('Timing')->assertSee('Call the engineer');
        $this->assertSame([50, null], [$a->fresh()->spent_minutes, $a->fresh()->timer_started_at]);
        $this->travel(20)->minutes();
        $page->call('toggleTimer', $b->id);
        $this->assertSame(20, $b->fresh()->spent_minutes);

        // over the estimate, and done stops a running timer
        $page->call('toggleTimer', $a->id);
        $this->travel(55)->minutes();
        $this->assertTrue($a->fresh()->isOverEstimate());
        $page->call('move', $a->id, 'done')->assertSee('Took 1h 45m of 1h 30m');
        $this->assertSame([105, null], [$a->fresh()->spent_minutes, $a->fresh()->timer_started_at]);

        // the project's tab: each task's time, and the totals
        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $this->project, 'pageClass' => EditProject::class])
            ->assertSee('1h 45m')->assertSee('20m')->assertSee('2h 5m')->assertSee('1h 30m');
        $this->assertSame(['0m', '45m', '1h 30m', '6h', '2h 5m'], array_map(Task::hm(...), [0, 45, 90, 360, 125]));
    }

    public function test_the_calendar_shows_the_month_and_a_task_dragged_to_another_day_keeps_its_time(): void
    {
        $task = Task::create(['title' => 'Submit the permit', 'due_at' => '2026-11-24 11:30']);   // 14:30 Erbil
        Task::create(['title' => 'Pour the December slab', 'due_at' => '2026-12-15 09:00']);
        $page = Livewire::test(Tasks::class)->set('show', 'calendar')
            ->assertSee('November 2026')->assertSee('14:30')->assertSee('Submit the permit')->assertDontSee('Pour the December slab')
            ->assertSeeHtml('data-day="2026-11-01"')->assertSeeHtml('data-day="2026-12-05"');   // Sunday 1 Nov to Saturday 5 Dec

        $page->call('moveToDay', $task->id, '2026-11-27')->assertNotified('Moved to Fri 27 Nov');
        $this->assertSame('2026-11-27 11:30:00', $task->fresh()->getRawOriginal('due_at'));
        $page->dispatch('undo-task', token: session('task_undo')['token']);
        $this->assertSame('2026-11-24 11:30:00', $task->fresh()->getRawOriginal('due_at'));
        $page->call('moveToDay', $task->id, 'soon');
        $this->assertSame('2026-11-24 11:30:00', $task->fresh()->getRawOriginal('due_at'));

        $page->set('month', '2026-12')->assertSee('December 2026')->assertSee('Pour the December slab')
            ->set('month', '2026-13')->assertSee('November 2026');   // a made-up month: this one
    }

    public function test_a_bar_dragged_on_the_timeline_moves_the_task_or_only_its_end(): void
    {
        $a = Task::create(['title' => 'Permit set', 'starts_on' => '2026-11-20', 'due_at' => '2026-11-24 11:30']);   // 14:30 Erbil
        $b = Task::create(['title' => 'Renders', 'due_at' => '2026-11-30 07:00']);   // made today: its bar starts now
        $done = Task::create(['title' => 'Filed', 'due_at' => '2026-11-24 11:30', 'status' => 'done']);
        $page = Livewire::test(Tasks::class)->set('show', 'timeline')->assertSeeHtml('data-due="2026-11-24"');
        $when = fn (Task $t) => [$t->fresh()->starts_on?->toDateString(), $t->fresh()->getRawOriginal('due_at')];

        $page->call('shiftOnTimeline', $a->id, 3)->assertNotified('Moved to Fri 27 Nov');
        $this->assertSame(['2026-11-23', '2026-11-27 11:30:00'], $when($a));
        $page->dispatch('undo-task', token: session('task_undo')['token']);
        $this->assertSame(['2026-11-20', '2026-11-24 11:30:00'], $when($a));

        $page->call('shiftOnTimeline', $b->id, -2);
        $this->assertSame(['2026-11-18', '2026-11-28 07:00:00'], $when($b));   // the start it showed moves with it

        $page->call('shiftOnTimeline', $a->id, 2, true)->assertNotified('Due Thu 26 Nov');
        $this->assertSame(['2026-11-20', '2026-11-26 11:30:00'], $when($a));
        $page->call('shiftOnTimeline', $a->id, -12, true);   // back past its start: the start comes too
        $this->assertSame(['2026-11-14', '2026-11-14 11:30:00'], $when($a));

        $page->call('shiftOnTimeline', $done->id, 3)->call('shiftOnTimeline', $b->id, 0);
        $this->assertSame([null, '2026-11-24 11:30:00'], $when($done));
        $this->assertSame(['2026-11-18', '2026-11-28 07:00:00'], $when($b));
    }

    public function test_shift_the_rest_moves_a_late_step_and_the_later_tasks_of_its_project(): void
    {
        $in = ['project_id' => $this->project->id];
        $late = Task::create($in + ['title' => 'Concept', 'due_at' => '2026-11-18 07:00']);   // 2 days and a bit late
        $next = Task::create($in + ['title' => 'Review', 'starts_on' => '2026-11-21', 'due_at' => '2026-11-25 14:00']);
        $room = Task::create(['client_room_id' => $this->room()->id, 'title' => 'Meet Shilan', 'due_at' => '2026-11-28 08:00']);
        $earlier = Task::create($in + ['title' => 'Survey', 'due_at' => '2026-11-10 07:00']);
        $done = Task::create($in + ['title' => 'Brief', 'due_at' => '2026-11-30 07:00', 'status' => 'done']);
        $other = Task::create(['title' => 'Studio tax', 'due_at' => '2026-11-25 14:00']);
        $due = fn (Task $t) => $t->fresh()->getRawOriginal('due_at');
        $shift = fn (Task $t) => [TestAction::make('openTask')->arguments(['task' => $t->id]), TestAction::make('shiftRest')];

        $page = Livewire::test(Tasks::class)
            ->callAction($shift($late))->assertNotified('Moved 3 tasks 3 days later');   // as many days as it is late
        $this->assertSame(['2026-11-21 07:00:00', '2026-11-28 14:00:00', '2026-12-01 08:00:00'], [$due($late), $due($next), $due($room)]);
        $this->assertSame('2026-11-24', $next->fresh()->starts_on->toDateString());
        $this->assertSame(['2026-11-10 07:00:00', '2026-11-30 07:00:00', '2026-11-25 14:00:00'], [$due($earlier), $due($done), $due($other)]);

        $page->dispatch('undo-task', token: session('task_undo')['token']);
        $this->assertSame(['2026-11-18 07:00:00', '2026-11-25 14:00:00', '2026-11-28 08:00:00'], [$due($late), $due($next), $due($room)]);

        $page->callAction($shift($next), ['days' => -1])->assertNotified('Moved 2 tasks 1 day sooner');
        $this->assertSame(['2026-11-24 14:00:00', '2026-11-27 08:00:00'], [$due($next), $due($room)]);
        $page->callAction($shift($next), ['days' => 0])->assertHasFormErrors(['days']);
        Livewire::test(Tasks::class)->assertActionHidden($shift($other));   // no project: nothing to shift with it
    }

    public function test_today_shows_only_what_is_late_due_by_tonight_pinned_or_timed(): void
    {
        // Fri 20 Nov, 12:00 in Erbil
        $late = Task::create(['title' => 'Late drawing', 'due_at' => '2026-11-18 07:00']);
        Task::create(['title' => 'Call the mayor', 'due_at' => '2026-11-20 18:00', 'labels' => ['client']]);   // 21:00
        Task::create(['title' => 'After midnight', 'due_at' => '2026-11-20 21:30']);   // 00:30 tomorrow
        Task::create(['title' => 'Pinned plan', 'due_at' => '2026-12-01 07:00', 'pinned' => true]);
        Task::create(['title' => 'Being timed', 'due_at' => '2026-12-02 07:00', 'timer_started_at' => now()->subMinutes(5), 'status' => 'doing']);
        Task::create(['title' => 'Filed today', 'due_at' => '2026-11-20 07:00', 'status' => 'done']);
        Task::whereKey(Task::create(['title' => 'Filed yesterday', 'due_at' => '2026-11-19 07:00', 'status' => 'done'])->id)->update(['done_at' => '2026-11-19 10:00']);

        $page = Livewire::test(Tasks::class)->set('label', 'drawing')->set('show', 'today')
            ->assertSee('Friday 20 November')->assertSee('4 left for today · 1 done')
            ->assertSee('Late drawing')->assertSee('Call the mayor')->assertSee('Pinned plan')->assertSee('Being timed')
            ->assertDontSee('After midnight')->assertDontSee('Filed today')->assertDontSee('in the next 7 days')
            ->assertDontSee('Start →');   // to do here goes straight to Done

        $page->call('move', $late->id, 'done')->assertSee('3 left for today · 2 done')->assertDontSee('Late drawing');
    }

    public function test_a_photo_from_a_card_goes_onto_the_task_and_not_onto_its_next_one(): void
    {
        Storage::fake(Task::DISK, ['serve' => true]);
        $task = Task::create(['title' => 'Check the slab', 'due_at' => '2026-11-21 07:00', 'repeat' => 'weekly']);
        $page = Livewire::test(Tasks::class)
            ->set('photo', UploadedFile::fake()->image('slab.jpg'))->call('attachPhoto', $task->id)->assertNotified('Added to “Check the slab”');
        $path = $task->fresh()->attachments[0];
        $this->assertStringStartsWith('tasks/', $path);
        Storage::disk(Task::DISK)->assertExists($path);
        $this->assertSame([$path => 'slab.jpg'], $task->fresh()->attachment_names);
        $page->assertSeeHtml('alt="slab.jpg"');

        $page->set('photo', UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'))->call('attachPhoto', $task->id)
            ->assertNotified('That could not be added: a photo or PDF, up to 30 MB');
        $this->assertCount(1, $task->fresh()->attachments);

        $page->call('move', $task->id, 'done');   // this week's photos stay with this week
        $this->assertNull(Task::query()->where('status', 'todo')->sole()->attachments);
    }

    public function test_the_bell_rings_an_hour_before_a_task_is_due_and_again_when_it_turns_late(): void
    {
        // 12:00 in Erbil
        Queue::fake();   // the host runs no queue worker: nothing may wait for one
        $user = auth()->user();
        $task = Task::create(['title' => 'Call Dara', 'due_at' => '2026-11-20 09:40', 'client_room_id' => $this->room()->id]);   // 12:40
        Task::create(['title' => 'Later on', 'due_at' => '2026-11-20 11:00']);
        Task::create(['title' => 'Late for days', 'due_at' => '2026-11-17 07:00']);   // red on the board already: no flood of old ones
        Task::create(['title' => 'Filed', 'due_at' => '2026-11-20 09:30', 'status' => 'done']);
        $titles = fn () => $user->notifications()->get()->sortBy('created_at')->pluck('data.title')->values()->all();

        $this->assertSame(1, TaskReminders::send());
        $this->assertSame(0, TaskReminders::send());   // once
        $this->assertSame(['Due at 12:40: Call Dara'], $titles());
        $this->assertSame('Within the hour. · Shilan · Ankawa House', $user->notifications()->first()->data['body']);

        $this->travel(45)->minutes();
        $this->assertSame(1, TaskReminders::send());
        $this->assertSame(0, TaskReminders::send());
        $this->assertSame(['Due at 12:40: Call Dara', 'Late: Call Dara'], $titles());

        $task->fresh()->update(['due_at' => now()->addMinutes(30)]);   // snoozed: rings again when it comes round
        $this->travel(1)->minutes();
        $this->get(Tasks::getUrl())->assertOk();   // an admin page checks
        $this->assertCount(3, $titles());
        Queue::assertNothingPushed();
    }

    public function test_the_morning_email_lists_what_is_late_and_due_today_once_a_day(): void
    {
        Task::create(['title' => 'Send the invoice', 'due_at' => '2026-11-19 10:00']);   // late
        Task::create(['title' => 'Call the plasterer', 'due_at' => '2026-11-20 15:00', 'project_id' => $this->project->id, 'estimate_minutes' => 90]);
        Task::create(['title' => 'Order the tiles', 'due_at' => '2026-11-21 09:00']);   // tomorrow
        Task::create(['title' => 'Already done', 'due_at' => '2026-11-20 10:00', 'status' => 'done']);

        Carbon::setTestNow('2026-11-20 04:59');   // 07:59 in Erbil
        $this->assertFalse(MorningTasks::isDue());
        Carbon::setTestNow('2026-11-20 05:00');
        $this->assertTrue(MorningTasks::isDue());
        MorningTasks::sendIfDue();
        MorningTasks::sendIfDue();

        Mail::assertSent(MorningTasksMail::class, 1);
        $mail = Mail::sent(MorningTasksMail::class)->first();
        $this->assertSame([['Send the invoice'], ['Call the plasterer']], [$mail->late->pluck('title')->all(), $mail->today->pluck('title')->all()]);
        $this->assertSame('Your tasks — 1 due today, 1 late (Fri 20 Nov)', $mail->envelope()->subject);
        $html = $mail->render();
        $this->assertStringContainsString('at 18:00', $html);
        $this->assertStringContainsString('Ankawa House · 1h 30m left to do', $html);
        $this->assertStringNotContainsString('Order the tiles', $html);

        // a day with nothing late or due: marked, and nothing sent
        Task::query()->update(['status' => 'done']);
        Carbon::setTestNow('2026-11-21 06:00');
        MorningTasks::sendIfDue();
        Mail::assertSent(MorningTasksMail::class, 1);
        $this->assertFalse(MorningTasks::isDue());

        SiteSetting::put(MorningTasks::ENABLED, '0', 'alerts');
        SiteSetting::flushCache();
        Carbon::setTestNow('2026-11-22 06:00');
        $this->assertFalse(MorningTasks::isDue());
    }

    public function test_the_phone_calendar_feed_lists_open_tasks_behind_a_secret_address(): void
    {
        Task::create(['title' => 'Pour the slab, level 2; check rebar', 'due_at' => '2026-11-21 06:00', 'project_id' => $this->project->id,
            'pinned' => true, 'notes' => "Bring the plans\nand the level"]);
        Task::create(['title' => 'Already done', 'due_at' => '2026-11-21 07:00', 'status' => 'done']);
        $kurdish = trim(str_repeat('ئەندازیار ', 12));
        Task::create(['title' => $kurdish, 'due_at' => '2026-11-22 07:00']);

        $this->get('/calendar/nothing-yet.ics')->assertNotFound();   // no address made yet
        Livewire::test(Tasks::class)->mountAction('phoneCalendar')->assertMountedActionModalSee(TaskCalendar::url());
        $url = TaskCalendar::url();

        auth()->logout();   // a calendar app cannot log in
        $ics = $this->get($url)->assertOk()->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->getContent();
        foreach (explode("\r\n", rtrim($ics)) as $line) {
            $this->assertTrue(strlen($line) <= 75 && mb_check_encoding($line, 'UTF-8'), $line);   // folded, never through a letter
        }
        $flat = str_replace("\r\n ", '', $ics);
        $this->assertStringContainsString("DTSTART:20261121T060000Z\r\n", $flat);
        $this->assertStringContainsString("SUMMARY:📌 Pour the slab\\, level 2\\; check rebar\r\n", $flat);
        $this->assertStringContainsString('DESCRIPTION:Ankawa House\nBring the plans\nand the level\nhttp', $flat);
        $this->assertStringContainsString('SUMMARY:'.$kurdish."\r\n", $flat);
        $this->assertStringNotContainsString('Already done', $flat);

        // a new address shuts the old out
        $this->actingAs(User::first());
        Livewire::test(Tasks::class)->callAction([TestAction::make('phoneCalendar'), TestAction::make('renewCalendar')]);
        $this->get($url)->assertNotFound();
        $this->get(TaskCalendar::url())->assertOk();
    }

    public function test_clearing_today_celebrates_once_and_the_streak_counts_days_with_nothing_late(): void
    {
        Carbon::setTestNow('2026-11-14 09:00');   // the first task: the streak counts from here
        $spoiler = Task::create(['title' => 'Late over a night', 'due_at' => '2026-11-16 08:00']);
        Carbon::setTestNow('2026-11-17 10:00');
        $spoiler->update(['status' => 'done']);   // still late when Mon 16 Nov ended (Erbil)
        Carbon::setTestNow('2026-11-20 09:00');   // Friday, 12:00 in Erbil
        $later = Task::create(['title' => 'Call the plasterer', 'due_at' => '2026-11-20 15:00']);
        $late = Task::create(['title' => 'Send the invoice', 'due_at' => '2026-11-20 06:00']);
        $this->assertSame(0, Task::streak());

        $page = Livewire::test(Tasks::class)->set('show', 'today')->assertSee('2 left for today');
        $page->call('move', $late->id, 'done')->assertNotDispatched('tasks-cleared');
        $this->assertSame(4, Task::streak());   // Tue 17 to today

        $page->call('move', $later->id, 'done')->assertDispatched('tasks-cleared')->assertNotified('All clear for today 🎉')
            ->assertSee('🔥 4 days in a row with nothing late');
        $page->call('togglePin', $later->id)->assertNotDispatched('tasks-cleared');   // once, not on every redraw
        Livewire::test(Tasks::class)->assertNotDispatched('tasks-cleared')   // nor on opening a page already clear
            ->assertSeeHtml('<b>4</b>days in a row with nothing late');
    }

    public function test_the_sunday_report_lists_the_tasks_done_last_week_with_their_time(): void
    {
        $done = function (string $at, string $title, array $more = []) {
            Carbon::setTestNow($at);   // done then
            Task::create(['title' => $title, 'due_at' => $at, 'status' => 'done', ...$more]);
        };
        $done('2026-11-11 10:00', 'Draw the stair', ['project_id' => $this->project->id, 'estimate_minutes' => 60, 'spent_minutes' => 90]);
        $done('2026-11-13 10:00', 'Send the invoice');
        $done('2026-11-16 10:00', 'This week, not last');
        Task::create(['title' => 'Still open', 'due_at' => '2026-11-12 10:00']);

        Carbon::setTestNow('2026-11-15 06:00');   // Sunday 09:00 in Erbil
        $report = WeeklyReport::data(...WeeklyReport::lastWeek());
        $this->assertSame(['Draw the stair', 'Send the invoice'], $report['tasksDone']->pluck('title')->all());

        $html = (new WeeklyReportMail($report))->render();
        $this->assertStringContainsString('Wed 11 Nov · Ankawa House', $html);
        $this->assertStringContainsString('1h 30m of 1h', $html);
        $this->assertStringContainsString('2 done, 1h 30m of work on the timer.', $html);
        $this->assertStringNotContainsString('Still open', $html);
    }

    public function test_the_busy_meter_adds_up_the_work_left_by_day_and_warns_of_a_full_week(): void
    {
        // Fri 20 Nov, 12:00 in Erbil; the week is Sun 15 – Sat 21 Nov
        Task::create(['title' => 'Sections', 'due_at' => '2026-11-21 07:00', 'estimate_minutes' => 360, 'spent_minutes' => 60]);
        Task::create(['title' => 'Elevations', 'due_at' => '2026-11-21 12:00', 'estimate_minutes' => 240]);
        $late = Task::create(['title' => 'Site survey', 'due_at' => '2026-11-18 07:00', 'estimate_minutes' => 1800]);   // late: today's work
        Task::create(['title' => 'Call back', 'due_at' => '2026-11-20 15:00', 'estimate_minutes' => 120]);
        Task::create(['title' => 'Filed', 'due_at' => '2026-11-21 07:00', 'estimate_minutes' => 600, 'status' => 'done']);
        Task::create(['title' => 'No guess', 'due_at' => '2026-11-21 07:00']);
        Task::create(['title' => 'Next week', 'due_at' => '2026-11-25 07:00', 'estimate_minutes' => 60]);

        $busy = Tasks::busy(Task::all());
        $this->assertSame(['2026-11-20' => 1920, '2026-11-21' => 540, '2026-11-25' => 60], $busy->sortKeys()->all());
        $week = Tasks::busyWeek($busy);
        $this->assertSame(['2026-11-15', 2460], [$week['from']->toDateString(), $week['minutes']]);

        $page = Livewire::test(Tasks::class)->assertSee('15 Nov – 21 Nov: 41h of work, more than a 40h week')
            ->set('label', 'client')->assertSee('Busy week')   // all the work counts, whatever is shown
            ->set('label', '')->set('show', 'calendar')
            ->assertSeeHtml('cal-day__h busy-3')->assertSee('32h')->assertSee('9h')
            ->assertSee('Week of 15 Nov: 41h of work, more than a 40h week')->assertSee('Week of 22 Nov: 1h of work of 40h')
            ->set('show', 'timeline')->assertSeeHtml('tl-heat busy-3')->assertSee('Sat 21 Nov: 9h of work');

        $late->update(['status' => 'done']);
        $page->set('show', 'board')->assertDontSee('Busy week');
    }

    public function test_templates_add_their_tasks_counted_from_a_day_and_can_be_changed(): void
    {
        $this->assertSame('New house', Tasks::templates()[0]['name']);
        Livewire::test(Tasks::class)->callAction('fromTemplate', ['template' => 0, 'start' => '2026-11-21', 'client_room_id' => $this->room()->id])
            ->assertHasNoActionErrors();
        $first = Task::query()->orderBy('id')->first();
        $this->assertSame(7, Task::count());
        $this->assertSame(['Site visit and survey', '2026-11-21', '2026-11-23 14:00:00', $this->project->id],   // 3 days: to 17:00 Erbil on the 23rd
            [$first->title, $first->starts_on->toDateString(), $first->getRawOriginal('due_at'), $first->project_id]);

        Livewire::test(Tasks::class)->callAction('templates', ['templates' => [
            ['name' => 'Kitchen', 'tasks' => [['title' => 'Measure', 'after' => 2, 'days' => 1, 'priority' => 'high']]],
        ]])->assertHasNoActionErrors();
        // the starter one is in the form already; the new one is added after it, as a plain list
        $saved = json_decode(SiteSetting::query()->where('key', Tasks::TEMPLATES)->value('value'), true);
        $this->assertSame(['New house', 'Kitchen'], array_column($saved, 'name'));
        $this->assertSame([['title' => 'Measure', 'after' => 2, 'days' => 1, 'priority' => 'high']], $saved[1]['tasks']);
        $this->assertSame('Kitchen', Tasks::templates()[1]['name']);

        // the timeline: bars grouped by project, the project's tab, the dashboard card
        Livewire::test(Tasks::class)->set('show', 'timeline')->assertSee('<p class="tl-group">Ankawa House</p>', false)->assertSee('tl-bar', false)->assertSee('Permit drawings');
        Livewire::test(TasksRelationManager::class, ['ownerRecord' => $this->project, 'pageClass' => EditProject::class])
            ->assertOk()->assertSee('Concept design')->assertSee('3 days left');
        $this->assertSame('7', TasksRelationManager::getBadge($this->project, EditProject::class));
        $this->get('/studio-aks-panel')->assertOk();
        Livewire::test(TasksDue::class)->assertSee('Open the board')->assertSee('Site visit and survey')->assertSee('3 days left');
    }

    public function test_the_website_makes_tasks_and_closes_them_once_answered(): void
    {
        // a visit: due within a day, or by the visit itself if sooner; answered, it closes
        $visit = VisitRequest::create(['name' => 'Dara', 'phone' => '0750 000 1111', 'kind' => 'site', 'about' => 'A plot',
            'wanted_on' => '2026-11-24', 'wanted_time' => '16:30']);
        $task = Task::sole();
        $this->assertSame(['Answer Dara: Site visit on Tue 24 Nov, 16:30', 'high', '2026-11-21 09:00:00'],
            [$task->title, $task->priority, $task->getRawOriginal('due_at')]);
        $visit->update(['status' => VisitRequest::CONFIRMED]);
        $this->assertSame('done', $task->fresh()->status);

        // a change the client asks for: the room's and its project's, until it has a price
        $room = $this->room();
        $change = $room->changes()->create(['title' => 'Bigger window', 'asked_by_client' => true, 'status' => RoomChange::ASKED]);
        $ask = Task::query()->open()->sole();
        $this->assertSame(['Price the change “Bigger window”', $room->id, $this->project->id], [$ask->title, $ask->client_room_id, $ask->project_id]);
        $change->update(['status' => RoomChange::PRICED, 'price' => 400]);
        $this->assertSame(0, Task::query()->open()->count());

        // meeting notes sent back, through the room itself; asked again, it closes
        $meeting = $room->meetings()->create(['met_on' => '2026-11-19', 'decisions' => 'Kitchen moves east']);
        $pin = $this->post('/room/'.$room->token.'/pin', ['pin' => $room->pin]);
        $this->withCookie($room->accessCookie(), $pin->getCookie($room->accessCookie())->getValue())
            ->post('/room/'.$room->token.'/meetings/'.$meeting->id, ['answer' => 'objected', 'note' => 'It was 50 cm', 'v' => $meeting->updated_at->timestamp]);
        $this->assertStringStartsWith('Correct the notes of the meeting on 19 Nov', Task::query()->open()->sole()->title);
        $meeting->refresh()->update(['status' => RoomMeeting::PENDING, 'client_note' => null]);
        $this->assertSame(0, Task::query()->open()->count());

        // a document about to expire: with its reminder, once
        SiteSetting::put(StudioMail::TO, 'studio@example.com', 'alerts');
        $room->documents()->create(['group' => 'permits', 'title' => 'Building permit', 'file_path' => 'rooms/documents/p.pdf', 'file_name' => 'p.pdf', 'expires_on' => '2026-12-05']);
        DocumentReminders::sendIfDue();
        $renew = Task::query()->open()->sole();
        $this->assertSame(['Renew “Building permit” — it expires on 5 Dec 2026', '2026-12-05 06:00:00'], [$renew->title, $renew->getRawOriginal('due_at')]);
        DocumentReminders::sendIfDue();
        $this->assertSame(1, Task::query()->open()->count());
    }
}
