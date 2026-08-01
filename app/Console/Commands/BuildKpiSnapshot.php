<?php

namespace App\Console\Commands;

use App\Models\KpiDaily;
use App\Services\Reporting\BusinessMetrics;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Freezes a day's figures so the dashboard reads a row instead of aggregating
 * the whole ledger on every page load, and so a month-end number stays what it
 * was even after a later revaluation moves today's.
 */
class BuildKpiSnapshot extends Command
{
    protected $signature = 'erp:kpi-snapshot {date? : The day to snapshot, defaults to yesterday}';

    protected $description = 'Store a day of business KPIs for fast dashboards and trend reporting';

    public function handle(BusinessMetrics $metrics): int
    {
        $date = $this->argument('date')
            ? Carbon::parse($this->argument('date'))
            : now()->subDay();

        $from = $date->copy()->startOfDay();
        $to = $date->copy()->endOfDay();

        $revenue = $metrics->revenue($from, $to);
        $cogs = $metrics->costOfGoodsSold($from, $to);
        $expenses = $metrics->operatingExpenses($from, $to);

        KpiDaily::updateOrCreate(['date' => $date->toDateString()], [
            'revenue_base' => $revenue,
            'cogs_base' => $cogs,
            'gross_profit_base' => round($revenue - $cogs, 4),
            'expenses_base' => $expenses,
            'net_profit_base' => round($revenue - $cogs - $expenses, 4),
            // Balances are point-in-time, so they are captured as they stand now
            // rather than reconstructed for a past date.
            'inventory_value_base' => $metrics->inventoryValue(),
            'goods_in_transit_base' => $metrics->goodsInTransit(),
            'receivables_base' => $metrics->receivables(),
            'payables_base' => $metrics->payables(),
            'computed_at' => now(),
        ]);

        $this->info("Snapshot stored for {$date->toDateString()}: revenue $".number_format($revenue, 2));

        return self::SUCCESS;
    }
}
