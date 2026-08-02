<?php

namespace App\Services\Documents;

use Spatie\LaravelPdf\PdfBuilder;

use function Spatie\LaravelPdf\Support\pdf;

/**
 * Renders documents through headless Chromium.
 *
 * Chromium rather than a PHP PDF library because invoices here carry Arabic and
 * Kurdish alongside English. DomPDF and mPDF break letter joining and get RTL
 * layout wrong; a browser engine shapes the text correctly and lets the template
 * be ordinary Blade.
 */
class PdfRenderer
{
    public function make(string $view, array $data): PdfBuilder
    {
        $pdf = pdf()
            ->view($view, $data)
            ->format('a4')
            ->margins(12, 12, 16, 12);

        if ($binary = $this->chromePath()) {
            $pdf->withBrowsershot(fn ($browsershot) => $browsershot->setChromePath($binary));
        }

        return $pdf;
    }

    /**
     * Locate a browser without requiring a puppeteer install.
     *
     * Configurable first so a VPS can point at its own binary; the common
     * desktop locations are only a development convenience.
     */
    public function chromePath(): ?string
    {
        if ($configured = config('services.chrome.path')) {
            return is_executable($configured) || file_exists($configured) ? $configured : null;
        }

        $candidates = [
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            '/usr/bin/chromium',
            '/usr/bin/chromium-browser',
            '/usr/bin/google-chrome',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function isAvailable(): bool
    {
        return $this->chromePath() !== null;
    }
}
