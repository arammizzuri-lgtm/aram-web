<?php

namespace Tests\Feature;

use App\Filament\Resources\ClientRooms\ClientRoomResource;
use App\Filament\Resources\ClientRooms\RelationManagers\ChangesRelationManager;
use App\Filament\Resources\ClientRooms\RelationManagers\ChoicesRelationManager;
use App\Filament\Resources\ClientRooms\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\ClientRooms\RelationManagers\DrawingsRelationManager;
use App\Filament\Resources\ClientRooms\RelationManagers\ProgressRelationManager;
use App\Filament\Resources\ClientRooms\Pages\EditClientRoom;
use App\Mail\RoomActivity;
use Filament\Forms\Components\Repeater;
use App\Models\ClientRoom;
use App\Models\Partner;
use App\Models\Project;
use App\Models\ProjectUpdate;
use App\Models\RoomChange;
use App\Models\RoomChoice;
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

    public function test_the_client_pins_comments_on_a_drawing_and_the_studio_answers_at_the_same_spot(): void
    {
        $room = $this->room();
        $plan = $this->drawing($room);
        $url = '/room/'.$room->token.'/drawings/'.$plan->id.'/pins';

        // only the client, in their room (the viewer's fetch carries the room's cookie, as the browser does)
        $this->withCredentials();
        $this->postJson($url, ['page' => 1, 'x' => 0.5, 'y' => 0.5, 'body' => 'Hi'])->assertForbidden();
        $this->enter($room);

        $this->postJson($url, ['page' => 2, 'x' => 0.25, 'y' => 0.75, 'body' => 'Can this window be wider?'])->assertOk()
            ->assertJson(['n' => 1, 'pins' => [['n' => 1, 'page' => 2, 'x' => 0.25, 'y' => 0.75, 'msgs' => [['studio' => false, 'body' => 'Can this window be wider?']]]]]);
        Mail::assertSent(RoomActivity::class, fn (RoomActivity $m) => $m->headline === 'Shilan pinned a comment on “Ground floor plan”'
            && $m->words === 'Can this window be wider?');

        // another spot is another pin; the same spot, the same thread
        $this->postJson($url, ['page' => 1, 'x' => 0.1, 'y' => 0.2, 'body' => 'A door here?'])->assertJson(['n' => 2]);
        $this->postJson($url, ['page' => 2, 'x' => '0.25000', 'y' => '0.75000', 'body' => 'Or a door?'])
            ->assertJson(['n' => 1])->assertJsonCount(2, 'pins.0.msgs');

        // off the page, wordless, or another room's drawing: refused
        $this->postJson($url, ['page' => 1, 'x' => 1.2, 'y' => 0.2, 'body' => 'x'])->assertUnprocessable();
        $this->postJson($url, ['page' => 0, 'x' => 0.2, 'y' => 0.2, 'body' => 'x'])->assertUnprocessable();
        $this->postJson($url, ['page' => 1, 'x' => 0.2, 'y' => 0.2, 'body' => ' '])->assertUnprocessable();
        $theirs = $this->drawing($this->room(['client_name' => 'Someone else']), 'Their plan');
        $this->postJson('/room/'.$room->token.'/drawings/'.$theirs->id.'/pins', ['page' => 1, 'x' => 0.2, 'y' => 0.2, 'body' => 'x'])->assertNotFound();

        // the room lists them under the drawing (room-pins.js draws them from its data)
        $this->get('/room/'.$room->token)->assertOk()
            ->assertSee('Comment on the drawing')->assertSee('Can this window be wider?')->assertSee('room-pins.js');
        $this->assertSame(3, $room->unseenCount());

        // the studio: each listed with its number, answered at the same spot, and seen on the drawing in the preview
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']));
        $first = RoomComment::where('body', 'Can this window be wider?')->sole();
        Livewire::test(CommentsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => EditClientRoom::class])
            ->assertSee('Pin 1 on “Ground floor plan”, page 2')->assertSee('Pin 2 on “Ground floor plan”')
            ->assertSee($room->url().'#pins-'.$plan->id.'-1', false)
            ->callTableAction('reply', $first, ['body' => 'Yes — 20 cm wider.']);
        $answer = RoomComment::where('body', 'Yes — 20 cm wider.')->sole();
        $this->assertTrue($answer->from_studio);
        $this->assertSame([$plan->id, 2, '0.25000', '0.75000'], [$answer->room_drawing_id, $answer->page, $answer->x, $answer->y]);
        $this->assertSame(['Can this window be wider?', 'Or a door?', 'Yes — 20 cm wider.'], $plan->fresh()->pins()[1]->pluck('body')->all());

        Livewire::test(DrawingsRelationManager::class, ['ownerRecord' => $room, 'pageClass' => EditClientRoom::class])
            ->assertSee('See the pins')->assertSee($room->url().'#pins-'.$plan->id, false);

        // deleting the drawing takes its pins with it
        $plan->delete();
        $this->assertSame(0, RoomComment::count());
    }

    public function test_a_change_is_asked_for_priced_and_accepted_as_a_payment_stage_or_declined_with_a_reason(): void
    {
        $room = $this->room();
        $room->payments()->create(['label' => 'Concept design', 'amount' => 3000, 'sort_order' => 1]);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $manager = fn () => Livewire::test(ChangesRelationManager::class, ['ownerRecord' => $room, 'pageClass' => EditClientRoom::class]);
        $decide = fn (RoomChange $c, array $with) => $this->post('/room/'.$room->token.'/changes/'.$c->id, $with + ['v' => $c->fresh()->updated_at->timestamp]);
        $this->enter($room);

        // the client asks; the studio is told, and it waits for a price
        $response = $this->post('/room/'.$room->token.'/changes', ['title' => 'A wider kitchen window', 'details' => 'About 40 cm wider.']);
        $asked = RoomChange::sole();
        $response->assertRedirect('/room/'.$room->token.'#change-'.$asked->id)->assertSessionHas('room_flash', 'room_change_sent');
        $this->assertSame(RoomChange::ASKED, $asked->status);
        $this->assertTrue($asked->asked_by_client);
        Mail::assertSent(RoomActivity::class, fn (RoomActivity $m) => str_starts_with($m->headline, 'Shilan asked for a change: “A wider kitchen window”')
            && $m->words === 'About 40 cm wider.');
        $this->assertSame(1, $room->unseenCount());
        $decide($asked, ['decision' => 'accepted']);
        $this->assertSame(RoomChange::ASKED, $asked->fresh()->status);           // nothing to accept before a price
        $this->get('/room/'.$room->token)->assertSee('Waiting for the studio’s price')->assertDontSee('name="v"', false);

        // the studio gives it a price
        $this->actingAs($admin);
        $manager()->assertSee('Asked — needs a price')
            ->callTableAction('edit', $asked, ['price' => 500, 'extra_days' => 7, 'note' => 'The column moves too.'])
            ->assertHasNoTableActionErrors();
        auth()->logout();
        $asked->refresh();
        $this->assertSame(RoomChange::PRICED, $asked->status);
        $this->assertSame('500.00', $asked->price);
        $this->assertSame('About 40 cm wider.', $asked->details);              // their words stay theirs
        $this->assertSame(0, $room->unseenCount());

        // the client accepts the price they were shown, once, and it is a payment stage of its own
        $this->get('/room/'.$room->token)->assertSee('$500')->assertSee('+7</bdi> days', false)->assertSee('The column moves too.');
        $this->post('/room/'.$room->token.'/changes/'.$asked->id, ['decision' => 'accepted', 'v' => $asked->updated_at->timestamp - 60])
            ->assertSessionHas('room_flash', 'room_change_moved');
        $this->assertSame(RoomChange::PRICED, $asked->fresh()->status);
        $decide($asked, ['decision' => 'accepted'])->assertRedirect('/room/'.$room->token.'#change-'.$asked->id)->assertSessionHas('room_flash', 'room_decided');
        $this->post('/room/'.$room->token.'/changes/'.$asked->id, ['decision' => 'accepted', 'v' => $asked->updated_at->timestamp]);
        $asked->refresh();
        $this->assertSame(RoomChange::ACCEPTED, $asked->status);
        $this->assertSame(2, $room->payments()->count());
        $stage = $room->payments()->get()->last();
        $this->assertSame(['Change: A wider kitchen window', '500.00', 2, $stage->id], [$stage->label, $stage->amount, $stage->sort_order, $asked->room_payment_id]);
        $this->assertCount(1, Mail::sent(RoomActivity::class, fn (RoomActivity $m) => $m->headline === 'Shilan accepted the change “A wider kitchen window” — $500 (+7 days)'));
        $this->get('/room/'.$room->token)->assertSee('Change: A wider kitchen window')->assertSee('Added to your payments')
            ->assertSee('$3,500');

        // the studio offers one, with a picture; the client declines it, saying why
        $this->actingAs($admin);
        $manager()->callTableAction('create', null, ['title' => 'Marble floor in the hall', 'price' => 2400, 'extra_days' => 10])
            ->assertHasNoTableActionErrors();
        auth()->logout();
        $offer = RoomChange::where('title', 'Marble floor in the hall')->sole();
        Storage::disk('local')->put('rooms/changes/marble.jpg', 'jpeg');
        $offer->update(['file_path' => 'rooms/changes/marble.jpg', 'file_name' => 'marble.jpg']);
        $this->assertFalse($offer->asked_by_client);
        $this->get('/room/'.$room->token.'/changes/'.$offer->id)->assertOk();
        $decide($offer, ['decision' => 'declined'])->assertSessionHasErrors('note');
        $decide($offer, ['decision' => 'declined', 'note' => 'Too dear for now.']);
        $offer->refresh();
        $this->assertSame([RoomChange::DECLINED, 'Too dear for now.', null], [$offer->status, $offer->decline_note, $offer->room_payment_id]);
        $this->assertSame(2, $room->payments()->count());
        Mail::assertSent(RoomActivity::class, fn (RoomActivity $m) => $m->headline === 'Shilan declined the change “Marble floor in the hall”'
            && $m->words === 'Too dear for now.' && $m->tone === 'warn');
        $this->assertSame(2, $room->unseenCount());
        $this->assertSame('1', ClientRoomResource::getNavigationBadge());

        // the studio's side: the file, and the room's form kept from losing a stage added while it was open
        $this->actingAs($admin);
        $this->get(route('filament.studio-aks-panel.room-change', $offer))->assertOk();
        $page = Livewire::test(EditClientRoom::class, ['record' => $room->getRouteKey()]);
        $this->assertSame(0, $room->unseenCount());
        $room->payments()->create(['label' => 'Change: something else', 'amount' => 100, 'sort_order' => 3]);
        $page->call('save')->assertNotified('The payment stages changed meanwhile');
        $this->assertSame(3, $room->payments()->count());

        // deleting the room takes the change pictures with it
        $room->fresh()->delete();
        Storage::disk('local')->assertMissing('rooms/changes/marble.jpg');
    }

    public function test_the_client_picks_a_finish_once_and_its_extra_cost_becomes_a_payment_stage(): void
    {
        $room = $this->room();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $partner = Partner::create(['name' => 'Erbil Stone Co.', 'category' => 'stone', 'phone' => '0750 222 3344', 'is_published' => true]);

        // the studio sets it out in the admin
        $this->actingAs($admin);
        $undoFake = Repeater::fake();
        Livewire::test(ChoicesRelationManager::class, ['ownerRecord' => $room, 'pageClass' => EditClientRoom::class])
            ->callTableAction('create', null, ['title' => 'Kitchen worktop', 'note' => 'Both come 3 cm thick.', 'options' => [
                ['name' => 'Porcelain slab', 'extra_cost' => 0],
                ['name' => 'Calacatta marble', 'extra_cost' => 800, 'partner_id' => $partner->id],
            ]])->assertHasNoTableActionErrors();
        $undoFake();
        auth()->logout();
        $choice = RoomChoice::where('title', 'Kitchen worktop')->sole();
        [$porcelain, $marble] = $choice->options->all();
        $this->assertSame(['Porcelain slab', 'Calacatta marble', $partner->id], [$porcelain->name, $marble->name, $marble->partner_id]);
        $this->assertNotNull($choice->seen_at);
        Storage::disk('local')->put('rooms/choices/marble.jpg', 'jpeg');
        $marble->update(['image_path' => 'rooms/choices/marble.jpg']);
        $other = $this->room(['client_name' => 'Someone else']);
        $theirs = $other->choices()->create(['title' => 'Tiles'])->options()->create(['name' => 'Grey', 'image_path' => 'rooms/choices/marble.jpg']);
        $v = $choice->load('options')->version();
        $pick = fn (array $with) => $this->post('/room/'.$room->token.'/choices/'.$choice->id, $with + ['v' => $v]);
        $this->enter($room);

        $this->get('/room/'.$room->token)->assertOk()
            ->assertSee('Kitchen worktop')->assertSee('Both come 3 cm thick.')->assertSee('+$800')->assertSee('Included')
            ->assertSee('Erbil Stone Co.')->assertSee('tel:07502223344', false)->assertSee('name="option"', false);
        $this->get('/room/'.$room->token.'/choices/options/'.$marble->id)->assertOk();
        $this->get('/room/'.$room->token.'/choices/options/'.$theirs->id)->assertNotFound();   // another room's

        // only the options and prices they were shown, and only this choice's
        $pick(['option' => $marble->id, 'v' => 'stale'])->assertSessionHas('room_flash', 'room_choice_moved');
        $pick(['option' => $theirs->id])->assertStatus(422);
        $this->assertTrue($choice->fresh()->isPending());

        // the pick: final, dated, with their note, and the extra cost a payment stage of its own
        $pick(['option' => $marble->id, 'note' => 'Veins running lengthwise, please.'])
            ->assertRedirect('/room/'.$room->token.'#choice-'.$choice->id)->assertSessionHas('room_flash', 'room_decided');
        $pick(['option' => $porcelain->id]);                                    // a second pick changes nothing
        $choice->refresh();
        $this->assertSame([RoomChoice::CHOSEN, $marble->id, 'Veins running lengthwise, please.'], [$choice->status, $choice->chosen_option_id, $choice->client_note]);
        $stage = $room->payments()->sole();
        $this->assertSame(['Kitchen worktop: Calacatta marble', '800.00', $stage->id], [$stage->label, $stage->amount, $choice->room_payment_id]);
        $this->assertCount(1, Mail::sent(RoomActivity::class, fn (RoomActivity $m) => $m->headline === 'Shilan chose “Calacatta marble” for “Kitchen worktop” (+$800, now a payment stage)'
            && $m->words === 'Veins running lengthwise, please.'));
        $this->assertSame(1, $room->unseenCount());
        $this->get('/room/'.$room->token)->assertSee('Your choices')->assertSee('Chosen on')->assertSee('Added to your payments')
            ->assertDontSee('name="option"', false);

        // the studio reopens it: the unpaid stage goes; they pick again, an included one: no stage
        $this->actingAs($admin);
        Livewire::test(ChoicesRelationManager::class, ['ownerRecord' => $room, 'pageClass' => EditClientRoom::class])
            ->assertSee('Calacatta marble')->assertSee('Veins running lengthwise')
            ->callTableAction('reopen', $choice);
        auth()->logout();
        $this->assertTrue($choice->fresh()->isPending());
        $this->assertSame(0, $room->payments()->count());
        $pick(['option' => $porcelain->id]);
        $this->assertSame([$porcelain->id, null], [$choice->fresh()->chosen_option_id, $choice->fresh()->room_payment_id]);
        $this->assertSame(0, $room->payments()->count());

        // a paid stage is kept when it is reopened
        $choice->fresh()->reopen();
        $pick(['option' => $marble->id]);
        $room->payments()->sole()->update(['paid_on' => today()]);
        $this->assertTrue($choice->fresh()->reopen());
        $this->assertSame(1, $room->payments()->count());

        // deleting the choice takes its pictures with it
        $choice->fresh()->delete();
        Storage::disk('local')->assertMissing('rooms/choices/marble.jpg');
        $this->assertSame(0, RoomChoice::query()->whereKey($choice->id)->count());
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
        $room->changes()->create(['title' => 'A wider kitchen window', 'price' => 500]);
        $room->choices()->create(['title' => 'Kitchen worktop'])->options()->create(['name' => 'Calacatta marble']);
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin);

        $this->get('/room/'.$room->token)->assertOk()
            ->assertSee('Studio preview')->assertSee('Kitchen layout agreed')->assertSee('A wider kitchen window')
            ->assertDontSee('<textarea name="body"', false)->assertDontSee('name="decision"', false)
            ->assertDontSee('name="details"', false)
            ->assertSee('Calacatta marble')->assertDontSee('name="option"', false);
        $this->get('/room/'.$room->token.'/drawings/'.$plan->id)->assertOk();
        $this->post('/room/'.$room->token.'/drawings/'.$plan->id, ['decision' => 'approved'])->assertForbidden();
        $this->assertNull($room->fresh()->last_seen_at);
    }

    public function test_daily_progress_is_added_in_the_room_and_stays_private(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']));
        $room = $this->room();

        // the room lists its project's updates, and a new one is private unless switched off
        Livewire::test(ProgressRelationManager::class, ['ownerRecord' => $room, 'pageClass' => EditClientRoom::class])
            ->assertSee(['Foundations poured', 'Kitchen layout agreed'])
            ->mountTableAction('create')
            ->assertTableActionDataSet(['is_private' => true])
            ->setTableActionData(['date' => '2026-11-10', 'title_en' => 'Tiles in the kitchen', 'note_en' => 'The floor is down.'])
            ->callMountedTableAction()
            ->assertHasNoTableActionErrors();

        $update = ProjectUpdate::where('title_en', 'Tiles in the kitchen')->sole();
        $this->assertSame($this->project->id, $update->project_id);
        $this->assertTrue($update->is_private);

        // the client sees it and can comment under it; the public page never shows it
        auth()->logout();
        $this->enter($room);
        $this->get('/room/'.$room->token)->assertSee('Tiles in the kitchen')->assertSee('/updates/'.$update->id.'/comments', false);
        $this->get('/projects/ankawa-house')->assertDontSee('Tiles in the kitchen');
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
