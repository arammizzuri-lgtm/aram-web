<?php

namespace Tests\Feature;

use App\Filament\Widgets\StudioStatsOverview;
use App\Filament\Widgets\TopProjects;
use App\Filament\Widgets\TrafficSources;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\Project;
use App\Models\SiteEvent;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\Insights;
use App\Support\TrafficSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dashboard's insights: where visitors come from, which projects they
 * open, and which of them start a WhatsApp chat or send an enquiry.
 */
class InsightsTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/131.0 Safari/537.36';

    private function project(string $name = 'Erbil Tower'): Project
    {
        return Project::create([
            'name' => $name, 'category' => 'commercial', 'status' => 'Completed', 'size' => 'default',
            'imgs' => ['https://example.com/a.jpg'], 'is_published' => true,
        ]);
    }

    public function test_sources_are_read_from_links_apps_and_referrers(): void
    {
        $this->assertSame('google', TrafficSource::classify('https://www.google.com/'));
        $this->assertSame('google', TrafficSource::classify('https://www.google.iq/search?q=x'));
        $this->assertSame('instagram', TrafficSource::classify('https://l.instagram.com/?u=x'));
        $this->assertSame('facebook', TrafficSource::classify('https://lm.facebook.com/l.php'));
        $this->assertSame('whatsapp', TrafficSource::classify('android-app://com.whatsapp/'));
        $this->assertSame('search', TrafficSource::classify('https://www.bing.com/search?q=x'));
        $this->assertSame('other', TrafficSource::classify('https://archdaily.com/x'));
        $this->assertSame('direct', TrafficSource::classify(null));
        $this->assertSame('internal', TrafficSource::classify('https://arammizzuri.com/ku', null, null, 'www.arammizzuri.com'));
        // the in-app browser says who it is, even with no referrer
        $this->assertSame('instagram', TrafficSource::classify(null, 'Mozilla/5.0 (iPhone) Instagram 300.0'));
        $this->assertSame('facebook', TrafficSource::classify(null, 'Mozilla/5.0 [FBAN/FBIOS;FBAV/400.0]'));
        // and a tagged link beats everything
        $this->assertSame('whatsapp', TrafficSource::classify('https://www.google.com/', null, 'wa'));
        $this->assertSame('other', TrafficSource::classify(null, null, 'newsletter'));
    }

    public function test_a_visit_remembers_where_it_came_from_and_credits_the_enquiry(): void
    {
        Mail::fake();
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => 'https://www.google.com/'])->get('/')->assertOk();
        // moving around the site afterwards is not a new source
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => url('/')])->get('/ku')->assertOk();

        $this->assertSame(['google', 'internal'], PageView::orderBy('id')->pluck('source')->all());

        $this->postJson('/contact', ['name' => 'Lana', 'email' => 'lana@example.com'])->assertOk();
        $this->assertSame('google', ContactMessage::sole()->source);
    }

    public function test_overlay_opens_and_whatsapp_taps_are_counted(): void
    {
        $project = $this->project();
        $this->withHeaders(['User-Agent' => self::BROWSER, 'Referer' => 'https://l.instagram.com/'])->get('/');

        // no CSRF token: the pages send these with navigator.sendBeacon
        $this->withHeaders(['User-Agent' => self::BROWSER])
            ->post('/t', ['type' => 'project_open', 'project' => $project->slug])->assertNoContent();
        $this->withHeaders(['User-Agent' => self::BROWSER])
            ->post('/t', ['type' => 'whatsapp_click', 'placement' => 'overlay', 'project' => $project->slug])->assertNoContent();
        // nonsense and robots are answered the same way, and not kept
        $this->withHeaders(['User-Agent' => self::BROWSER])->post('/t', ['type' => 'hack'])->assertNoContent();
        $this->withHeaders(['User-Agent' => 'Googlebot/2.1'])->post('/t', ['type' => 'project_open'])->assertNoContent();

        $this->assertSame(2, SiteEvent::count());
        $tap = SiteEvent::where('type', 'whatsapp_click')->sole();
        $this->assertSame([$project->id, 'overlay', 'instagram'], [$tap->project_id, $tap->placement, $tap->source]);
    }

    public function test_each_whatsapp_button_says_where_it_sits(): void
    {
        $project = $this->project();
        SiteSetting::put('contact_whatsapp', '+964 750 123 4567');

        // the homepage's floating button is about no project — not the last one
        // its project loop happened to leave behind
        $this->get('/')->assertSee('data-wa="fab" data-wa-project=""', false)
            ->assertSee('data-wa="contact"', false)->assertSee('data-wa="overlay"', false);
        $this->get('/projects/'.$project->slug)
            ->assertSee('data-wa="fab" data-wa-project="'.$project->slug.'"', false)
            ->assertSee('data-wa="project" data-wa-project="'.$project->slug.'"', false);
    }

    public function test_the_numbers_add_up_per_source_and_per_project(): void
    {
        $tower = $this->project('Erbil Tower');
        $villa = $this->project('Mountain Villa');
        $hash = fn ($n) => str_repeat((string) $n, 64);

        foreach ([[1, 'google', '/'], [1, 'internal', '/projects/'.$tower->slug], [2, 'google', '/ku/projects/'.$tower->slug],
            [3, 'direct', '/projects/'.$villa->slug]] as [$v, $source, $path]) {
            PageView::create(['visitor_hash' => $hash($v), 'path' => $path, 'source' => $source, 'created_at' => now()]);
        }
        SiteEvent::create(['type' => 'project_open', 'project_id' => $tower->id, 'source' => 'google', 'visitor_hash' => $hash(1), 'created_at' => now()]);
        SiteEvent::create(['type' => 'whatsapp_click', 'project_id' => $tower->id, 'placement' => 'project', 'source' => 'google', 'visitor_hash' => $hash(2), 'created_at' => now()]);
        ContactMessage::create(['name' => 'A', 'email' => 'a@x.test', 'message' => '', 'project_id' => $tower->id, 'source' => 'google']);

        $sources = collect(Insights::sources(now()->subDay(), now()->addDay()))->keyBy('key');
        $this->assertSame(['visitors' => 2, 'whatsapp' => 1, 'enquiries' => 1],
            collect($sources['google'])->only(['visitors', 'whatsapp', 'enquiries'])->all());
        $this->assertSame(1, $sources['direct']['visitors']);
        $this->assertArrayNotHasKey('internal', $sources->all());

        $top = Insights::topProjects(now()->subDay(), now()->addDay());
        $this->assertSame('Erbil Tower', $top[0]['project']->name);
        $this->assertEquals(['opens' => 1, 'pages' => 2, 'views' => 3, 'whatsapp' => 1, 'enquiries' => 1],
            collect($top[0])->only(['opens', 'pages', 'views', 'whatsapp', 'enquiries'])->all());
    }

    public function test_the_dashboard_shows_them(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $project = $this->project();
        PageView::create(['visitor_hash' => str_repeat('a', 64), 'path' => '/projects/'.$project->slug, 'source' => 'instagram', 'created_at' => now()]);

        $this->actingAs($admin);
        Livewire::test(TrafficSources::class, ['pageFilters' => ['range' => '30']])->assertSee('Instagram');
        Livewire::test(TopProjects::class, ['pageFilters' => ['range' => '30']])->assertSee('Erbil Tower');
        // dashboard widgets load after the page, so each is tested as itself
        Livewire::test(StudioStatsOverview::class, ['pageFilters' => ['range' => '30']])->assertSee('WhatsApp taps');
        $this->get('/studio-aks-panel')->assertOk();
    }
}
