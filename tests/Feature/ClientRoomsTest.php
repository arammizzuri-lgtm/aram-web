<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientRooms\ClientRoomResource;
use App\Filament\Resources\ClientRooms\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\ClientRooms\RelationManagers\DrawingsRelationManager;
use App\Filament\Resources\ClientRooms\Pages\EditClientRoom;
use App\Mail\RoomActivity;
use App\Models\ClientRoom;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\RoomComment;
use App\Models\RoomDrawing;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\StudioMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A client's private room: the link and the PIN, what is inside it, the
 * client approving drawings and commenting, and the studio's side of it.
 */
class ClientRoomsTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Mail::fake();
        SiteSetting::put(StudioMail::TO, 'studio@example.com', 'alerts');

        $this->project = Project::create([
            'name' => 'Ankawa House', 'name_ku' => 'خانووی عەنکاوە', 'category' => 'residential', 'status' => 'Under Construction',
            'size' => 'default', 'imgs' => ['https://example.com/cover.jpg'], 'is_published' => true,
        ]);
        ProjectUpdate::create(['project_id' => $this->project->id, 'date' => '2026-09-01', 'title_en' => 'Foundations poured', 'is_private' => false]);
        ProjectUpdate::create(['project_id' => $this->project->id, 'date' => '2026-10-01', 'title_en' => 'Kitchen layout agreed', 'is_private' => true]);
    }

    private function room(array $attrs = []): ClientRoom
    {
        return ClientRoom::create($attrs + ['project_id' => $this->project->id, 'client_name' => 'Shilan', 'client_phone' => '0750 123 4567', 'lang' => 'en']);
    }

    private function drawing(ClientRoom $room, string $title = 'Ground floor plan'): RoomDrawing
    {
        Storage::disk('local')->put('rooms/drawings/'.md5($title).'.pdf', "%PDF-1.4\n%%EOF");

        return $room->drawings()->create(['title' => $title, 'file_path' => 'rooms/drawings/'.md5($title).'.pdf', 'file_name' => 'plan.pdf']);
    }

    /** Enter the PIN, and carry the cookie it sets from then on. */
    private function enter(ClientRoom $room, ?string $pin = null): void
    {
        $response = $this->post('/room/'.$room->token.'/pin', ['pin' => $pin ?? $room->pin])->assertRedirect('/room/'.$room->token);
        $this->withCookie($room->accessCookie(), $response->getCookie($room->accessCookie())->getValue());
    }

    public function test_a_room_comes_with_a_secret_link_and_a_pin_and_shows_nothing_without_the_pin(): void
    {
        $room = $this->room();
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9]{40}$/', $room->token);
        $this->assertMatchesRegularExpression('/^\d{4}$/', $room->pin);
        $this->assertNotSame($room->pin, $room->getRawOriginal('pin'));        // kept encrypted

        $page = $this->get('/room/'.$room->token)->assertOk();
        $page->assertSee('Enter your PIN')->assertSee('Ankawa House')
            ->assertDontSee('Foundations poured')->assertDontSee('Kitchen layout agreed');
        $page->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $page->headers->get('Cache-Control'));

        $this->get('/room/'.str_repeat('a', 40))->assertNotFound();
        $this->get('/room/short')->assertNotFound();
    }

    public function test_the_right_pin_opens_the_room_with_every_update_drawing_and_payment(): void
    {
        $room = $this->room(['note' => 'Welcome to your room.']);
        $this->drawing($room);
        $room->payments()->create(['label' => 'Concept design', 'amount' => 4000, 'paid_on' => '2026-08-01']);
        $room->payments()->create(['label' => 'Permit drawings', 'amount' => 6000, 'due_on' => now()->subDays(3)->toDateString()]);

        $this->enter($room);
        $page = $this->get('/room/'.$room->token)->assertOk();
        $page->assertSee('Welcome, Shilan')->assertSee('Welcome to your room.')
            // newest first, the private one marked (on its date line, above its title)
            ->assertSeeInOrder(['Only in your room', 'Kitchen layout agreed', 'Foundations poured'])
            ->assertSee('Ground floor plan')->assertSee('Waiting for your decision')->assertSee('Approve')
            ->assertSee('<bdi>$4,000</bdi> paid of <bdi>$10,000</bdi>', false)->assertSee('Overdue since')
            ->assertSee('<textarea name="body"', false);
        $this->assertNotNull($room->fresh()->last_seen_at);

        // a phone-number-style PIN typed on a Kurdish keyboard works too
        $digits = strtr($room->pin, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
        $this->post('/room/'.$room->token.'/pin', ['pin' => $digits])->assertCookie($room->accessCookie());

        // a new PIN locks every phone out again
        $room->update(['pin' => $room->pin === '1111' ? '2222' : '1111']);
        $this->get('/room/'.$room->token)->assertSee('Enter your PIN');
    }

    public function test_wrong_pins_are_limited_per_room(): void
    {
        $room = $this->room(['pin' => '4821']);
        $this->post('/room/'.$room->token.'/pin', ['pin' => '0000'])->assertSessionHas('pin_error', 'room_pin_wrong');
        $this->get('/room/'.$room->token)->assertSee('That PIN is not right');

        foreach (range(1, 4) as $_) {
            $this->post('/room/'.$room->token.'/pin', ['pin' => '0000']);
        }
        // even the right one now waits
        $this->post('/room/'.$room->token.'/pin', ['pin' => '4821'])
            ->assertSessionHas('pin_error', 'room_pin_locked')->assertCookieMissing($room->accessCookie());
    }

    public function test_private_updates_never_reach_the_public_site(): void
    {
        $this->get('/projects/ankawa-house')->assertOk()
            ->assertSee('Foundations poured')->assertDontSee('Kitchen layout agreed');

        ProjectUpdate::where('is_private', false)->delete();
        $this->get('/projects/ankawa-house')->assertDontSee('Kitchen layout agreed')->assertDontSee('id="progress"', false);
        $this->get('/')->assertSee('"progress":false', false);
    }

    public function test_the_client_approves_or_sends_back_a_drawing_and_the_studio_is_told(): void
    {
        $room = $this->room();
        $plan = $this->drawing($room, 'Ground floor plan');
        $facade = $this->drawing($room, 'Street facade');
        $this->enter($room);

        $this->post('/room/'.$room->token.'/drawings/'.$plan->id, ['decision' => 'approved'])
            ->assertRedirect('/room/'.$room->token.'#drawing-'.$plan->id);
        $plan->refresh();
        $this->assertSame('approved', $plan->status);
        $this->assertNotNull($plan->decided_at);
        $this->assertSame('127.0.0.1', $plan->decided_ip);
        Mail::assertSent(RoomActivity::class, fn (RoomActivity $m) => $m->hasTo('studio@example.com')
            && $m->headline === 'Shilan approved “Ground floor plan”');

        // a decision stands as given
        $this->post('/room/'.$room->token.'/drawings/'.$plan->id, ['decision' => 'changes', 'note' => 'Actually…']);
        $this->assertSame('approved', $plan->fresh()->status);

        // changes need saying what
        $this->post('/room/'.$room->token.'/drawings/'.$facade->id, ['decision' => 'changes'])->assertSessionHasErrors('note');
        $this->post('/room/'.$room->token.'/drawings/'.$facade->id, ['decision' => 'changes', 'note' => 'Lighter stone, please.']);
        $this->assertSame(['changes', 'Lighter stone, please.'], [$facade->fresh()->status, $facade->fresh()->decision_note]);

        $this->get('/room/'.$room->token)->assertSee('Approved on')->assertSee('Changes asked on')->assertSee('Lighter stone, please.');
        $this->assertSame(2, $room->unseenCount());
    }

    public function test_the_client_comments_under_an_update_of_their_own_project_only(): void
    {
        $room = $this->room();
        $update = ProjectUpdate::where('title_en', 'Kitchen layout agreed')->sole();
        $this->enter($room);

        $this->post('/room/'.$room->token.'/updates/'.$update->id.'/comments', ['body' => 'Can the island be longer?'])
            ->assertRedirect('/room/'.$room->token.'#update-'.$update->id);
        $this->assertSame('Can the island be longer?', RoomComment::sole()->body);
        Mail::assertSent(RoomActivity::class, fn (RoomActivity $m) => $m->headline === 'Shilan commented on “Kitchen layout agreed”');

        $other = Project::create(['name' => 'Other', 'category' => 'residential', 'status' => 'Completed', 'size' => 'default', 'is_published' => true]);
        $foreign = ProjectUpdate::create(['project_id' => $other->id, 'date' => '2026-10-02', 'title_en' => 'Not yours']);
        $this->post('/room/'.$room->token.'/updates/'.$foreign->id.'/comments', ['body' => 'Hi'])->assertNotFound();

        $this->get('/room/'.$room->token)->assertSee('Can the island be longer?');
    }

    public function test_without_the_pin_nothing_can_be_done_or_downloaded(): void
    {
        $room = $this->room();
        $plan = $this->drawing($room);
        $update = ProjectUpdate::first();

        $this->post('/room/'.$room->token.'/drawings/'.$plan->id, ['decision' => 'approved'])->assertForbidden();
        $this->post('/room/'.$room->token.'/updates/'.$update->id.'/comments', ['body' => 'x'])->assertForbidden();
        $this->get('/room/'.$room->token.'/drawings/'.$plan->id)->assertForbidden();

        $this->enter($room);
        $this->get('/room/'.$room->token.'/drawings/'.$plan->id)->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename=plan.pdf');
        $this->get('/room/'.$room->token.'/drawings/'.$plan->id.'?download=1')
            ->assertHeader('Content-Disposition', 'attachment; filename=plan.pdf');

        // closed: the link says so, and nothing more
        $room->update(['is_open' => false]);
        $this->get('/room/'.$room->token)->assertSee('This room is closed')->assertDontSee('Foundations poured');
        $this->post('/room/'.$room->token.'/drawings/'.$plan->id, ['decision' => 'approved'])->assertForbidden();
    }

    public function test_the_room_speaks_kurdish(): void
    {
        $room = $this->room(['lang' => 'ku']);
        $this->assertSame(url('/ku/room/'.$room->token), $room->url());
        $this->get('/ku/room/'.$room->token)->assertOk()
            ->assertSee('<html lang="ku" dir="rtl">', false)->assertSee('ژمارە نهێنییەکەت بنووسە')->assertSee('خانووی عەنکاوە');

        $room->update(['currency' => 'IQD']);
        $this->assertSame('12,500,000 دینار', $room->money(12500000, 'ku'));
        $this->assertSame('12,500,000 IQD', $room->money(12500000, 'en'));
    }

    public function test_the_studio_previews_without_the_pin_but_cannot_act_for_the_client(): void
    {
        $room = $this->room();
        $plan = $this->drawing($room);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin);

        $this->get('/room/'.$room->token)->assertOk()
            ->assertSee('Studio preview')->assertSee('Kitchen layout agreed')
            ->assertDontSee('<textarea name="body"', false)->assertDontSee('name="decision"', false);
        $this->get('/room/'.$room->token.'/drawings/'.$plan->id)->assertOk();
        $this->post('/room/'.$room->token.'/drawings/'.$plan->id, ['decision' => 'approved'])->assertForbidden();
        $this->assertNull($room->fresh()->last_seen_at);
    }

    public function test_the_admin_makes_a_room_sends_it_on_whatsapp_and_answers_the_client(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin);
        $this->get(ClientRoomResource::getUrl())->assertOk()->assertSee('No client rooms yet');
        $this->get(ClientRoomResource::getUrl('create'))->assertOk();

        $room = $this->room(['lang' => 'ku']);
        $plan = $this->drawing($room);
        $update = ProjectUpdate::first();
        $room->comments()->create(['project_update_id' => $update->id, 'body' => 'When is the next visit?']);
        $plan->update(['status' => 'approved', 'decided_at' => now()]);
        $this->assertSame('1', ClientRoomResource::getNavigationBadge());

        $this->get(ClientRoomResource::getUrl('edit', ['record' => $room]))->assertOk()
            ->assertSee($room->pin)->assertSee($room->url())
            ->assertSee('https://wa.me/9647501234567?text='.rawurlencode('سڵاو Shilan، ئەمە ژووری تایبەتی تۆیە بۆ خانووی عەنکاوە'), false)
            ->assertSee(rawurlencode('ژمارە نهێنیی ژوورەکەت: '.$room->pin), false);
        // opening it counts as reading what they did
        $this->assertSame(0, $room->unseenCount());
        $this->assertNull(ClientRoomResource::getNavigationBadge());

        $comment = RoomComment::sole();
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => EditClientRoom::class])
            ->assertSee('When is the next visit?')
            ->callTableAction('reply', $comment, ['body' => 'Next Tuesday at ten.']);
        $this->assertTrue(RoomComment::where('body', 'Next Tuesday at ten.')->sole()->from_studio);

        Livewire::test(DrawingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => EditClientRoom::class])
            ->assertSee('Ground floor plan')->assertSee('Approved');
        $this->get(route('filament.studio-aks-panel.room-drawing', $plan))->assertOk();

        // a new PIN, a new link
        $oldToken = $room->token;
        Livewire::test(EditClientRoom::class, ['record' => $room->getRouteKey()])->callAction('newLink');
        $this->assertNotSame($oldToken, $room->fresh()->token);
        auth()->logout();
        $this->get('/room/'.$oldToken)->assertNotFound();

        // deleting the room takes its files with it, not the project
        $room->fresh()->delete();
        Storage::disk('local')->assertMissing($plan->file_path);
        $this->assertSame(2, $this->project->updates()->count());
    }
}
