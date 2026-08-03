<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Deals\DealResource;
use App\Services\Reporting\BusinessMetrics;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * What is actually on, and where it has got to.
 *
 * The whole system is organised around the deal and the dashboard never showed
 * one: it opened on money tiles and a chart, so "what am I working on?" — the
 * first question of the morning — was answered by going somewhere else.
 *
 * Reading left to right is reading the business: requests arriving, goods being
 * bought, goods moving, goods landed. A stage that has swollen is the queue
 * backing up, and it is visible here before it is visible anywhere else.
 *
 * Each stage links to the deals list already filtered to it, so the widget is
 * also the way in rather than only a picture.
 */
class PipelineWidget extends Widget
{
    protected string $view = 'filament.widgets.pipeline';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /** @return Collection<int, array<string, mixed>> */
    public function stages(): Collection
    {
        return app(BusinessMetrics::class)->pipeline()->map(fn (array $stage) => [
            ...$stage,
            'url' => DealResource::getUrl('index', [
                'tableFilters' => ['status' => ['values' => [$stage['stage']]]],
            ]),
        ]);
    }

    /** The tallest column, so the bars have something to be relative to. */
    public function busiest(): int
    {
        return max(1, (int) $this->stages()->max('count'));
    }

    public function total(): int
    {
        return (int) $this->stages()->sum('count');
    }
}
