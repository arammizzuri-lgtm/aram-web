<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\Widget;

/**
 * The band at the top of the dashboard: only things that need a decision today.
 *
 * Renders nothing when there is nothing wrong, so its presence always means
 * something rather than becoming furniture people stop seeing.
 */
class AttentionWidget extends Widget
{
    protected string $view = 'filament.widgets.attention';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    /** @return array<int, array{label: string, detail: string, tone: string, url: ?string}> */
    public function getAlerts(): array
    {
        $metrics = app(BusinessMetrics::class);
        $alerts = [];

        if (($provisional = $metrics->shipmentsAwaitingFinalCosting()) > 0) {
            $days = $metrics->oldestProvisionalCostingDays();

            $alerts[] = [
                'label' => $provisional.' '.str('shipment')->plural($provisional).' awaiting final costing',
                'detail' => $days !== null
                    ? "Oldest arrived {$days} days ago — margins stay provisional until it is finalised."
                    : 'Margins stay provisional until these are finalised.',
                'tone' => $days !== null && $days > 30 ? 'danger' : 'warning',
                'url' => url('/admin/shipments'),
            ];
        }

        if (($overdue = $metrics->overdueInvoiceCount()) > 0) {
            $alerts[] = [
                'label' => $overdue.' overdue '.str('invoice')->plural($overdue),
                'detail' => '$'.number_format($metrics->overdueReceivables(), 2).' past its due date.',
                'tone' => 'danger',
                'url' => url('/admin/customers'),
            ];
        }

        $lowStock = Product::query()->lowStock()->count();

        if ($lowStock > 0) {
            $alerts[] = [
                'label' => $lowStock.' '.str('product')->plural($lowStock).' at or below reorder level',
                'detail' => 'Lead times run 35–55 days, so reordering late means stocking out.',
                'tone' => 'warning',
                'url' => url('/admin/products?tableFilters[low_stock][isActive]=true'),
            ];
        }

        return $alerts;
    }
}
