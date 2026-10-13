<?php

namespace Tests\Feature;

use App\Filament\Pages\CostEstimator;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Widgets\StudioStatsOverview;
use App\Mail\NewEnquiry;
use App\Models\Category;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\SiteEvent;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\CostEstimate;
use App\Support\StudioMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "What will your house cost?": the sums, the page, its live result, and an
 * estimate sent to the studio — worked out again on the server.
 */
class CostEstimatorTest extends TestCase
{
    use RefreshDatabase;

    private const BROWSER = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 Chrome/131.0 Safari/537.36';

    /** 600 m² plot, 2 floors, basement, 4 bedrooms, 3 bathrooms, the middle finish. */
    private const HOUSE = ['plot' => '600', 'floors' => '2', 'basement' => '1', 'bedrooms' => '4', 'bathrooms' => '3', 'finish' => 'premium'];

    public function test_the_sums_at_the_default_rates(): void
    {
        $e = CostEstimate::calculate(CostEstimate::inputs(self::HOUSE));

        // 600 × 60% × 2 floors; the basement is one floor's footprint
        $this->assertSame([720, 360], [$e['built'], $e['basement_area']]);
        $this->assertSame([
            'building' => [302000, 374000],     // 720 × $420–520
            'basement' => [126000, 162000],     // 360 × $350–450
            'bathrooms' => [18000, 18000],      // 3 × $6,000
        ], $e['lines']);
        $this->assertSame([446000, 554000], $e['construction']);
        $this->assertSame([22300, 44400], $e['fee']);          // 5–8% of it, to the $100
        $this->assertSame('500k-1m', CostEstimate::budgetBand($e));
        $this->assertSame("≈ \u{2066}584 – 726\u{2069} million IQD", CostEstimate::iqdRange($e['construction'], $e['iqd_rate'], 'en'));
        $this->assertSame("≈ \u{2066}29 – 58\u{2069} ملیۆن دینار", CostEstimate::iqdRange($e['fee'], $e['iqd_rate'], 'ku'));
        // a range inside Kurdish words stays one left-to-right piece
        $this->assertSame("\u{2066}$300 – $380\u{2069} بۆ هەر م²", CostEstimate::rangeIn('{low} – {high} بۆ هەر م²', '$300', '$380'));

        // their own built area wins; nonsense is held to sensible limits; no plot, no estimate
        $own = CostEstimate::calculate(CostEstimate::inputs(['plot' => '600', 'built' => '500', 'floors' => '99', 'finish' => 'gold', 'pool' => 'on']));
        $this->assertSame([500, 4, 'premium', false], [$own['built'], $own['inputs']['floors'], $own['inputs']['finish'], $own['built_auto']]);
        $this->assertSame([25000, 45000], $own['lines']['pool']);
        $this->assertNull(CostEstimate::inputs(['floors' => 2]));
    }

    public function test_the_admin_sets_the_rates_and_the_fee_basis(): void
    {
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin);
        $this->get(CostEstimator::getUrl())->assertOk()->assertSee('An example, at the saved rates')->assertSee('$446,000 – $554,000');

        Livewire::test(CostEstimator::class)
            ->fillForm(['premium_low' => 500, 'premium_high' => 600, 'fee_basis' => 'per_m2', 'fee_low' => 10, 'fee_high' => 15, 'iqd_rate' => 0])
            ->call('save')->assertHasNoFormErrors();

