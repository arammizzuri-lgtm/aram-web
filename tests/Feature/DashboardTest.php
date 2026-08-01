<?php

namespace Tests\Feature;

use App\Filament\Widgets\AttentionWidget;
use App\Filament\Widgets\BusinessOverviewWidget;
use App\Filament\Widgets\ContainersWidget;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\User;
use App\Services\Reporting\BusinessMetrics;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\FoundationSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = 'owner'): User
    {
        $this->seed([
            FoundationSeeder::class,
            ReferenceDataSeeder::class,
            RolePermissionSeeder::class,
            DemoDataSeeder::class,
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

    #[Test]
    public function the_dashboard_widgets_render(): void
    {
        $this->signIn();

        Livewire::test(AttentionWidget::class)->assertOk();
        Livewire::test(BusinessOverviewWidget::class)->assertOk();
        Livewire::test(ContainersWidget::class)->assertOk();
    }

    #[Test]
    public function receiving_the_container_valued_stock_at_landed_cost(): void
    {
        $this->signIn();

        $crystal = Product::where('sku', 'CRY-0042')->firstOrFail();
        $level = StockLevel::where('product_id', $crystal->id)->firstOrFail();

        // 100 received, 26 sold across the demo invoices.
        $this->assertSame('74.0000', $level->quantity);
        $this->assertSame('107.6865', $level->average_cost);
    }

    #[Test]
    public function gross_profit_is_measured_against_landed_cost_not_the_supplier_price(): void
    {
        $this->signIn();
        $metrics = app(BusinessMetrics::class);

        $from = now()->subDays(30)->startOfDay();
        $to = now()->endOfDay();

        $revenue = $metrics->revenue($from, $to);
        $cogs = $metrics->costOfGoodsSold($from, $to);

        $this->assertGreaterThan(0, $revenue);
        $this->assertGreaterThan(0, $cogs);
        $this->assertSame(round($revenue - $cogs, 2), $metrics->grossProfit($from, $to));

        // A margin computed off supplier prices would be far higher than reality;
        // anything above 50% here would mean landed cost was not being applied.
        $this->assertLessThan(50, $metrics->grossMarginPercent($from, $to));
    }

    #[Test]
    public function inventory_and_goods_in_transit_are_reported_separately(): void
    {
        $this->signIn();
        $metrics = app(BusinessMetrics::class);

        $this->assertGreaterThan(0, $metrics->inventoryValue());
        // The demo container has landed, so nothing is on the water.
        $this->assertSame(0.0, $metrics->goodsInTransit());
    }

    #[Test]
    public function receivables_exclude_what_has_already_been_paid(): void
    {
        $this->signIn();
        $metrics = app(BusinessMetrics::class);

        $this->assertGreaterThan(0, $metrics->receivables());
        $this->assertLessThan(
            $metrics->revenue(now()->subDays(30), now()),
            $metrics->receivables(),
            'some invoices were settled, so receivables must be below revenue'
        );
    }

    #[Test]
    public function the_attention_band_reports_provisional_costing(): void
    {
        $this->signIn();

        $labels = collect(app(AttentionWidget::class)->getAlerts())->pluck('label');

        $this->assertTrue($labels->contains(fn (string $l) => str_contains($l, 'awaiting final costing')));
    }

    #[Test]
    public function sales_staff_do_not_see_the_financial_dashboard(): void
    {
        $this->signIn('sales');

        $this->assertFalse(BusinessOverviewWidget::canView());
        $this->assertFalse(AttentionWidget::canView());
    }
}
