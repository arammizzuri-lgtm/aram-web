<?php

namespace Tests\Feature;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Widgets\TopProjects;
use App\Mail\NewEnquiry;
use App\Models\ContactMessage;
use App\Models\Project;
use App\Models\SiteEvent;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\StudioMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The heart on every project, the saved list (/saved) it builds and shares,
 * the list going with an enquiry, and the share buttons — all counted.
 */
class SavedAndSharingTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/131.0 Safari/537.36';

    private function project(string $name, array $attrs = []): Project
    {
        return Project::create($attrs + [
            'name' => $name, 'name_ku' => $name.' (ku)', 'category' => 'residential', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/'.md5($name).'.jpg'], 'is_published' => true,
        ]);
    }

    public function test_every_project_can_be_saved_and_shared_from_its_page_the_grid_and_the_overlay(): void
    {
        $villa = $this->project('Mountain Villa');

        $page = $this->get('/projects/mountain-villa')->assertOk();
        $page->assertSee('data-save="mountain-villa" data-save-at="project"', false);
        // WhatsApp share: no number in it, so WhatsApp asks whom to send it to
        $page->assertSee('href="https://wa.me/?text='.rawurlencode('Have a look at “Mountain Villa” by Aram Mizuri Architecture: '.url('/projects/mountain-villa')).'"', false);
        $page->assertSee('data-share="link" data-share-at="project"', false);
        $page->assertSee('data-copy-en="Copy link"', false);
        $page->assertSee('data-saved-link hidden', false);           // "Saved (n)" waits for a first heart
        $page->assertSee('saved.js', false);

        $this->get('/ku/projects/mountain-villa')->assertOk()
            ->assertSee(rawurlencode('سەیری «Mountain Villa (ku)» بکە'), false);

        $home = $this->get('/')->assertOk();
        $home->assertSee('data-save="mountain-villa" data-save-at="grid"', false)
            ->assertSee('id="overlaySave"', false)->assertSee('id="overlayShareWa"', false)
            ->assertSee('id="formSaved"', false)
            ->assertSee('"share":{"en":"Have a look at “{project}” by Aram Mizuri Architecture: {url}"', false);
        $this->assertSame(1, substr_count($home->getContent(), 'data-save-at="grid"'));

        $this->get('/careers')->assertOk()->assertSee('data-saved-link', false);
    }

    public function test_a_saved_list_shows_its_projects_in_order_and_previews_its_first(): void
    {
        $this->project('Erbil Tower');
        $this->project('Mountain Villa');
        $this->project('Hidden House', ['is_published' => false]);

        $list = $this->get('/saved?p=mountain-villa.erbil-tower.hidden-house.nonsense..ERBIL-TOWER')->assertOk();
        $list->assertSeeInOrder(['data-slug="mountain-villa"', 'data-slug="erbil-tower"'], false)
            ->assertDontSee('Hidden House')
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<meta property="og:title" content="Mountain Villa · Erbil Tower — Aram Mizuri Architecture">', false)
            ->assertSee('<meta property="og:url" content="'.url('/saved?p=mountain-villa.erbil-tower').'">', false)
            ->assertSee('Ask the studio about these')
            ->assertSee('href="'.url('/').'?saved=mountain-villa.erbil-tower#contact"', false)
            ->assertSee('https://wa.me/?text='.rawurlencode('A few projects by Aram Mizuri Architecture I like: '.url('/saved?p=mountain-villa.erbil-tower')), false)
            // what the address asked for — the visitor's own list may hold one
            // since unpublished, and is still theirs
            ->assertSee('var asked = ["mountain-villa","erbil-tower","hidden-house","nonsense"]', false);

        // commas work too, and Kurdish has its own address
        $this->get('/ku/saved?p=erbil-tower,mountain-villa')->assertOk()
            ->assertSee('<html lang="ku" dir="rtl">', false)
            ->assertSeeInOrder(['data-slug="erbil-tower"', 'data-slug="mountain-villa"'], false)
            ->assertSee('ئەو پرۆژانەی بۆت نێردراون');

        // nothing asked for: the empty state, and the visitor's own list if they have one
        $this->get('/saved')->assertOk()->assertSee('Nothing saved yet')->assertSee('if (!false && mine.length)', false);

        // one visitor's list is not a page for search engines
        $this->get('/sitemap.xml')->assertDontSee('/saved');
    }

    public function test_the_saved_list_goes_with_the_enquiry_and_reaches_the_studio(): void
    {
        Mail::fake();
        SiteSetting::put(StudioMail::TO, 'studio@example.com', 'alerts');
        $tower = $this->project('Erbil Tower');
        $villa = $this->project('Mountain Villa');
        $this->project('Hidden House', ['is_published' => false]);

        $this->postJson('/contact', [
            'name' => 'Shilan', 'phone' => '0750 123 4567',
            'saved' => ['mountain-villa', 'hidden-house', 'nope', 'erbil-tower'],
        ])->assertOk();

        $enquiry = ContactMessage::sole();
        $this->assertSame([$villa->id, $tower->id], $enquiry->saved_projects);
        $this->assertSame(['Mountain Villa', 'Erbil Tower'], $enquiry->savedProjects()->pluck('name')->all());
        Mail::assertSent(NewEnquiry::class, fn (NewEnquiry $m) => str_contains($m->render(), 'Mountain Villa · Erbil Tower'));

        // nothing saved, nothing stored
        $this->postJson('/contact', ['name' => 'Rozh', 'phone' => '0750 765 4321', 'saved' => []])->assertOk();
        $this->assertNull(ContactMessage::where('name', 'Rozh')->sole()->saved_projects);
        $this->postJson('/contact', ['name' => 'Bot', 'phone' => '0750 765 4321', 'saved' => 'not-a-list'])->assertStatus(422);

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin)->get(ContactMessageResource::getUrl('edit', ['record' => $enquiry]))->assertOk()
            ->assertSee('Had saved')->assertSee('Mountain Villa');
    }

    public function test_hearts_and_shares_are_counted_per_project_but_a_whatsapp_share_is_not_a_chat(): void
    {
        $villa = $this->project('Mountain Villa');
        $send = fn (array $data) => $this->withHeaders(['User-Agent' => self::BROWSER])->post('/t', $data)->assertNoContent();

        $send(['type' => 'save', 'project' => $villa->slug, 'placement' => 'grid']);
        $send(['type' => 'save', 'project' => $villa->slug, 'placement' => 'overlay']);
        $send(['type' => 'share', 'project' => $villa->slug, 'placement' => 'project']);
        $send(['type' => 'share', 'placement' => 'saved']);
        $send(['type' => 'share', 'placement' => 'nowhere']);     // not a place a button sits

        $this->assertSame(2, SiteEvent::where('type', 'save')->count());
        $this->assertSame(['project', 'saved'], SiteEvent::where('type', 'share')->orderBy('id')->pluck('placement')->all());
        $this->assertSame(0, SiteEvent::where('type', 'whatsapp_click')->count());

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin);
        Livewire::test(TopProjects::class, ['pageFilters' => ['range' => '30']])
            ->assertSee('Mountain Villa')->assertSee('Saved')->assertSee('Shared');
    }
}
