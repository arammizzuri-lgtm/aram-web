<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Widgets\Concerns\HasWidgetRange;
use App\Filament\Widgets\Concerns\RanksByProfit;
use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

/**
 * Which line of business earns the money — Crystals, Textile, Packaging,
 * Furniture.
 *
 * The four sections are four different trades with four different margins,
 * and the customer and supplier rankings cut across all of them. This is the
 * view across the other way: what each kind of goods brought in, what it cost,
 * and how much of the profit it carries.
 *
 * Lines typed by hand rather than picked from a price list belong to no
 * section and are counted on their own — guessed into one, they would quietly
 * flatter whichever section they landed in.
 */
class CategoryProfitChart extends Widget
{
    use HasWidgetRange;
    use InteractsWithPageFilters;
    use RanksByProfit;

    protected string $view = 'filament.widgets.category-profit';

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function ranking(): array
    {
        [$from, $to] = $this->activeRange();

        $rows = app(BusinessMetrics::class)->profitByCategory($from, $to);

        $url = fn (array $row): ?string => $row['section_id']
            ? ProductResource::getUrl('index', ['filters' => ['price_list_section_id' => ['value' => $row['section_id']]]])
            : null;

        return [
            'rows' => $this->profitRows($rows, 'category', $url),
            'strip' => $this->shareStrip($rows, 'category', 'categories'),
            'table' => $this->profitTable($rows, 'category', 'Category'),
            // Per-line profit leans on an estimate when a deal carries a lump
            // commission or discount; say so rather than present it as exact.
            'approximate' => $rows->contains('approximate', true),
        ];
    }
}
