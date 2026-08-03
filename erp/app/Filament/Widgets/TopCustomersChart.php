<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use App\Services\Reporting\BusinessMetrics;
use App\Support\Money;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * Which customers actually earned you money.
 *
 * A ranked list rather than a bar chart, and the change is not cosmetic. With
 * eight names or fewer — which is this business — a bar chart spends a third of
 * a screen to say "this one is bigger", makes you read a figure off an axis by
 * eye, and gives you nowhere to click. A list gives the name, the exact figure,
 * the share and the way through to the account, in a quarter of the height.
 *
 * A chart earns its space when the shape of the data is the point. Here the
 * ranking is the point, and a ranking is a list.
 *
 * Ranked by profit rather than revenue, because the two disagree often enough
 * to matter — a customer who buys a lot at a thin margin can outrank one who
 * quietly makes you more. Losses stay in the list, marked, rather than being
 * sorted out of sight: they are the rows worth finding.
 */
class TopCustomersChart extends Widget
{
    protected string $view = 'filament.widgets.top-customers';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        $rows = app(BusinessMetrics::class)
            ->profitByCustomer(now()->subDays(90)->startOfDay(), now()->endOfDay())
            ->take(8);

        /*
         * The bar is drawn against the largest magnitude in the list, so a loss
         * of 400 and a profit of 400 read as equally significant. Scaling a
         * loss against the biggest *profit* would make a bad month look small.
         */
        $widest = max(1.0, $rows->max(fn (array $row) => abs((float) $row['profit'])) ?: 1.0);

        return $rows->map(fn (array $row) => [
            'customer' => $row['customer'] ?? 'Unknown',
            'profit' => Money::of($row['profit'], 'USD'),
            'negative' => (float) $row['profit'] < 0,
            'share' => max(2, round(abs((float) $row['profit']) / $widest * 100)),
            'url' => $this->accountUrl($row['customer'] ?? null),
        ]);
    }

    /**
     * The row is a way in, not only a figure.
     *
     * Matched by name because that is what the report carries; a customer since
     * renamed simply does not link, which is better than linking to the wrong
     * account.
     */
    private function accountUrl(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $customer = Customer::query()->where('name', $name)->first();

        return $customer
            ? CustomerResource::getUrl('account', ['record' => $customer])
            : null;
    }
}