        $e = CostEstimate::calculate(CostEstimate::inputs(self::HOUSE));
        $this->assertSame([360000, 432000], $e['lines']['building']);
        $this->assertSame([10800, 16200], $e['fee']);            // (720 + 360) m² × $10–15
        $this->assertNull(CostEstimate::iqdRange($e['construction'], $e['iqd_rate'], 'en'));
    }

    public function test_the_page_answers_from_its_address_in_both_languages(): void
    {
        $this->get('/estimate')->assertOk()
            ->assertSee('What will your house cost?')->assertSee('Enter your plot area')
            ->assertSee('<link rel="canonical" href="'.url('/estimate').'">', false);

        $page = $this->get('/estimate?'.http_build_query(self::HOUSE))->assertOk();
        $page->assertSee('$446,000 – $554,000')->assertSee("≈ \u{2066}584 – 726\u{2069} million IQD")
            ->assertSee('720 m² built, Premium finish')->assertSee('360 m² basement')->assertSee('3 bathrooms')
            ->assertSee('$22,300 – $44,400')->assertSee("\u{2066}5 – 8\u{2069}% of the building cost")
            ->assertSee('Send this to the studio');

        $this->get('/ku/estimate?'.http_build_query(self::HOUSE))->assertOk()
            ->assertSee('<html lang="ku" dir="rtl">', false)
            ->assertSee('خانووەکەت چەندی تێدەچێت؟')->assertSee('ملیۆن دینار')->assertSee('تەواوکاریی باش');

        $this->get('/sitemap.xml')->assertSee('<loc>'.url('/estimate').'</loc>', false);
        $this->get('/')->assertSee('class="est-band"', false)->assertSee('href="'.url('/estimate').'"', false);
    }

    public function test_the_live_result_is_the_same_sums_and_not_a_page_view(): void
    {
        $views = PageView::count();
        $this->withHeaders(['User-Agent' => self::BROWSER])
            ->getJson('/estimate/calc?'.http_build_query(self::HOUSE))->assertOk()
            ->assertJson(['ok' => true, 'auto_built' => 720])
            ->assertJsonPath('html', fn (string $html) => str_contains($html, '$446,000 – $554,000'));
        $this->getJson('/ku/estimate/calc?plot=')->assertOk()->assertJson(['ok' => false, 'auto_built' => null]);
        $this->assertSame($views, PageView::count());

        $this->withHeaders(['User-Agent' => self::BROWSER])->post('/t', ['type' => 'estimate'])->assertNoContent();
        $this->assertSame(1, SiteEvent::where('type', 'estimate')->count());
    }

    public function test_an_estimate_sent_to_the_studio_is_worked_out_again_and_filed_with_the_enquiry(): void
    {
        Mail::fake();
        SiteSetting::put(StudioMail::TO, 'studio@example.com', 'alerts');
        Category::firstOrCreate(['key' => 'residential'], ['name' => 'Residential', 'name_ku' => 'نیشتەجێبوون']);

        $this->postJson('/contact', [
            'name' => 'Hawre', 'phone' => '0750 123 4567', 'page' => '/estimate',
            // a figure typed into the browser is not taken: only the answers are
            'estimate' => self::HOUSE + ['construction' => '1'],
        ])->assertOk();

        $e = ContactMessage::sole();
        $this->assertSame([446000, 554000], $e->estimate['construction']);
        $this->assertSame([600, '500k-1m', 'residential'], [$e->plot_area, $e->budget, $e->project_type]);
        $this->assertSame('$446,000 – $554,000 to build · design fee $22,300 – $44,400', $e->estimateLabel());
        Mail::assertSent(NewEnquiry::class, fn (NewEnquiry $m) => str_contains($m->render(), '600 m² plot · 2 floors · 720 m² built · basement · 4 bedrooms · 3 bathrooms · Premium'));

        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.test', 'password' => 'secret-password']);
        $this->actingAs($admin)->get(ContactMessageResource::getUrl('edit', ['record' => $e]))->assertOk()
            ->assertSee('Their estimate')->assertSee('$446,000 – $554,000 to build');
        Livewire::test(StudioStatsOverview::class, ['pageFilters' => ['range' => '30']])
            ->assertSee('Estimates')->assertSee('1 sent with an enquiry');
    }

    public function test_switched_off_it_is_gone_from_the_site(): void
    {
        SiteSetting::put('est_enabled', '0', 'estimate');
        $this->get('/estimate')->assertNotFound();
        $this->getJson('/estimate/calc?plot=600')->assertNotFound();
        $this->get('/')->assertDontSee('class="est-band"', false);
        $this->get('/sitemap.xml')->assertDontSee('/estimate');
    }
}
