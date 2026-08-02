<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * What is waiting on you, above everything else.
 *
 * Only things you can act on today — goods bought without approval, deals
 * delivered but not billed, freight whose cost is known but never invoiced.
 * A dashboard that lists everything is a dashboard nobody reads.
 */
class AttentionWidget extends Widget
{
    protected string $view = 'filament.widgets.attention-widget';

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    public function items(): Collection
    {
        return app(BusinessMetrics::class)->needsAttention();
    }
}
