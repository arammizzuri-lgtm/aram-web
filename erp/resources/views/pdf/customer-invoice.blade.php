@php
    /*
     * The customer's copy. There is no cost on this page and no way to get one.
     *
     * Sorani Kurdish is Arabic script and reads right to left, so this is not a
     * translation of the English layout — it is mirrored. Rendering goes through
     * a real browser because that is what lays out bidirectional text correctly;
     * a PHP PDF library would produce reversed, disconnected letterforms.
     */
    $rtl = $invoice->isRightToLeft();
    $t = fn (string $en, string $ku) => $rtl ? $ku : $en;

    $money = fn ($amount) => number_format((float) $amount, $invoice->currency === 'IQD' ? 0 : 2);
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<!doctype html>
<html lang="{{ $rtl ? 'ckb' : 'en' }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        /*
         * The margins are stated here as well as in PdfRenderer, which sets
         * them through Browsershot's API. Rendered on the server the API values
         * win and these are ignored; printed from a browser there is no API, and
         * without them the invoice runs to the edge of the paper.
         */
        @page { size: A4; margin: 12mm 12mm 16mm; }

        /*
         * Noto Naskh carries the Arabic script Sorani uses. Without an Arabic
         * font installed on the machine doing the rendering, Chromium draws
         * tofu boxes — the English half still looks perfect, which is how this
         * failure survives a casual check and fails in front of a customer.
         */
        body {
            font-family: {{ $rtl ? "'Noto Naskh Arabic', 'Amiri', 'Scheherazade New'," : '' }}
                         'Inter', 'Helvetica Neue', Arial, sans-serif;
            font-size: 11px;
            color: #1a1d21;
            margin: 0;
            line-height: 1.5;
        }

        .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; }
        .company-name { font-size: 17px; font-weight: 700; letter-spacing: -0.01em; }
        .muted { color: #6b7280; }
        .doc-title { font-size: 22px; font-weight: 700; letter-spacing: -0.02em; }
        .doc-number { font-family: 'SFMono-Regular', Consolas, monospace; font-size: 13px; }

        .meta { margin-top: 22px; display: flex; gap: 32px; }
        .meta > div { flex: 1; }
        .label {
            font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.08em;
            color: #6b7280; margin-bottom: 3px;
        }

        table { width: 100%; border-collapse: collapse; margin-top: 22px; }
        th {
            text-align: {{ $rtl ? 'right' : 'left' }};
            font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.06em;
            color: #6b7280; font-weight: 600;
            border-bottom: 1.5px solid #1a1d21; padding: 0 8px 6px;
        }
        td { padding: 8px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .num {
            text-align: {{ $rtl ? 'left' : 'right' }};
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }
        .spec { color: #6b7280; font-size: 9.5px; }

        .totals { margin-top: 16px; display: flex; justify-content: {{ $rtl ? 'flex-start' : 'flex-end' }}; }
        .totals table { width: 240px; margin: 0; }
        .totals td { border: none; padding: 4px 8px; }
        .grand td { border-top: 1.5px solid #1a1d21; font-weight: 700; font-size: 13px; padding-top: 8px; }

        .foot { margin-top: 28px; font-size: 9.5px; color: #6b7280; }
        .cancelled {
            margin-top: 16px; padding: 8px 12px; border: 1.5px solid #d03b3b;
            color: #d03b3b; font-weight: 700; text-align: center; letter-spacing: 0.05em;
        }
    </style>

    {{--
        Screen styling, and only when this page is being shown to somebody.

        Server-side rendering never sees it, which is deliberate: it keeps the
        document that Browsershot draws exactly as it was, and confines the
        change to the case that did not exist before — an invoice opened in a
        browser, where it should look like the sheet of paper it is about to
        become rather than text against the side of the window.
    --}}
    @if ($autoPrint ?? false)
        <style>
            @media screen {
                html { background: #f1f2f4; }

                body {
                    width: 186mm;                 /* A4 less the 12mm margins */
                    min-height: 265mm;
                    margin: 16px auto;
                    padding: 12mm 12mm 16mm;
                    background: #fff;
                    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12), 0 8px 24px rgba(0, 0, 0, 0.08);
                }
            }
        </style>
    @endif
</head>
<body>

<div class="head">
    <div>
        <div class="company-name">{{ $company->name ?? config('app.name') }}</div>
        @if ($company?->address)
            <div class="muted">{{ $company->address }}</div>
        @endif
        @if ($company?->phone)
            <div class="muted">{{ $company->phone }}</div>
        @endif
    </div>

    {{-- Numbers stay left-to-right even on a mirrored page: an invoice number
         and a date are identifiers, not prose, and flipping them makes them
         unreadable to everyone including the person who wrote them. --}}
    <div style="text-align: {{ $rtl ? 'left' : 'right' }}" dir="ltr">
        <div class="doc-title">
            {{ $invoice->type === 'shipping'
                ? $t('SHIPPING INVOICE', 'پسوڵەی گەیاندن')
                : $t('INVOICE', 'پسوڵە') }}
        </div>
        <div class="doc-number">{{ $invoice->number }}</div>
        <div class="muted">{{ $invoice->invoice_date?->format('d M Y') }}</div>
    </div>
</div>

<div class="meta">
    <div>
        <div class="label">{{ $t('Billed to', 'پسوڵە بۆ') }}</div>
        <div style="font-weight: 600">
            {{ $rtl ? ($invoice->customer->name_ku ?: $invoice->customer->name) : $invoice->customer->name }}
        </div>
        @if ($invoice->customer->phone)
            <div class="muted" dir="ltr" style="text-align: {{ $rtl ? 'right' : 'left' }}">
                {{ $invoice->customer->phone }}
            </div>
        @endif
        @if ($invoice->customer->billing_address)
            <div class="muted">{{ $invoice->customer->billing_address }}</div>
        @endif
    </div>

    <div>
        <div class="label">{{ $t('Order', 'داواکاری') }}</div>
        <div dir="ltr" style="text-align: {{ $rtl ? 'right' : 'left' }}">{{ $invoice->deal->number }}</div>

        @if ($invoice->due_date)
            <div class="label" style="margin-top: 10px">{{ $t('Due', 'کاتی دانەوە') }}</div>
            <div>{{ $invoice->due_date->format('d M Y') }}</div>
        @endif
    </div>

    <div>
        <div class="label">{{ $t('Currency', 'دراو') }}</div>
        <div dir="ltr" style="text-align: {{ $rtl ? 'right' : 'left' }}">{{ $invoice->currency }}</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 46%">{{ $t('Description', 'کاڵا') }}</th>
            <th class="num">{{ $t('Qty', 'ژمارە') }}</th>
            <th class="num">{{ $t('Unit price', 'نرخی یەکە') }}</th>
            <th class="num">{{ $t('Amount', 'کۆ') }}</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($invoice->lines as $line)
            <tr>
                <td>
                    <div>{{ $rtl ? ($line->description_ku ?: $line->description) : $line->description }}</div>
                    @if ($line->specification)
                        <div class="spec">{{ $line->specification }}</div>
                    @endif
                </td>
                <td class="num">{{ $qty($line->quantity) }} {{ $line->unit }}</td>
                <td class="num">{{ $money($line->unit_price) }}</td>
                <td class="num">{{ $money($line->line_total) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<div class="totals">
    <table>
        @if ((float) $invoice->discount > 0)
            <tr>
                <td>{{ $t('Subtotal', 'کۆی گشتی') }}</td>
                <td class="num">{{ $money($invoice->subtotal) }}</td>
            </tr>
            <tr>
                <td>{{ $t('Discount', 'داشکاندن') }}</td>
                <td class="num">−{{ $money($invoice->discount) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>{{ $t('Total', 'کۆی کۆتایی') }}</td>
            <td class="num">{{ $money($invoice->total) }} {{ $invoice->currency }}</td>
        </tr>
    </table>
</div>

@if ($invoice->status === 'cancelled')
    <div class="cancelled">{{ $t('CANCELLED', 'هەڵوەشاوەتەوە') }}</div>
@endif

@if ($invoice->notes || $invoice->deal->customer_notes)
    <div class="foot">{{ $invoice->notes ?: $invoice->deal->customer_notes }}</div>
@endif

{{--
    Opened in the browser rather than rendered on the server, the print dialog
    is raised as soon as the page has settled — the browser then draws the PDF
    itself. Nothing else on the page changes: this is the same document either
    way, laid out by the same kind of engine.
--}}
@if ($autoPrint ?? false)
    <script>
        window.addEventListener('load', () => window.print());
    </script>
@endif

</body>
</html>
