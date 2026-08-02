<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistant;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\Ai\AiAnswer;
use App\Services\Ai\AiProvider;
use App\Services\Ai\ClaudeProvider;
use App\Services\Ai\ErpToolSurface;
use App\Services\Deals\DealWriter;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The assistant is a security boundary as much as a feature.
 *
 * These tests exist mainly to prove one thing: an assistant login cannot get
 * cost or profit out of it, however the question is phrased. The model is never
 * trusted to decline — the data simply is not there to return.
 */
class AiAssistantTest extends TestCase
{
    use RefreshDatabase;

    private ErpToolSurface $tools;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->tools = app(ErpToolSurface::class);
    }

    private function signIn(string $role): User
    {
        $user = User::create([
            'name' => ucfirst($role),
            'email' => "{$role}-".uniqid().'@test.local',
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    /** One deal, so there is something real to ask about. */
    private function seedDeal(): Deal
    {
        $customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'IQD', 'is_active' => true,
        ]);

        $supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu Crystals', 'default_currency' => 'CNY',
        ]);

        $deal = Deal::create([
            'number' => 'D-2026-0001',
            'customer_id' => $customer->id,
            'deal_date' => today(),
            'sell_currency' => 'IQD',
            'rmb_usd_rate' => 7.2,
            'iqd_usd_rate' => 1470,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'supplier_id' => $supplier->id,
            'description' => 'Crystal P07 20mm',
            'quantity' => 500,
            'unit_cost' => 12.50,
            'cost_currency' => 'CNY',
            'unit_price' => 28000,
        ]);

        return app(DealWriter::class)->sync($deal->fresh());
    }

    private function seedProduct(): Product
    {
        return Product::create([
            'sku' => 'CRY-0042',
            'name' => 'Crystal Chandelier A-330',
            'product_category_id' => ProductCategory::create([
                'name' => 'Chandeliers', 'slug' => 'chandeliers',
            ])->id,
            'unit_id' => Unit::where('code', 'PCS')->firstOrFail()->id,
            'cost_price' => 85,
            'selling_price' => 155,
            'is_active' => true,
        ]);
    }

    // ------------------------------------------------------------ tool surface

    #[Test]
    public function the_owner_gets_the_full_tool_surface(): void
    {
        $names = array_column($this->tools->definitions($this->signIn('owner')), 'name');

        $this->assertContains('find_deals', $names);
        $this->assertContains('find_products', $names);
        $this->assertContains('get_customer_balances', $names);
        $this->assertContains('get_consignments', $names);
        $this->assertContains('get_deal_profitability', $names);
        $this->assertContains('get_suppliers', $names);
    }

    /**
     * Withheld, not emptied.
     *
     * A profitability tool with the money stripped out returns deal numbers and
     * nothing else, which invites the model to fill the gap with a guess. Not
     * offering it is the honest signal that this user cannot ask.
     */
    #[Test]
    public function the_assistant_is_not_offered_the_cost_bearing_tools(): void
    {
        $names = array_column($this->tools->definitions($this->signIn('assistant')), 'name');

        $this->assertNotContains('get_deal_profitability', $names);
        $this->assertNotContains('get_suppliers', $names);

        // They still need to do the job.
        $this->assertContains('find_deals', $names);
        $this->assertContains('get_consignments', $names);
        $this->assertContains('get_customer_balances', $names);
    }

    /** The second lock, on the assumption the first was somehow bypassed. */
    #[Test]
    public function a_restricted_tool_is_refused_even_if_the_model_asks_for_it_anyway(): void
    {
        $result = $this->tools->run('get_deal_profitability', [], $this->signIn('assistant'));

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('permission', $result['error']);
        $this->assertArrayNotHasKey('deals', $result);
    }

    #[Test]
    public function an_unknown_tool_name_is_refused(): void
    {
        $result = $this->tools->run('drop_all_tables', [], $this->signIn('owner'));

        $this->assertArrayHasKey('error', $result);
    }

    // ------------------------------------------------------------- cost gating

    #[Test]
    public function deal_results_carry_profit_for_the_owner_but_not_the_assistant(): void
    {
        $this->seedDeal();

        $owner = $this->tools->run('find_deals', [], $this->signIn('owner'))['deals'][0];
        $this->assertArrayHasKey('cost_usd', $owner);
        $this->assertArrayHasKey('profit_usd', $owner);

        $assistant = $this->tools->run('find_deals', [], $this->signIn('assistant'))['deals'][0];
        $this->assertArrayNotHasKey('cost_usd', $assistant);
        $this->assertArrayNotHasKey('profit_usd', $assistant);

        // What they legitimately need is still there.
        $this->assertSame('D-2026-0001', $assistant['deal']);
        $this->assertArrayHasKey('revenue_usd', $assistant);
    }

    #[Test]
    public function product_results_strip_cost_and_margin_for_the_assistant(): void
    {
        $this->seedProduct();

        $owner = $this->tools->run('find_products', ['search' => 'CRY-0042'], $this->signIn('owner'))['products'][0];
        $this->assertArrayHasKey('cost', $owner);
        $this->assertArrayHasKey('margin_percent', $owner);

        $assistant = $this->tools->run('find_products', ['search' => 'CRY-0042'], $this->signIn('assistant'))['products'][0];
        $this->assertArrayNotHasKey('cost', $assistant);
        $this->assertArrayNotHasKey('margin_percent', $assistant);
        $this->assertSame(155.0, $assistant['selling_price'], 'the selling price is theirs to use');
    }

    // ------------------------------------------------------------ tool output

    #[Test]
    public function profitability_reports_the_real_figures_across_two_currencies(): void
    {
        $this->seedDeal();

        $result = $this->tools->run(
            'get_deal_profitability',
            ['worst_first' => true],
            $this->signIn('owner'),
        );

        $row = $result['deals'][0];

        // 500 x 28,000 IQD / 1,470 = 9,523.81 in ; 6,250 CNY / 7.2 = 868.06 out
        $this->assertSame('D-2026-0001', $row['deal']);
        $this->assertSame(9523.81, round($row['revenue_usd'], 2));
        $this->assertSame(868.06, round($row['cost_usd'], 2));
        $this->assertSame(8655.75, round($row['profit_usd'], 2));
    }

    #[Test]
    public function customer_balances_can_be_narrowed_to_those_who_owe(): void
    {
        $this->seedDeal();
        $owner = $this->signIn('owner');

        // Nothing invoiced yet, so nobody owes anything.
        $this->assertSame(0, $this->tools->run('get_customer_balances', ['owing_only' => true], $owner)['count']);

        // Unfiltered, the customer is still listed at zero.
        $all = $this->tools->run('get_customer_balances', [], $owner);
        $this->assertSame(1, $all['count']);
        $this->assertSame('Ali Trading', $all['customers'][0]['customer']);
    }

    /** Where the goods are is not commercially sensitive. */
    #[Test]
    public function consignments_are_visible_to_both_roles(): void
    {
        foreach (['owner', 'assistant'] as $role) {
            $result = $this->tools->run('get_consignments', [], $this->signIn($role));

            $this->assertArrayNotHasKey('error', $result, $role);
            $this->assertArrayHasKey('consignments', $result);
        }
    }

    // -------------------------------------------------------------------- page

    #[Test]
    public function the_assistant_page_renders_without_a_key(): void
    {
        $this->signIn('owner');
        config()->set('services.anthropic.key', null);

        Livewire::test(AiAssistant::class)
            ->assertOk()
            ->assertSee('needs a Claude API key');
    }

    #[Test]
    public function the_provider_reports_a_missing_key_instead_of_failing(): void
    {
        config()->set('services.anthropic.key', null);

        $answer = app(ClaudeProvider::class)->ask(
            [['role' => 'user', 'content' => 'Hello']],
            $this->signIn('owner'),
        );

        $this->assertFalse($answer->succeeded());
        $this->assertStringContainsString('ANTHROPIC_API_KEY', $answer->error);
    }

    #[Test]
    public function asking_a_question_records_the_answer_and_its_tool_trail(): void
    {
        $this->signIn('owner');

        $this->app->bind(AiProvider::class, fn () => new class implements AiProvider
        {
            public function ask(array $messages, User $user): AiAnswer
            {
                return new AiAnswer(
                    text: 'You have one open deal worth $9,523.81.',
                    toolCalls: [['name' => 'find_deals', 'input' => ['open_only' => true]]],
                );
            }

            public function isConfigured(): bool
            {
                return true;
            }
        });

        Livewire::test(AiAssistant::class)
            ->set('question', 'How are my deals doing?')
            ->call('ask')
            ->assertSee('one open deal')
            ->assertSee('Find deals');
    }

    /** Suggestions must not invite a question the user's tools cannot answer. */
    #[Test]
    public function the_assistant_is_not_offered_cost_bearing_suggestions(): void
    {
        $this->signIn('assistant');
        $suggestions = Livewire::test(AiAssistant::class)->instance()->suggestions();

        $this->assertNotContains('What was my profit over the last 30 days?', $suggestions);
        $this->assertContains('Where are my goods at the moment?', $suggestions);

        $this->signIn('owner');
        $ownerSuggestions = Livewire::test(AiAssistant::class)->instance()->suggestions();

        $this->assertContains('What was my profit over the last 30 days?', $ownerSuggestions);
    }
}
