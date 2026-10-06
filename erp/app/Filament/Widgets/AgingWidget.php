<?php

namespace App\Filament\Widgets;

use App\Services\Reporting\BusinessMetrics;
use App\Support\Money;
use Filament\Widgets\Widget;

/**
 * How old the money owed is, both ways.
 *
 * "Owed to you" on the position tiles is one number, and one number cannot
 * tell you whether it is healthy. A thousand dollars invoiced last week and a
 * thousand owed since spring are the same figure and a different problem. This
 * splits each side — what customers owe you, what you owe suppliers — by how
 * long it has been sitting, because the rightmost column is the one that turns
 * into a loss, and it is invisible until it is named.
 *
 * Ages are measured from the invoice and the order, not a due date: the terms
 * here are a conversation more often than a number on a document. These are
 * balances, true right now, so nothing here moves with the dashboard's time
 * filter.
 */
class AgingWidget extends Widget
{
    protected string $view = 'filament.widgets.aging';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    /**
     * The buckets, in order, with the oldest marked.
     *
     * @return array<string, mixed>
     */
    public function aging(): array
    {
        $metrics = app(BusinessMetrics::class);
        $canSeeCost = auth()->user()?->can('view_cost') ?? false;

        $receivables = $metrics->receivablesAgeing();

        $data = [
            'receivables' => $this->row($receivables),
            'receivablesTotal' => $this->total($receivables),
            'payables' => null,
            'payablesTotal' => null,
        ];

        // What you owe suppliers is a cost figure, so the assistant does not see
        // it — the receivables side, which they chase, stays.
        if ($canSeeCost) {
            $payables = $metrics->payablesAgeing();
            $data['payables'] = $this->row($payables);
            $data['payablesTotal'] = $this->total($payables);
        }

        return $data;
    }

    /**
     * One side's four buckets as display figures, oldest flagged.
     *
     * @param  array<string, Money>  $buckets
     * @return array<int, array<string, mixed>>
     */
    private function row(array $buckets): array
    {
        $labels = [
            'current' => '0–30 days',
            '30' => '31–60 days',
            '60' => '61–90 days',
            '90' => 'Over 90 days',
        ];

        $rows = [];

        foreach ($labels as $key => $label) {
            $amount = $buckets[$key] ?? Money::zero('USD');
            // PHP casts the numeric string keys ('30','60','90') to ints, so a
            // strict === against the string would never match — cast it back.
            $oldest = (string) $key === '90';

            $rows[] = [
                'label' => $label,
                'value' => $amount->display(),
                // The bad-debt column earns colour only when there is actually
                // something in it; an empty "Over 90" is good news, not an alarm.
                'tone' => ($oldest && $amount->isPositive()) ? 'critical' : null,
            ];
        }

        return $rows;
    }

    /** @param array<string, Money> $buckets */
    private function total(array $buckets): string
    {
        $total = 0.0;

        foreach ($buckets as $amount) {
            $total += $amount->toFloat();
        }

        return Money::of($total, 'USD')->display();
    }
}
