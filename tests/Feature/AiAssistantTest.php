<?php

namespace Tests\Feature;

use App\Filament\Pages\AiAssistant;
use App\Models\User;
use App\Services\Ai\AiAnswer;
use App\Services\Ai\AiProvider;
use App\Services\Ai\ClaudeProvider;
use App\Services\Ai\ErpToolSurface;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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
            DemoDataSeeder::class,
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

    // ------------------------------------------------------------ tool surface

    #[Test]
    public function the_owner_gets_the_full_tool_surface(): void
    {
        $names = array_column($this->tools->definitions($this->signIn('owner')), 'name');

        $this->assertContains('get_business_summary', $names);
        $this->assertContains('get_product_profitability', $names);
        $this->assertContains('find_products', $names);
    }

    /**
     * The whole point of a fixed surface: a Sales login cannot reach cost data
     * through a chat box, however the question is phrased.
     */
    #[Test]
    public function sales_is_not_offered_the_cost_bearing_tools(): void
    {
        $names = array_column($this->tools->definitions($this->signIn('sales')), 'name');

        $this->assertNotContains('get_business_summary', $names);
        $this->assertNotContains('get_product_profitability', $names);
        $this->assertNotContains('get_suppliers', $names);
        $this->assertContains('find_products', $names, 'sales still needs the catalogue');
    }

    #[Test]
    public function a_restricted_tool_is_refused_even_if_the_model_asks_for_it_anyway(): void
    {
        $sales = $this->signIn('sales');

        $result = $this->tools->run('get_business_summary', ['from' => '2026-01-01', 'to' => '2026-12-31'], $sales);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not available', $result['error']);
        $this->assertArrayNotHasKey('revenue', $result);
    }

    #[Test]
    public function product_results_strip_cost_and_margin_for_sales(): void
    {
        $ownerRow = $this->tools->run('find_products', ['search' => 'CRY-0042'], $this->signIn('owner'))['products'][0];
        $salesRow = $this->tools->run('find_products', ['search' => 'CRY-0042'], $this->signIn('sales'))['products'][0];

        $this->assertArrayHasKey('landed_cost', $ownerRow);
        $this->assertArrayHasKey('margin_percent', $ownerRow);

        $this->assertArrayNotHasKey('landed_cost', $salesRow);
        $this->assertArrayNotHasKey('margin_percent', $salesRow);
        $this->assertArrayHasKey('selling_price', $salesRow, 'sales still needs the selling price');
    }

    #[Test]
    public function shipment_results_strip_cost_for_sales(): void
    {
        $sales = $this->signIn('sales');

        $row = $this->tools->run('get_shipments', [], $sales)['shipments'][0];

        $this->assertArrayNotHasKey('goods_value', $row);
        $this->assertArrayNotHasKey('cost_uplift_percent', $row);
        $this->assertArrayHasKey('container', $row);
    }

    #[Test]
    public function an_unknown_tool_name_is_refused(): void
    {
        $result = $this->tools->run('drop_all_tables', [], $this->signIn('owner'));

        $this->assertArrayHasKey('error', $result);
    }

    // ------------------------------------------------------------- tool output

    #[Test]
    public function the_business_summary_reports_real_figures(): void
    {
        $summary = $this->tools->run(
            'get_business_summary',
            ['from' => now()->subDays(60)->toDateString(), 'to' => now()->toDateString()],
            $this->signIn('owner'),
        );

        $this->assertGreaterThan(0, $summary['revenue']);
        $this->assertGreaterThan(0, $summary['inventory_value']);
        $this->assertSame(
            round($summary['revenue'] - $summary['cost_of_goods_sold'], 2),
            $summary['gross_profit'],
        );
    }

    /** The sofa loss must be findable — that is the question the tool exists for. */
    #[Test]
    public function profitability_can_surface_the_loss_makers_first(): void
    {
        $result = $this->tools->run(
            'get_product_profitability',
            ['from' => now()->subDays(60)->toDateString(), 'to' => now()->toDateString(), 'worst_first' => true],
            $this->signIn('owner'),
        );

        $worst = $result['products'][0];

        $this->assertLessThan(0, $worst['gross_profit']);
        $this->assertStringContainsString('Sofa', $worst['product']);
    }

    #[Test]
    public function customer_balances_only_list_customers_who_owe(): void
    {
        $result = $this->tools->run('get_customer_balances', [], $this->signIn('owner'));

        $this->assertGreaterThan(0, $result['count']);

        foreach ($result['customers'] as $row) {
            $this->assertGreaterThan(0, $row['outstanding']);
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
                    text: 'Revenue was $12,314.00 over the period.',
                    toolCalls: [['name' => 'get_business_summary', 'input' => ['from' => '2026-07-01']]],
                );
            }

            public function isConfigured(): bool
            {
                return true;
            }
        });

        Livewire::test(AiAssistant::class)
            ->set('question', 'What was my revenue?')
            ->call('ask')
            ->assertSee('Revenue was')
            ->assertSee('Get business summary');
    }

    #[Test]
    public function sales_is_offered_different_suggested_questions(): void
    {
        $this->signIn('sales');

        $suggestions = Livewire::test(AiAssistant::class)->instance()->suggestions();

        $this->assertNotContains('What was my profit over the last 30 days?', $suggestions);
        $this->assertContains('Which products are running low and need reordering?', $suggestions);
    }
}
