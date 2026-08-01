<?php

namespace Tests\Feature;

use App\Filament\Pages\ReportsPage;
use App\Models\KpiDaily;
use App\Models\User;
use App\Services\Notifications\BusinessAlertService;
use App\Services\Reporting\ReportBuilder;
use Database\Seeders\CrystalCatalogueSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsAndAlertsTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'owner'): User
    {
        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
            CrystalCatalogueSeeder::class,
        ]);

        $user = User::create([
            'name' => ucfirst($role),
            'email' => "{$role}@test.local",
            'password' => 'password',
            'is_active' => true,
        ]);

        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    // ---------------------------------------------------------------- reports

    #[Test]
    public function every_report_builds_without_error(): void
    {
        $this->signIn();
        $builder = app(ReportBuilder::class);

        foreach (array_keys($builder->available()) as $report) {
            $result = $builder->build($report, now()->subYear(), now());

            $this->assertArrayHasKey('headings', $result, "{$report} must return headings");
            $this->assertArrayHasKey('rows', $result, "{$report} must return rows");
            $this->assertNotEmpty($result['headings'], "{$report} headings must not be empty");
        }
    }

    #[Test]
    public function the_sales_report_reconciles_revenue_against_cogs(): void
    {
        $this->signIn();

        $result = app(ReportBuilder::class)->build('sales', now()->subYear(), now());

        $this->assertGreaterThan(0, $result['rows']->count());
        $this->assertEqualsWithDelta(
            $result['totals']['Revenue'] - $result['totals']['COGS'],
            $result['totals']['Gross profit'],
            0.05,
        );
    }

    /** Buckets are what turn "they owe money" into "chase this one today". */
    #[Test]
    public function the_receivables_report_buckets_by_age(): void
    {
        $this->signIn();

        $result = app(ReportBuilder::class)->build('receivables', now()->subYear(), now());

        $this->assertSame(
            ['Code', 'Customer', '0–30 days', '31–60', '61–90', '90+', 'Total due', 'Credit limit'],
            $result['headings'],
        );

        // Every listed customer must actually owe something.
        foreach ($result['rows'] as $row) {
            $this->assertGreaterThan(0, $row[6]);
        }
    }

    #[Test]
    public function the_shipment_report_shows_the_cost_uplift_per_container(): void
    {
        $this->signIn();

        $row = app(ReportBuilder::class)->build('shipment_costs', now()->subYear(), now())['rows']->first();

        $this->assertSame(18300.0, $row[4], 'goods value');
        $this->assertEqualsWithDelta(8148.57, $row[5], 0.01, 'shipping costs');
        $this->assertEqualsWithDelta(44.53, $row[7], 0.01, 'uplift %');
    }

    #[Test]
    public function the_reports_page_renders_and_switches_report(): void
    {
        $this->signIn();

        Livewire::test(ReportsPage::class)
            ->assertOk()
            ->assertSee('Sales & margin')
            ->set('report', 'inventory')
            ->assertOk()
            ->assertSee('Inventory valuation');
    }

    #[Test]
    public function balance_reports_take_no_date_range(): void
    {
        $this->signIn();

        $page = Livewire::test(ReportsPage::class)->set('report', 'inventory')->instance();

        $this->assertFalse($page->isDated());

        $page->report = 'sales';
        $this->assertTrue($page->isDated());
    }

    #[Test]
    public function the_csv_export_streams_the_same_rows_as_the_screen(): void
    {
        $this->signIn();

        $page = Livewire::test(ReportsPage::class)->instance();
        $expected = $page->result()['rows']->count();

        ob_start();
        $page->downloadCsv()->sendContent();
        $csv = ob_get_clean();

        // One header line plus a row each, before the totals block.
        $lines = array_filter(explode("\n", trim($csv)));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv, 'a BOM keeps Excel from mangling Chinese names');
        $this->assertGreaterThanOrEqual($expected + 1, count($lines));
        $this->assertStringContainsString('Invoice', $csv);
    }

    #[Test]
    public function sales_staff_cannot_reach_reports(): void
    {
        $this->signIn('sales');

        $this->assertFalse(ReportsPage::canAccess());
    }

    // ----------------------------------------------------------------- alerts

    #[Test]
    public function the_alert_service_reports_conditions_worth_acting_on(): void
    {
        $this->signIn();

        $keys = app(BusinessAlertService::class)->all()->pluck('key');

        $this->assertTrue($keys->contains('low_stock'));
        $this->assertTrue($keys->isNotEmpty());
    }

    #[Test]
    public function alerts_carry_an_action_and_a_destination(): void
    {
        $this->signIn();

        foreach (app(BusinessAlertService::class)->all() as $alert) {
            $this->assertNotEmpty($alert->title);
            $this->assertNotEmpty($alert->actionLabel, "{$alert->key} must offer an action");
            $this->assertStringStartsWith('/admin/', $alert->url);
            $this->assertContains($alert->severity, ['danger', 'warning', 'info']);
        }
    }

    #[Test]
    public function the_alert_command_sends_to_users_who_can_act_and_does_not_duplicate(): void
    {
        $owner = $this->signIn();

        $this->artisan('erp:alerts')->assertSuccessful();
        $first = $owner->notifications()->count();

        $this->assertGreaterThan(0, $first);

        // A standing condition must not stack a fresh notification every run.
        $this->artisan('erp:alerts')->assertSuccessful();

        $this->assertSame($first, $owner->fresh()->notifications()->count());
    }

    #[Test]
    public function a_dry_run_sends_nothing(): void
    {
        $owner = $this->signIn();

        $this->artisan('erp:alerts --dry-run')->assertSuccessful();

        $this->assertSame(0, $owner->notifications()->count());
    }

    #[Test]
    public function the_kpi_snapshot_stores_a_days_figures(): void
    {
        $this->signIn();

        $this->artisan('erp:kpi-snapshot', ['date' => now()->subDays(20)->toDateString()])->assertSuccessful();

        $snapshot = KpiDaily::firstOrFail();

        $this->assertEqualsWithDelta(
            (float) $snapshot->revenue_base - (float) $snapshot->cogs_base,
            (float) $snapshot->gross_profit_base,
            0.05,
        );
        $this->assertGreaterThan(0, (float) $snapshot->inventory_value_base);
    }
}
