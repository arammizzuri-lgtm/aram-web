<?php

namespace Tests\Feature;

use App\Filament\Widgets\PipelineWidget;
use App\Filament\Widgets\PositionWidget;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\DealLine;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Deals\DealWriter;
use App\Services\Reporting\BusinessMetrics;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The first screen, with a business behind it.
 *
 * Rendered against an empty database a dashboard proves almost nothing — every
 * figure is zero and every list is its empty state. So this one has deals at
 * several stages and money on both sides, which is the only condition in which
 * the widgets do any work.
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Customer $customer;

    private Supplier $supplier;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([FoundationSeeder::class, ReferenceDataSeeder::class, RolePermissionSeeder::class]);

        $this->owner = User::create([
            'name' => 'Owner', 'email' => 'owner@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $this->owner->assignRole('owner');
        $this->actingAs($this->owner);

        $this->customer = Customer::create([
            'code' => 'C-001', 'name' => 'Ali Trading',
            'default_currency' => 'USD', 'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'code' => 'SUP-A', 'name' => 'Yiwu', 'default_currency' => 'CNY',
        ]);
    }

    private function deal(string $number, string $stage, float $price): Deal
    {
        $deal = Deal::create([
            'number' => $number,
            'customer_id' => $this->customer->id,
            'deal_date' => today(),
            'sell_currency' => 'USD',
            'rmb_usd_rate' => 7.2,
        ]);

        DealLine::create([
            'deal_id' => $deal->id,
            'description' => 'Crystal P07',
            'quantity' => 1,
            'unit_cost' => 10,
            'cost_currency' => 'CNY',
            'unit_price' => $price,
        ]);

        app(DealWriter::class)->sync($deal->fresh());

        // Set after the sync, which advances the stage on its own.
        $deal->fresh()->update(['status' => $stage]);

        return $deal->fresh();
    }

    // ------------------------------------------------------------- pipeline

    #[Test]
    public function the_pipeline_counts_open_work_by_stage(): void
    {
        $this->deal('D-2026-0001', 'draft', 100);
        $this->deal('D-2026-0002', 'shipping', 250);
        $this->deal('D-2026-0003', 'shipping', 150);
        $this->deal('D-2026-0004', 'closed', 900);

        $stages = app(BusinessMetrics::class)->pipeline()->keyBy('stage');

        $this->assertSame(1, $stages['draft']['count']);
        $this->assertSame(2, $stages['shipping']['count']);
        $this->assertSame('400.0000', $stages['shipping']['value']->amount);

        // Closed is not work, so it is not a stage on this widget at all.
        $this->assertArrayNotHasKey('closed', $stages->all());
    }

    #[Test]
    public function every_stage_keeps_its_column_even_when_empty(): void
    {
        $this->deal('D-2026-0001', 'draft', 100);

        // The gap in the run is information: an empty stage says the queue has
        // moved past it, and hiding it would hide that.
        $this->assertCount(7, app(BusinessMetrics::class)->pipeline());
    }

    #[Test]
    public function the_pipeline_widget_renders_its_stages(): void
    {
        $this->deal('D-2026-0001', 'purchasing', 100);

        Livewire::test(PipelineWidget::class)
            ->assertOk()
            ->assertSee('What is on')
            ->assertSee('Purchasing');
    }

    #[Test]
    public function the_pipeline_says_so_when_nothing_is_open(): void
    {
        Livewire::test(PipelineWidget::class)
            ->assertOk()
            ->assertSee('Nothing open');
    }

    // ------------------------------------------------------------- position

    /**
     * The heading that was wrong.
     *
     * Six tiles sat under "Last 30 days" and three of them were balances —
     * true at this moment, belonging to no window. "Owed to you, last 30 days"
     * is a sentence that cannot be acted on.
     */
    #[Test]
    public function balances_and_flows_are_stated_under_separate_headings(): void
    {
        $this->deal('D-2026-0001', 'delivered', 500);

        Livewire::test(PositionWidget::class)
            ->assertOk()
            ->assertSee('Last 30 days')
            ->assertSee('Right now')
            ->assertSee('Owed to you')
            ->assertSee('Profit');
    }

    /** Cost is dropped for the assistant, not blanked. */
    #[Test]
    public function an_assistant_sees_no_cost_figures_on_the_dashboard(): void
    {
        $this->deal('D-2026-0001', 'delivered', 500);

        $assistant = User::create([
            'name' => 'Assistant', 'email' => 'assistant@test.local',
            'password' => 'password', 'is_active' => true,
        ]);
        $assistant->assignRole('assistant');
        $this->actingAs($assistant);

        Livewire::test(PositionWidget::class)
            ->assertOk()
            ->assertSee('Invoiced')
            ->assertDontSee('Profit')
            ->assertDontSee('Owed to suppliers')
            ->assertDontSee('Bought at your own risk');
    }

    // ------------------------------------------------------------ the screen

    #[Test]
    public function the_dashboard_opens_with_its_quick_actions(): void
    {
        $this->deal('D-2026-0001', 'quoted', 500);

        $this->get('/'.config('erp.mount'))
            ->assertOk()
            ->assertSee('New deal')
            ->assertSee('Record a payment')
            ->assertSee('Log a tracking number');
    }

    /**
     * Every navigation group has something in it, and everything has a group.
     *
     * Trading — Deals, Consignments, Purchases, nearly everything anybody does
     * here — was never declared, so it took no icon and sorted outside the
     * intended order. Logistics and Inventory were declared and empty, left by
     * the redesign that removed the warehouse.
     */
    #[Test]
    public function the_navigation_groups_match_what_is_in_them(): void
    {
        $panel = filament()->getPanel('admin');

        $declared = collect($panel->getNavigationGroups())
            ->map(fn ($group) => $group->getLabel())
            ->filter()
            ->values();

        $used = collect(glob(app_path('Filament/**/*.php')))
            ->merge(glob(app_path('Filament/**/**/*.php')))
            ->map(fn (string $file) => file_get_contents($file))
            ->flatMap(fn (string $source) => preg_match_all(
                "/navigationGroup = '([^']+)'/", $source, $found
            ) ? $found[1] : [])
            ->unique()
            ->values();

        foreach ($used as $group) {
            $this->assertTrue(
                $declared->contains($group),
                "navigation group [{$group}] is used but never declared, so it takes no icon and sorts arbitrarily",
            );
        }

        foreach ($declared as $group) {
            $this->assertTrue(
                $used->contains($group),
                "navigation group [{$group}] is declared but empty",
            );
        }
    }
}
