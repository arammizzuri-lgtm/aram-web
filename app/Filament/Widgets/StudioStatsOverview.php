<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use App\Models\PageView;
use App\Support\DashboardRange;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudioStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        [$start, $end] = DashboardRange::resolve($this->pageFilters);
        $monthly = $start->diffInDays($end) > 92;

        $series = PageView::seriesBetween($start, $end);
        $spark = array_values($series);
        $visitors = PageView::visitorsBetween($start, $end);
        $views = PageView::pageViewsBetween($start, $end);
        $avgPerBucket = round($visitors / max(count($series), 1), 1);
        $unread = ContactMessage::query()->where('is_read', false)->count();

        return [
            Stat::make('Visitors', (string) $visitors)
                ->description(DashboardRange::label($this->pageFilters))
                ->descriptionIcon('heroicon-m-eye')
                ->chart($spark)
                ->color('warning'),

            Stat::make('Daily average', (string) $avgPerBucket)
                ->description($monthly ? 'visitors per month' : 'visitors per day')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart($spark)
                ->color('primary'),

            Stat::make('Page views', (string) $views)
                ->description('total loads')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart($spark)
                ->color('success'),

            Stat::make('Unread messages', (string) $unread)
                ->description($unread > 0 ? 'need a reply' : 'all caught up')
                ->descriptionIcon('heroicon-m-envelope')
                ->color($unread > 0 ? 'danger' : 'gray'),
        ];
    }
}
