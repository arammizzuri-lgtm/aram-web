<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class PersonalGreeting extends Widget
{
    protected string $view = 'filament.widgets.personal-greeting';

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $hour = (int) now()->format('G');
        $part = $hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening');

        return [
            'greeting' => "Good {$part}",
            'name' => str(auth()->user()?->name ?? 'Aram')->before(' ')->toString() ?: 'Aram',
            'date' => now()->format('l, F j, Y'),
        ];
    }
}
