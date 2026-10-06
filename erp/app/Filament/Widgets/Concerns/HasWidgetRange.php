<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\DashboardRange;
use Illuminate\Support\Carbon;

/**
 * A time window a widget can set for itself, on top of the dashboard's.
 *
 * The filter at the top of the dashboard sets a default for everything; this
 * lets one widget look at a different stretch without disturbing the rest —
 * last quarter's suppliers beside this month's cash, on the same screen. Until
 * a widget is given its own window it simply follows the dashboard, so the
 * ordinary case stays one control at the top.
 *
 * The choice is kept per widget between visits, keyed by the widget's class, so
 * a reload finds each one looking where you left it.
 *
 * Pairs with Filament's InteractsWithPageFilters, which supplies $pageFilters —
 * the dashboard default this falls back to.
 */
trait HasWidgetRange
{
    /** Null means "follow the dashboard"; anything else is this widget's own. */
    public ?string $widgetRange = null;

    public ?string $widgetRangeStart = null;

    public ?string $widgetRangeEnd = null;

    public function mountHasWidgetRange(): void
    {
        $stored = session()->get($this->rangeSessionKey());

        if (is_array($stored)) {
            $this->widgetRange = $stored['range'] ?? null;
            $this->widgetRangeStart = $stored['start'] ?? null;
            $this->widgetRangeEnd = $stored['end'] ?? null;
        }
    }

    public function updatedWidgetRange(): void
    {
        $this->persistRange();
    }

    public function updatedWidgetRangeStart(): void
    {
        $this->persistRange();
    }

    public function updatedWidgetRangeEnd(): void
    {
        $this->persistRange();
    }

    /**
     * The filters this widget resolves against: its own, or the dashboard's.
     *
     * @return array<string, mixed>|null
     */
    protected function activeFilters(): ?array
    {
        if ($this->widgetRange === null || $this->widgetRange === '') {
            return $this->pageFilters;
        }

        return [
            'range' => $this->widgetRange,
            'startDate' => $this->widgetRangeStart,
            'endDate' => $this->widgetRangeEnd,
        ];
    }

    /** @return array{0: Carbon, 1: Carbon} */
    protected function activeRange(): array
    {
        return DashboardRange::resolve($this->activeFilters());
    }

    public function activeLabel(): string
    {
        return DashboardRange::label($this->activeFilters());
    }

    private function persistRange(): void
    {
        session()->put($this->rangeSessionKey(), [
            'range' => $this->widgetRange,
            'start' => $this->widgetRangeStart,
            'end' => $this->widgetRangeEnd,
        ]);
    }

    private function rangeSessionKey(): string
    {
        return 'erp_widget_range_'.md5(static::class);
    }
}
