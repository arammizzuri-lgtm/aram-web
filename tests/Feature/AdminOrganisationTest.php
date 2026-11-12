<?php

namespace Tests\Feature;

use App\Filament\Pages\CostEstimator;
use App\Filament\Pages\EnquiryAlerts;
use App\Filament\Pages\ManageSiteContent;
use App\Filament\Pages\ReadAloudSettings;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Filament\Resources\Clients\Pages\EditClient;
use App\Filament\Resources\Landmarks\Pages\CreateLandmark;
use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Filament\Widgets\NeedsAttention;
use App\Models\Category;
use App\Models\Client;
use App\Models\ContactMessage;
use App\Models\Landmark;
use App\Models\MissingPage;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\SiteText;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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
            'Live projects: missing Kurdish or Arabic' => 1,
            'Live projects: not on the map' => 1,
            'Photos without a room' => 1,
            'Broken links on your own pages' => 1,
        ], $items());
    }

    public function test_content_health_finds_what_live_projects_lack_and_opens_them(): void
    {
        $this->actingAs($this->admin());
        $make = fn (string $name, array $a = []) => Project::create($a + [
            'name' => $name, 'category' => 'residential', 'status' => 'Completed', 'size' => 'default', 'is_published' => true,
            'imgs' => ['https://example.com/'.$name.'.jpg'], 'name_ku' => 'ناو', 'name_ar' => 'اسم', 'lat' => 36.19, 'lng' => 44.01,
        ]);
        $done = $make('Done');                                                        // no English description, so none to translate
        $noArabicText = $make('Half', ['desc' => 'A house.', 'desc_ku' => 'خانوو']);
        $noPhotos = $make('Bare', ['imgs' => []]);
        $pinOnly = $make('Pin', ['map_only' => true, 'imgs' => [], 'name_ku' => null, 'lat' => null]);   // a pin, never a page
        $hidden = $make('Draft', ['name_ku' => null, 'imgs' => [], 'lat' => null, 'is_published' => false]);

        $this->assertEqualsCanonicalizing([$noArabicText->id], Project::query()->needing('translation')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$noPhotos->id], Project::query()->needing('photos')->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$pinOnly->id], Project::query()->needing('pin')->pluck('id')->all());

        // the dashboard line opens the list showing just those
        $item = Livewire::test(NeedsAttention::class)->viewData('items')->firstWhere('label', 'Live projects: missing Kurdish or Arabic');
        $this->assertSame(1, $item['count']);
        parse_str((string) parse_url($item['url'], PHP_URL_QUERY), $query);
        Livewire::withQueryParams($query)->test(ListProjects::class)
            ->assertCanSeeTableRecords([$noArabicText])
            ->assertCanNotSeeTableRecords([$done, $noPhotos, $pinOnly, $hidden]);
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

    public function test_a_deleted_project_waits_in_the_trash_and_comes_back(): void
    {
        $this->actingAs($this->admin());
        Storage::fake('public');
        Storage::disk('public')->put('projects/voices/note.m4a', 'voice');
        $project = Project::create([
            'name' => 'Lake House', 'category' => 'residential', 'status' => 'Completed', 'size' => 'default', 'is_published' => true,
            'imgs' => ['https://example.com/a.jpg'], 'voice_en' => 'projects/voices/note.m4a',
        ]);

        Livewire::test(ListProjects::class)->callTableAction('delete', $project);
        $this->assertSoftDeleted($project);
        $this->assertFalse($project->fresh()->hasPage());
        $this->get('/projects/lake-house')->assertNotFound();
        Storage::disk('public')->assertExists('projects/voices/note.m4a');
        $this->assertSame('lake-house-2', Project::uniqueSlug('lake-house'));   // its address waits with it

        // the trash lists it, and its page still opens to bring it back
        Livewire::test(ListProjects::class)->filterTable('trashed', false)->assertCanSeeTableRecords([$project]);
        Livewire::test(EditProject::class, ['record' => $project->getRouteKey()])->callAction('restore');
        $this->assertNotSoftDeleted($project);

        // deleted for good, its voice note goes too
        $project->delete();
        Livewire::test(ListProjects::class)->filterTable('trashed', false)->callTableAction('forceDelete', $project);
        $this->assertModelMissing($project);
        Storage::disk('public')->assertMissing('projects/voices/note.m4a');
    }

    public function test_leaving_unsaved_changes_asks_first_and_records_open_on_the_site(): void
    {
        $this->actingAs($this->admin());

        // every form remembers what was saved, so leaving with changes asks first
        $this->assertTrue(Filament::getPanel('studio-aks-panel')->hasUnsavedChangesAlerts());
        // pages switch without a reload, but a download is never a page switch
        $panel = Filament::getPanel('studio-aks-panel');
        $this->assertTrue($panel->hasSpaMode());
        $this->assertTrue(str(route('filament.studio-aks-panel.backup-everything'))->is($panel->getSpaUrlExceptions()));
        foreach ([ManageSiteContent::class, CostEstimator::class, ReadAloudSettings::class, EnquiryAlerts::class] as $page) {
            $this->assertNotEmpty(Livewire::test($page)->get('savedDataHash'), $page);
        }
        $this->get(ManageSiteContent::getUrl())->assertSee('setUpSpaModeUnsavedDataChangesAlert', false);   // asks on a page switch too

        // a published client opens its band on the homepage; a hidden one has nothing to open
        $client = Client::create(['name' => 'Rotana', 'is_published' => true]);
        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])
            ->assertActionVisible('viewOnSite')->assertActionHasUrl('viewOnSite', url('/').'#clients');
        $client->update(['is_published' => false]);
        Livewire::test(EditClient::class, ['record' => $client->getRouteKey()])->assertActionHidden('viewOnSite');

        $category = Category::query()->firstOrFail();
        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->assertActionHasUrl('viewOnSite', url('/projects').'?type='.$category->key);
    }

    public function test_a_project_is_duplicated_hidden_with_its_own_number_and_address(): void
    {
        $this->actingAs($this->admin());
        $original = Project::create([
            'name' => 'Garden House', 'category' => 'residential', 'status' => 'Completed', 'size' => 'default', 'is_published' => true,
            'imgs' => ['projects/garden/a.jpg', 'projects/garden/b.jpg'], 'photo_rooms' => ['projects/garden/a.jpg' => 'kitchen'],
            'desc' => 'A house around a garden.', 'voice_en' => 'projects/voices/garden.m4a', 'sort_order' => 1,
        ]);

        Livewire::test(ListProjects::class)->callTableAction('replicate', $original);

        $copy = Project::query()->where('name', 'Garden House (copy)')->firstOrFail();
        $this->assertFalse($copy->is_published);
        $this->assertSame('garden-house-copy', $copy->slug);
        $this->assertSame(Project::formatNum((int) $original->num + 1), $copy->num);
        $this->assertSame($original->imgs, $copy->imgs);
        $this->assertSame(['projects/garden/a.jpg' => 'kitchen'], $copy->photo_rooms);
        $this->assertSame('A house around a garden.', $copy->desc);
        $this->assertNull($copy->voice_en);   // its file would go with whichever project let it go
        $this->assertGreaterThan($original->sort_order, $copy->sort_order);
        $this->assertTrue($original->fresh()->is_published);
    }

    public function test_texts_english_only_on_purpose_are_not_counted_as_missing(): void
    {
        // read only by search engines, so no Kurdish and an Arabic left empty by design
        $this->assertSame('', SiteText::map()['arch_firms']['ku']);
        $this->assertSame([], SiteText::untranslated());
    }
}
