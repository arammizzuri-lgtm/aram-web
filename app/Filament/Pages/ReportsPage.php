<?php

namespace App\Filament\Pages;

use App\Services\Reporting\ReportBuilder;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

/**
 * Pick a report, pick a period, read it or export it.
 *
 * Every report is defined once in ReportBuilder and rendered from the same rows
 * it exports, so what you download is exactly what you were looking at.
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
     * These URLs get bookmarked and pasted into messages.
     */
    protected static ?string $slug = 'reports';

    protected static ?string $title = 'Reports';

    protected string $view = 'filament.pages.reports';

    public string $report = 'sales';

    public string $from = '';

    public string $to = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->can('view_cost') ?? false;
    }

    public function mount(): void
    {
        $this->from = now()->subDays(90)->toDateString();
        $this->to = now()->toDateString();
    }

    public function definitions(): array
    {
        return app(ReportBuilder::class)->available();
    }

    public function isDated(): bool
    {
        return $this->definitions()[$this->report]['dated'] ?? true;
    }

    /** @return array{headings: array<int, string>, rows: Collection, totals: array<string, mixed>} */
    public function result(): array
    {
        return app(ReportBuilder::class)->build(
            $this->report,
            Carbon::parse($this->from)->startOfDay(),
            Carbon::parse($this->to)->endOfDay(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('csv')
                ->label('Export CSV')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->action(fn () => $this->downloadCsv()),
        ];
    }

    /**
     * Streamed rather than assembled in memory: an inventory or price-list export
     * can run to tens of thousands of rows.
     */
    public function downloadCsv(): StreamedResponse
    {
        $result = $this->result();
        $label = str($this->definitions()[$this->report]['label'])->slug();
        $filename = "{$label}-{$this->from}-to-{$this->to}.csv";

        return response()->streamDownload(function () use ($result) {
            $handle = fopen('php://output', 'wb');

            // Excel reads a bare UTF-8 CSV as Windows-1252 and mangles the Chinese
            // supplier names; the BOM is what stops that.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $result['headings']);

            foreach ($result['rows'] as $row) {
                fputcsv($handle, $row);
            }

            if (filled($result['totals'])) {
                fputcsv($handle, []);

                foreach ($result['totals'] as $label => $value) {
                    fputcsv($handle, [$label, is_numeric($value) ? round((float) $value, 2) : $value]);
                }
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
