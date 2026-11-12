<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSiteContent;
use App\Filament\Resources\Landmarks\Pages\CreateLandmark;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Widgets\NeedsAttention;
use App\Models\ContactMessage;
use App\Models\Landmark;
use App\Models\MissingPage;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\SiteText;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * How the admin is laid out: the sidebar in groups, and the dashboard's list
 * of what is waiting.
 */
class AdminOrganisationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
    }

    public function test_the_sidebar_is_in_groups_in_the_order_the_work_runs(): void
    {
        $panel = Filament::getPanel('studio-aks-panel');
        $groups = collect([...$panel->getResources(), ...$panel->getPages()])
            ->map(fn (string $class) => $class::getNavigationGroup())->filter()->unique();
        $this->assertEqualsCanonicalizing(['Portfolio', 'Clients', 'Careers', 'Website settings', 'System'], $groups->values()->all());

        $this->actingAs($this->admin())->get('/studio-aks-panel')->assertOk()->assertSeeInOrder([
            'Portfolio', 'Projects', 'Project grid', 'Photos by room', 'Heritage landmarks',
            'Clients', 'Enquiries', 'Client rooms', 'Review links', 'Client quotes', 'Homepage clients', 'Holiday cards', 'Partners',
            'Careers', 'Positions', 'Applications',
            'Website settings', 'Site content', 'Cost estimator', 'Read aloud', 'Enquiry alerts',
            'System', 'Backups', 'Broken links',
        ]);
    }

    public function test_needs_attention_lists_only_what_is_waiting(): void
    {
        $this->actingAs($this->admin());
        $items = fn () => Livewire::test(NeedsAttention::class)->viewData('items')->pluck('count', 'label')->all();

        $this->assertSame([], $items());
        Livewire::test(NeedsAttention::class)->assertSee('All caught up');

        ContactMessage::create(['name' => 'A', 'email' => 'a@x.test', 'message' => 'Hello']);
        ContactMessage::create(['name' => 'B', 'email' => 'b@x.test', 'message' => 'Read', 'is_read' => true]);
        Project::create([
            'name' => 'Erbil Tower', 'category' => 'commercial', 'status' => 'Completed', 'size' => 'default', 'is_published' => true,
            'imgs' => ['https://example.com/a.jpg', 'https://example.com/b.jpg'], 'photo_rooms' => ['https://example.com/a.jpg' => 'kitchen'],
        ]);
        MissingPage::create(['path' => '/projects/old-name', 'hits' => 2, 'referrer' => url('/projects')]);
        MissingPage::create(['path' => '/wp-login.php', 'hits' => 9, 'referrer' => null]);   // not a link the studio can fix

        $this->assertSame([
            'New enquiries' => 1,
            'Photos without a room' => 1,
            'Broken links on your own pages' => 1,
        ], $items());
    }

    public function test_the_project_and_landmark_forms_save_through_their_tabs(): void
    {
        $this->actingAs($this->admin());

        // a new project: the cover and the notes wait until there are saved photographs
        Livewire::test(CreateProject::class)
            ->assertSee(['Details', 'Story', 'Photographs', 'More on the page'])
            ->assertDontSee('Card cover & focal point')
            ->assertDontSee('Notes on the photographs')
            ->fillForm(['name' => 'Twin House', 'categories' => ['residential'], 'status' => 'Completed', 'size' => 'default', 'desc' => 'Two houses, one garden.',
                'typology' => 'Villa', 'typology_ku' => 'ڤێلا', 'location_ku' => 'هەولێر'])
            ->call('create')
            ->assertHasNoFormErrors();
        $project = Project::query()->where('name', 'Twin House')->firstOrFail();
        $this->assertSame('Two houses, one garden.', $project->desc);
        // the typology and the place in Kurdish too; Arabic left blank shows the English
        $this->assertSame('ڤێلا', $project->tr('typology', 'ku'));
        $this->assertSame('هەولێر', $project->tr('location', 'ku'));
        $this->assertSame('Villa', $project->tr('typology', 'ar'));
        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])->assertSee('Card cover & focal point');

        Livewire::test(CreateLandmark::class)
            ->fillForm(['name_en' => 'Amedi Gate', 'region' => 'bashur', 'era' => 'mesopotamia', 'hero_style' => 'split', 'condition' => null, 'is_published' => true])
            ->call('create')
            ->assertHasNoFormErrors();
        $this->assertSame('split', Landmark::query()->where('name_en', 'Amedi Gate')->value('hero_style'));
    }

    public function test_site_content_is_in_parts_of_the_site_and_can_be_searched(): void
    {
        $this->actingAs($this->admin());

        // every section belongs to a part of the site — none left under "Other"
        $groups = ManageSiteContent::groups();
        $this->assertArrayNotHasKey('Other', $groups);
        $this->assertSame(count(ManageSiteContent::sections()), count(array_merge(...array_values($groups))));

        // a search finds a text by its words, in any section, and links to its field
        $key = array_key_first(SiteText::tabs()['Footer']);
        $label = SiteText::tabs()['Footer'][$key][0];
        Livewire::test(ManageSiteContent::class)
            ->assertSee(['Everywhere', 'Home page', 'Missing translations'])
            ->set('find', mb_substr(SiteText::ku($key), 0, 12))
            ->assertSee($label)
            ->assertSee('#t-'.$key, false)
            ->assertDontSee('Save changes')
            ->set('find', 'zzqx-nothing-like-this')
            ->assertSee('No text has these words.')
            ->set('find', '')
            ->set('missing', true)
            ->assertSee('Every text has its Kurdish and Arabic.');
    }

    public function test_the_google_profile_has_its_own_section_and_saves(): void
    {
        $this->actingAs($this->admin());

        Livewire::withQueryParams(['section' => 'google'])->test(ManageSiteContent::class)
            ->assertSee('Google Business Profile')
            ->fillForm(['google_profile_url' => 'https://maps.app.goo.gl/abc', 'google_rating' => '4.9', 'google_review_count' => '23'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('4.9', (string) SiteSetting::get('google_rating'));
        $this->assertSame('https://maps.app.goo.gl/abc', (string) SiteSetting::get('google_profile_url'));
    }

    public function test_texts_english_only_on_purpose_are_not_counted_as_missing(): void
    {
        // read only by search engines, so no Kurdish and an Arabic left empty by design
        $this->assertSame('', SiteText::map()['arch_firms']['ku']);
        $this->assertSame([], SiteText::untranslated());
    }
}
