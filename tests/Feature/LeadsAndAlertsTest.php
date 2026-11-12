<?php

namespace Tests\Feature;

use App\Filament\Pages\EnquiryAlerts;
use App\Filament\Resources\MissingPages\MissingPageResource;
use App\Mail\NewEnquiry;
use App\Mail\SiteError;
use App\Models\ContactMessage;
use App\Models\MissingPage;
use App\Models\Project;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Models\User;
use App\Support\StudioMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Three things that bring the studio work or keep the site sound: the cost
 * estimate taken away as a PDF (and the studio told who took it), the
 * studio's Google profile and rating, and the broken links and errors the
 * studio is told about.
 */
class LeadsAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    private const HOUSE = ['plot' => '600', 'floors' => '2', 'basement' => '1', 'bedrooms' => '4', 'bathrooms' => '3', 'finish' => 'premium'];

    /* ── The estimate as a PDF ──────────────────────────────────────── */

    public function test_taking_the_estimate_as_a_pdf_files_an_enquiry_with_it(): void
    {
        Mail::fake();
        SiteSetting::put(StudioMail::TO, 'studio@example.com', 'alerts');

        $this->get('/estimate?plot=600')->assertOk()->assertSee('Get this estimate as a PDF')
            ->assertSee('data-sheet="'.url('/estimate/sheet').'"', false);

        $this->postJson('/contact', ['name' => 'Hawre', 'phone' => '0750 123 4567', 'intent' => 'estimate_pdf', 'estimate' => self::HOUSE, 'lang' => 'ku'])->assertOk();
        $e = ContactMessage::sole();
        $this->assertSame('Took this estimate as a PDF.', $e->message);
        $this->assertSame(600, $e->plot_area);
        $this->assertNotNull($e->estimate);
        Mail::assertSent(NewEnquiry::class);

        // any other intent is refused
        $this->postJson('/contact', ['name' => 'X', 'phone' => '0750 123 4567', 'intent' => 'anything'])->assertStatus(422);
    }

    public function test_the_estimate_sheet_prints_the_answers_figures_and_their_name(): void
    {
        $this->get('/estimate/sheet?'.http_build_query(self::HOUSE + ['for' => 'Hawre <b>Aziz</b>', 'print' => 1]))->assertOk()
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSee('Prepared for Hawre Aziz')->assertDontSee('<b>Aziz</b>', false)
            ->assertSee('720 m²')->assertSee('$446,000 – $554,000')
            ->assertSee('window.print()', false);
        $this->get('/ku/estimate/sheet?plot=600')->assertOk()->assertSee('<html lang="ku" dir="rtl">', false)->assertSee('وەڵامەکانت');
        // no answers, nothing to print: back to the estimator
        $this->get('/estimate/sheet')->assertRedirect(url('/estimate'));
    }

    /* ── Google ─────────────────────────────────────────────────────── */

    public function test_the_google_profile_shows_once_it_is_set(): void
    {
        Testimonial::create(['quote_en' => 'They listened before they drew.', 'person_name' => 'Hawre', 'is_published' => true]);

        $this->assertNull(google_profile());
        $this->get('/')->assertOk()->assertDontSee('class="gbadge"', false)->assertDontSee('data-maps=', false);

        SiteSetting::put('google_profile_url', 'https://maps.app.goo.gl/studio', 'content');
        SiteSetting::put('google_review_url', 'https://g.page/r/studio/review', 'content');
        SiteSetting::put('google_rating', '4.9', 'content');
        SiteSetting::put('google_review_count', '23', 'content');

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString('<a class="gbadge" href="https://maps.app.goo.gl/studio"', $html);
        $this->assertStringContainsString('23 reviews on Google', $html);
        $this->assertStringContainsString('data-maps="https://maps.app.goo.gl/studio"', $html);
        $this->assertStringContainsString('"sameAs":["', $html);
        $this->assertStringContainsString('https://maps.app.goo.gl/studio"]', $html);
        // in Kurdish, the rating in Kurdish numerals
        $this->get('/ku')->assertOk()->assertSee('٤٫٩')->assertSee('٢٣ هەڵسەنگاندن لە گووگڵ');
    }

    /* ── Broken links and errors ────────────────────────────────────── */

    public function test_pages_not_found_are_counted_but_not_what_bots_probe_for(): void
    {
        $this->get('/projects/old-villa', ['Referer' => url('/journal')])->assertNotFound();
        $this->get('/projects/old-villa')->assertNotFound();
        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/.env')->assertNotFound();
        $this->getJson('/no-such-thing')->assertNotFound();

        $page = MissingPage::sole();
        $this->assertSame(['/projects/old-villa', 2, url('/journal')], [$page->path, $page->hits, $page->referrer]);
        $this->assertTrue($page->fromThisSite());

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin)->get(MissingPageResource::getUrl())->assertOk()
            ->assertSee('/projects/old-villa')->assertSee('From this site — a link to fix');
    }

    public function test_the_error_alert_says_what_broke_and_where(): void
    {
        $html = (new SiteError(error: 'Division by zero', type: 'DivisionByZeroError', where: 'app/Support/CostEstimate.php:12',
            url: 'https://arammizuri.com/estimate', method: 'GET', referrer: null, agent: 'Chrome', at: '2026-11-18 10:00:00'))->render();
        $this->assertStringContainsString('Division by zero', $html);
        $this->assertStringContainsString('GET https://arammizuri.com/estimate', $html);
        $this->assertStringContainsString('app/Support/CostEstimate.php:12', $html);
    }

    public function test_a_page_that_exists_is_not_counted(): void
    {
        Project::create(['name' => 'Stone House', 'slug' => 'stone-house', 'category' => 'residential', 'status' => 'Completed', 'size' => 'default', 'imgs' => ['https://example.com/a.jpg'], 'is_published' => true]);
        $this->get('/projects')->assertOk();
        $this->assertSame(0, MissingPage::count());
    }

    public function test_the_alert_mailbox_refuses_an_address_for_a_server_and_asks_for_the_rest(): void
    {
        $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']));

        // an e-mail address where the server's name goes, and nothing else — as it was set
        Livewire::test(EnquiryAlerts::class)
            ->fillForm(['host' => 'arammizuri@arammizuri.com', 'username' => '', 'password' => ''])
            ->call('save')
            ->assertHasFormErrors(['host' => 'regex', 'username' => 'required_with', 'password' => 'required']);
        $this->assertSame('', (string) setting(StudioMail::HOST));

        Livewire::test(EnquiryAlerts::class)
            ->fillForm(['host' => 'Mail.ArammIzuri.com ', 'port' => '465', 'username' => 'website@arammizuri.com', 'password' => 'secret'])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame('mail.arammizuri.com', setting(StudioMail::HOST));
        $this->assertTrue(StudioMail::hasMailbox());
    }
}
