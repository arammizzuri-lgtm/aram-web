<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Support\DashboardRange;
use App\Support\Insights;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StudioStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -2;

    /** Five figures, side by side on a wide screen. */
    protected int|array|null $columns = ['@xl' => 5, '!@lg' => 5];

    protected function getStats(): array
    {
        [$start, $end] = DashboardRange::resolve($this->pageFilters);

        $series = PageView::seriesBetween($start, $end);
        $spark = array_values($series);
        $visitors = PageView::visitorsBetween($start, $end);
        $views = PageView::pageViewsBetween($start, $end);
        $taps = Insights::whatsappTaps($start, $end);
        $enquiries = ContactMessage::query()->whereBetween('created_at', [$start, $end])->count();
        $unread = ContactMessage::query()->where('is_read', false)->count();
        $estimates = Insights::estimates($start, $end);

        return [
            Stat::make('Visitors', (string) $visitors)
                ->description(DashboardRange::label($this->pageFilters))
                ->descriptionIcon('heroicon-m-eye')
                ->chart($spark)
                ->color('warning'),

            Stat::make('Page views', (string) $views)
                ->description('total loads')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->chart($spark)
                ->color('primary'),

            // every WhatsApp button pressed, on any page (engage.js)
            Stat::make('WhatsApp taps', (string) $taps)
                ->description('chats started from the site')
                ->descriptionIcon('heroicon-m-chat-bubble-bottom-center-text')
                ->color('success'),

            Stat::make('Enquiries', (string) $enquiries)
                ->description($unread > 0 ? $unread.' unread — need a reply' : 'all read')
                ->descriptionIcon('heroicon-m-envelope')
                ->color($unread > 0 ? 'danger' : 'gray')
                ->url(ContactMessageResource::getUrl()),

            // "What will your house cost?" worked out, and sent on to the studio
            Stat::make('Estimates', (string) $estimates['run'])
                ->description($estimates['sent'].' sent with an enquiry')
                ->descriptionIcon('heroicon-m-calculator')
                ->color($estimates['sent'] > 0 ? 'success' : 'gray'),
        ];
    }
}