<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Small things a visitor reads as signs of a studio at work, or not: an
 * e-mail address that answers, a copyright that is current, and social
 * links that go somewhere.
 */
class TrustDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function set(string $key, string $value): void
    {
        SiteSetting::put($key, $value);
        SiteSetting::flushCache();
    }

    public function test_the_studio_email_is_on_its_real_domain(): void
    {
        $this->get('/')->assertOk()->assertSee('Info@arammizuri.com')->assertDontSee('arammizzuri');
        $this->get('/careers')->assertOk()->assertSee('Info@arammizuri.com');
    }

    public function test_a_misspelled_address_already_saved_is_corrected(): void
    {
        SiteSetting::put('contact_email_new', 'Info@arammizzuri.com', 'contact');
        SiteSetting::put('alerts_to', 'Info@arammizzuri.com, aram@example.com', 'alerts');
        SiteSetting::put('contact_title_en', 'Let’s build', 'contact');

        (require database_path('migrations/2026_10_15_000003_fix_contact_email_domain.php'))->up();

        $this->assertSame('Info@arammizuri.com', DB::table('site_settings')->where('key', 'contact_email_new')->value('value'));
        $this->assertSame('Info@arammizuri.com, aram@example.com', DB::table('site_settings')->where('key', 'alerts_to')->value('value'));
        $this->assertSame('Let’s build', DB::table('site_settings')->where('key', 'contact_title_en')->value('value'));
    }

    public function test_the_copyright_runs_from_the_first_year_to_this_one(): void
    {
        $now = now()->year;

        $this->set('footer_copy', "© 2022 Aram Mizuri Architecture.\nAll rights reserved.");
        $this->get('/')->assertSee('© 2022–'.$now.' Aram Mizuri Architecture.', false);

        // this year already, or a range written by hand: left as it is
        $this->set('footer_copy', "© {$now} Aram Mizuri Architecture.");
        $this->get('/')->assertSee("© {$now} Aram Mizuri Architecture.", false)->assertDontSee("{$now}–{$now}", false);
        $this->set('footer_copy', '© 2019–2021 Aram Mizuri Architecture.');
        $this->get('/')->assertSee('© 2019–2021 Aram Mizuri Architecture.', false);
    }

    public function test_social_links_without_an_address_are_not_shown(): void
    {
        $this->set('footer_socials', json_encode([
            ['label' => 'Instagram', 'url' => 'https://instagram.com/AramMizuri'],
            ['label' => 'Archello', 'url' => '#'],
            ['label' => 'Pinterest', 'url' => ''],
        ]));

        $this->get('/')->assertSee('https://instagram.com/AramMizuri', false)
            ->assertDontSee('>Archello', false)->assertDontSee('>Pinterest', false);
        $this->assertSame(['Instagram'], array_column(linked_socials(), 'label'));
    }
}
