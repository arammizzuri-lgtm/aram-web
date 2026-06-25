<?php

namespace App\Filament\Widgets;

use App\Models\ContactMessage;
use Filament\Widgets\Widget;

class RecentMessages extends Widget
{
    protected string $view = 'filament.widgets.recent-messages';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = ['default' => 'full', 'lg' => 1];

    protected function getViewData(): array
    {
        return [
            'messages' => ContactMessage::query()->latest()->limit(5)->get(),
        ];
    }
}
