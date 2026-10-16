<?php

namespace Tests\Feature;

use App\Filament\Pages\PhotoRooms;
use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A project's photographs explained: notes on them as a drawing's
 * callouts, the room each shows (and every room from every project on
 * /photos), and the architect's own voice note on the project.
 */
class PhotoStoriesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Category::updateOrCreate(['key' => 'residential'], ['name' => 'Residential', 'name_ku' => 'نیشتەجێبوون', 'sort_order' => 1]);
    }

    private function project(array $attrs = []): Project
    {
        return Project::create($attrs + [
            'name' => 'Stone House', 'category' => 'residential', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/a.jpg', 'https://example.com/b.jpg', 'https://example.com/c.jpg'],
            'cover' => 'https://example.com/b.jpg', 'is_published' => true,
        ]);
    }

    /* ---- Notes on the photographs ---------------------------------------- */

    public function test_notes_are_kept_only_for_photographs_still_on_the_project(): void
    {
        $p = $this->project(['callouts' => [
            ['img' => 'https://example.com/a.jpg', 'x' => 30, 'y' => 40, 'en' => 'Limestone', 'ku' => 'بەرد'],
            ['img' => 'https://example.com/a.jpg', 'x' => 150, 'y' => -5, 'en' => ' Edge ', 'ku' => ''],
            ['img' => 'https://example.com/gone.jpg', 'x' => 10, 'y' => 10, 'en' => 'Orphan', 'ku' => ''],
            ['img' => 'https://example.com/c.jpg', 'x' => 10, 'y' => 10, 'en' => '', 'ku' => ''],
        ]]);

        $this->assertEquals([
            ['img' => 'https://example.com/a.jpg', 'x' => 30, 'y' => 40, 'en' => 'Limestone', 'ku' => 'بەرد'],
            ['img' => 'https://example.com/a.jpg', 'x' => 100, 'y' => 0, 'en' => 'Edge', 'ku' => ''],
        ], $p->fresh()->callouts);

        // a photograph taken off takes its notes with it
        $p->update(['imgs' => ['https://example.com/b.jpg', 'https://example.com/c.jpg']]);
        $this->assertNull($p->fresh()->callouts);
    }

    public function test_notes_follow_the_order_the_page_shows_the_photographs_in(): void
    {
        // the cover (b) comes first on the page, then a, then c
        $p = $this->project(['callouts' => [
            ['img' => 'https://example.com/a.jpg', 'x' => 30, 'y' => 40, 'en' => 'Limestone', 'ku' => 'بەردی ئامێدی'],
            ['img' => 'https://example.com/b.jpg', 'x' => 50, 'y' => 60, 'en' => 'The courtyard', 'ku' => ''],
        ]]);

        $this->assertSame([0 => [['x' => 50.0, 'y' => 60.0, 't' => 'The courtyard']], 1 => [['x' => 30.0, 'y' => 40.0, 't' => 'Limestone']]], $p->notesByImage('en'));
        // in Kurdish, the Kurdish words — the English where none were written
        $this->assertSame('بەردی ئامێدی', $p->notesByImage('ku')[1][0]['t']);
        $this->assertSame('The courtyard', $p->notesByImage('ku')[0][0]['t']);

        $html = $this->get('/projects/stone-house')->assertOk()->assertSee('photo-notes.js')->getContent();
        $attr = fn (string $pattern) => preg_match($pattern, $html, $m) ? json_decode(html_entity_decode($m[1]), true) : null;
        // in the gallery (photograph 1, "a"), and in the viewer for every photograph in order
        $this->assertEquals([['x' => 30, 'y' => 40, 't' => 'Limestone']], $attr("#<figure class=\"pp-shot[^\"]*\"\s+data-notes='([^']*)'#"));
        $this->assertEquals([[['x' => 50, 'y' => 60, 't' => 'The courtyard']], [['x' => 30, 'y' => 40, 't' => 'Limestone']], []],
            $attr("#<dialog class=\"pp-lightbox\"[^>]*data-notes='([^']*)'#"));
        $this->assertStringContainsString('<button class="pp-lightbox__notes" type="button" aria-pressed="true" hidden>Notes</button>', $html);

        // a project without notes loads nothing for them
        $this->project(['name' => 'Plain House']);
        $this->get('/projects/plain-house')->assertOk()->assertDontSee('photo-notes.js')->assertDontSee('pp-lightbox__notes', false);
    }

    /* ---- The architect's voice -------------------------------------------- */

    public function test_the_architects_voice_note_plays_on_the_project_page(): void
    {
        Storage::fake('public');
        $p = $this->project(['voice_ku' => 'projects/voices/stone.opus']);

        $this->get('/ku/projects/stone-house')->assertOk()
            ->assertSee('گوێ لە تەڵارسازەکە بگرە')
            ->assertSee('data-src="'.asset('storage/projects/voices/stone.opus').'" data-type="audio/ogg; codecs=opus"', false)
            ->assertSee('voices.js')
            ->assertDontSee('pp-voice__lang', false);

        // on the English page the Kurdish one plays — and says so
        $this->get('/projects/stone-house')->assertOk()
            ->assertSee('Hear the architect')
            ->assertSee('<span class="pp-voice__lang">· in Kurdish</span>', false);

        // one in English is preferred there
        $p->update(['voice_en' => 'projects/voices/stone-en.m4a']);
        $this->get('/projects/stone-house')->assertSee('data-type="audio/mp4"', false)->assertDontSee('pp-voice__lang', false);

        // replaced or taken off, the old file goes
        Storage::disk('public')->put('projects/voices/stone-en.m4a', 'x');
        $p->update(['voice_en' => null]);
        Storage::disk('public')->assertMissing('projects/voices/stone-en.m4a');

        $this->project(['name' => 'Quiet House']);
        $this->get('/projects/quiet-house')->assertOk()->assertDontSee('pp-voice', false)->assertDontSee('voices.js');
    }

    /* ---- Room by room ------------------------------------------------------ */

    public function test_every_room_from_every_project_is_shown_room_by_room(): void
    {
        $a = $this->project(['photo_rooms' => ['https://example.com/a.jpg' => 'kitchen', 'https://example.com/b.jpg' => 'facade', 'https://example.com/c.jpg' => 'spaceship']]);
        $this->project(['name' => 'Garden Villa', 'photo_rooms' => ['https://example.com/a.jpg' => 'kitchen']]);
        $this->project(['name' => 'Draft', 'is_published' => false, 'photo_rooms' => ['https://example.com/a.jpg' => 'kitchen']]);

        // a room that is not one is not kept
        $this->assertSame(['https://example.com/a.jpg' => 'kitchen', 'https://example.com/b.jpg' => 'facade'], $a->fresh()->photo_rooms);

        $this->get('/photos')->assertOk()
            ->assertSee('Every room we have made')
            ->assertSee('3 photographs')
            ->assertSeeInOrder(['Façade &amp; exterior', 'Kitchen'], false)        // the rooms in their order
            ->assertSee(url('/photos').'?room=kitchen', false)
            ->assertDontSee('Draft');

        $this->get('/photos?room=kitchen')->assertOk()
            ->assertSee('2 photographs')
            ->assertSee('class="ph-room is-on" href="'.url('/photos').'?room=kitchen"', false)
            ->assertSee('Garden Villa');
        $this->get('/ku/photos?room=kitchen')->assertOk()->assertSee('چێشتخانە')->assertSee('2 وێنە');
        $this->get('/photos?room=nowhere')->assertOk()->assertSee('3 photographs');

        // the way there: from the projects page, from a photograph's room, from the sitemap
        $this->get('/projects')->assertSee('Browse room by room')->assertSee(url('/photos'), false);
        $html = $this->get('/projects/stone-house')->getContent();
        preg_match("#data-rooms='([^']*)'#", $html, $m);
        // in the page's order: the cover (b, the only façade — no way on), then a (one of two kitchens), then c
        $this->assertSame([null, ['label' => 'Kitchen — see all 2', 'url' => url('/photos').'?room=kitchen'], null],
            json_decode(html_entity_decode($m[1]), true));
        $this->get('/sitemap.xml')->assertSee(url('/photos'), false);
    }

    public function test_before_any_room_is_said_there_is_nothing_to_browse(): void
    {
        $this->project();
        $this->get('/photos')->assertOk()->assertSee('The photographs are being sorted room by room');
        $this->get('/projects')->assertDontSee('Browse room by room');
        $this->get('/sitemap.xml')->assertDontSee(url('/photos'), false);
    }

    public function test_the_studio_says_each_photographs_room_with_one_tap(): void
    {
        $p = $this->project();
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);

        $this->actingAs($admin)->get(PhotoRooms::getUrl())->assertOk()
            ->assertSee('Photos by room')
            ->assertSee('0</b> of <b>3</b>', false);

        $page = new PhotoRooms;
        $page->setRoom($p->id, 'https://example.com/a.jpg', 'kitchen');
        $this->assertSame('kitchen', $p->fresh()->roomOf('https://example.com/a.jpg'));
        $page->setRoom($p->id, 'https://example.com/a.jpg', 'kitchen');            // again: taken off
        $this->assertNull($p->fresh()->roomOf('https://example.com/a.jpg'));
        $page->setRoom($p->id, 'https://example.com/not-its-own.jpg', 'kitchen');   // not this project's
        $page->setRoom($p->id, 'https://example.com/a.jpg', 'spaceship');          // not a room
        $this->assertNull($p->fresh()->photo_rooms);

        // and the notes and the voice note are on the project's own form
        $this->actingAs($admin)->get(ProjectResource::getUrl('edit', ['record' => $p]))->assertOk()
            ->assertSee('Notes on the photographs')
            ->assertSee('The architect’s voice');
    }
}
