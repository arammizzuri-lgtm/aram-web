<?php

namespace App\Filament\Widgets;

use App\Models\InvoiceItem;
use Filament\Widgets\ChartWidget;

/**
 * Which products actually make money, measured against landed cost.
 *
 * A single series, so no legend is needed — the title names it. Horizontal bars
 * because product names are long and reading rotated labels is miserable.
 */
class TopProductsChart extends ChartWidget
{
    protected ?string $heading = 'Most profitable products';

    protected ?string $description = 'Gross profit over the last 90 days, after freight and duty.';

    protected static ?int $sort = 3;

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
        $rows = InvoiceItem::query()
            ->join('invoices', 'invoices.id', '=', 'invoice_items.invoice_id')
            ->join('products', 'products.id', '=', 'invoice_items.product_id')
            ->whereNot('invoices.status', 'cancelled')
            ->whereDate('invoices.invoice_date', '>=', now()->subDays(90))
            ->groupBy('products.id', 'products.name')
            ->selectRaw('products.name as name')
            ->selectRaw('sum(invoice_items.line_total - (invoice_items.quantity * invoice_items.unit_cost_base)) as profit')
            ->orderByDesc('profit')
            ->limit(8)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Gross profit',
                'data' => $rows->map(fn ($r) => round((float) $r->profit, 2))->all(),
                // Slot 1 for a single series; loss-making items flip to the
                // reserved critical colour so they cannot be misread as small wins.
                'backgroundColor' => $rows->map(
                    fn ($r) => (float) $r->profit < 0 ? '#d03b3b' : '#2a78d6'
                )->all(),
                'borderRadius' => 4,
            ]],
            'labels' => $rows->pluck('name')->map(fn (string $n) => str($n)->limit(28))->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => [
                'x' => ['beginAtZero' => true, 'grid' => ['color' => 'rgba(128,128,128,0.15)']],
                'y' => ['grid' => ['display' => false]],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
