<?php

namespace App\Filament\Pages;

use App\Services\Reporting\BusinessMetrics;
use App\Services\Reporting\ReportBuilder;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use League\Csv\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Every report on one screen, with a date range and an export.
 *
 * One page rather than one per report because they share everything: the same
 * range, the same table, the same export. Adding a report is an entry in
 * ReportBuilder, not a new screen to keep in step with the others.
 */
class ReportsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Reports';

    /*
     * Without this the URL is /admin/reports-page — Filament slugs the class
     * name, and "Page" is a framework suffix, not part of what this screen is.
     */
    protected static ?string $slug = 'reports';

    protected string $view = 'filament.pages.reports-page';

    public string $report = 'profit_by_deal';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        // A rolling window, so the 1st of the month is not a cliff.
        $this->from = now()->subDays(90)->toDateString();
        $this->to = now()->toDateString();

        if (! array_key_exists($this->report, $this->available())) {
            $this->report = array_key_first($this->available());
        }
    }

    /**
     * The reports this user may run.
     *
     * Cost-bearing ones are withheld rather than shown with columns blanked —
     * a report of deal numbers with the money removed invites the question the
     * permission exists to prevent.
     *
     * @return array<string, array<string, mixed>>
     */
    public function available(): array
    {
        $all = app(ReportBuilder::class)->definitions();

        if (auth()->user()?->can('view_cost')) {
            return $all;
        }

        return array_filter($all, fn (array $r) => ! $r['cost']);
    }

    public function definition(): array
    {
        return $this->available()[$this->report] ?? array_values($this->available())[0];
    }

    public function rows(): Collection
    {
        return app(ReportBuilder::class)->rows(
            $this->report,
            Carbon::parse($this->from)->startOfDay(),
            Carbon::parse($this->to)->endOfDay(),
        );
    }

    /** Column indexes that hold money or counts, so the view can align them. */
    public function numericColumns(): array
    {
        return $this->definition()['numeric'] ?? [];
    }

    /**
     * Stream the same rows the table is showing.
     *
     * Streamed rather than built in memory: a year of deals is a large array,
     * and the browser starts receiving before the query has finished.
     */
    public function export(): StreamedResponse
    {
        $definition = $this->definition();
        $rows = $this->rows();
        $name = str($definition['label'])->slug().'-'.$this->from.'-to-'.$this->to.'.csv';

        return new StreamedResponse(function () use ($definition, $rows) {
            $csv = Writer::createFromStream(fopen('php://output', 'w'));
            $csv->insertOne($definition['columns']);

            foreach ($rows as $row) {
                $csv->insertOne($row);
            }
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    /**
     * The three comparisons, over the same window as the report above them.
     *
     * They live here rather than on the dashboard on purpose. The first screen
     * is for checking; this one is for deciding, and a question like "is air
     * worth it" is not one anybody answers in passing. Sharing the date range
     * with the report is the point of putting them on the same screen — you set
     * a period once and everything on the page answers for it.
     *
     * @return array<string, mixed>
     */
    public function comparisons(): array
    {
        $metrics = app(BusinessMetrics::class);
        $from = Carbon::parse($this->from)->startOfDay();
        $to = Carbon::parse($this->to)->endOfDay();

        $pricing = $metrics->marginByPricingMethod($from, $to);
        $shipping = $metrics->shippingEconomics($from, $to);
        $thin = $metrics->thinnestDeals($from, $to);

        return [
            'pricing' => $pricing,
            // Bars are drawn against the best margin on the screen, so the
            // comparison is between the methods rather than against 100%.
            'pricing_widest' => max(1.0, (float) ($pricing->max('margin_percent') ?: 1)),
            'shipping' => $shipping,
            'thin' => $thin,
            'thin_widest' => max(1.0, (float) ($thin->max('margin_percent') ?: 1)),
        ];
    }

    public function getTitle(): string
    {
        return $this->definition()['label'];
    }

    public function getSubheading(): ?string
    {
        return $this->definition()['description'];
    }
}
