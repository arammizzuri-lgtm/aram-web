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
use App\Support\DocumentReminders;
use App\Support\StudioMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
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
