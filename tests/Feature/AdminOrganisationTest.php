<?php

namespace Tests\Feature;

use App\Filament\Widgets\NeedsAttention;
use App\Models\ContactMessage;
use App\Models\MissingPage;
use App\Models\Project;
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

    public function test_texts_english_only_on_purpose_are_not_counted_as_missing(): void
    {
        // read only by search engines, so no Kurdish and an Arabic left empty by design
        $this->assertSame('', SiteText::map()['arch_firms']['ku']);
        $this->assertSame([], SiteText::untranslated());
    }
}
