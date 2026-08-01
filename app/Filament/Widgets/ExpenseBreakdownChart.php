<?php

namespace App\Filament\Widgets;

use App\Models\Expense;
use Filament\Widgets\ChartWidget;

/**
 * Where the overhead goes.
 *
 * Shipment-allocated spend is excluded on purpose: freight and duty already sit
 * inside landed cost, so counting them here would charge the business twice for
 * the same money.
 */
class ExpenseBreakdownChart extends ChartWidget
{
    protected ?string $heading = 'Operating expenses by category';

    protected ?string $description = 'Last 90 days. Shipping sits in landed cost, not here.';

    protected static ?int $sort = 4;

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = Expense::query()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->whereNot('expenses.status', 'draft')
            ->where('expenses.is_allocated_to_shipment', false)
            ->whereDate('expenses.expense_date', '>=', now()->subDays(90))
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->selectRaw('expense_categories.name as name, sum(expenses.base_amount) as total')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Spend',
                'data' => $rows->map(fn ($r) => round((float) $r->total, 2))->all(),
                'backgroundColor' => '#1baf7a',
                'borderRadius' => 4,
            ]],
            'labels' => $rows->pluck('name')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            // Single series: the heading names it, so a legend box adds nothing.
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(128,128,128,0.15)']],
                'y' => ['grid' => ['display' => false]],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
