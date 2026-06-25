<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use App\Models\PageView;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudioStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -2;

    protected function getStats(): array
    {
        $today = PageView::uniqueVisitors(now()->startOfDay());
        $week = PageView::uniqueVisitors(now()->startOfWeek());
        $month = PageView::uniqueVisitors(now()->startOfMonth());
        $viewsTd = PageView::pageViews(now()->startOfDay());
        $unread = ContactMessage::query()->where('is_read', false)->count();
        $spark = array_values(PageView::dailyVisitors(7));

        return [
            Stat::make('Visitors today', (string) $today)
                ->description("{$viewsTd} page views")
                ->descriptionIcon('heroicon-m-eye')
                ->chart($spark)
                ->color('warning'),

            Stat::make('This week', (string) $week)
                ->description('unique visitors')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->chart($spark)
                ->color('primary'),

            Stat::make('This month', (string) $month)
                ->description('unique visitors')
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
