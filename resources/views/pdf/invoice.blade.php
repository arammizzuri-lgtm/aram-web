@php
    /** @var \App\Models\Invoice $invoice */
    $company = $company ?? null;
    $currency = $invoice->currency ?: 'USD';
    $symbol = $currency === 'USD' ? '$' : '';
    $decimals = $currency === 'IQD' ? 0 : 2;
    $money = fn ($v) => $symbol . number_format((float) $v, $decimals) . ($symbol ? '' : ' ' . $currency);
    $isCreditNote = $invoice->invoice_type === 'credit_note';
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->number }}</title>
    <style>
        /* Chromium renders this, so real font stacks and RTL both work. */
        @page { size: A4; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', 'Segoe UI', system-ui, sans-serif;
            font-size: 10.5px;
            line-height: 1.5;
            color: #0b0b0c;
        }

        /* Arabic and Kurdish need a face that shapes correctly; the browser
           handles the joining and bidi ordering that PHP PDF libraries do not.
           `direction: rtl` also flips the default alignment, so it is pinned back
           to left — otherwise an Arabic name drifts away from the English one it
           belongs under. Blocks that want it right (the title) set that after. */
        .ar { font-family: 'Segoe UI', 'Tahoma', 'Arial', sans-serif; direction: rtl; unicode-bidi: isolate; text-align: left; }

        .num { font-variant-numeric: tabular-nums; }

        header { display: flex; justify-content: space-between; align-items: flex-start; padding-bottom: 16px; border-bottom: 2px solid #0b0b0c; }
        .company-name { font-size: 17px; font-weight: 700; letter-spacing: -0.01em; }
        .muted { color: #52525b; }
        .tiny { font-size: 9px; }

        .doc-title { font-size: 22px; font-weight: 700; letter-spacing: -0.02em; text-align: right; }
        .doc-number { font-size: 12px; font-weight: 600; text-align: right; margin-top: 2px; }

        .parties { display: flex; gap: 28px; margin: 20px 0 16px; }
        .party { flex: 1; }
        .label { font-size: 8.5px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #8b8b94; margin-bottom: 4px; }

        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { font-size: 8.5px; text-transform: uppercase; letter-spacing: 0.05em; color: #52525b; text-align: left; padding: 6px 8px; border-bottom: 1px solid #0b0b0c; }
        table.items th.r, table.items td.r { text-align: right; }
        table.items td { padding: 7px 8px; border-bottom: 1px solid #e7e7ea; vertical-align: top; }
        .sku { font-family: 'Consolas', monospace; font-size: 8.5px; color: #8b8b94; }

        .totals { margin-top: 14px; margin-left: auto; width: 46%; }
        .totals tr td { padding: 4px 8px; }
        .totals tr td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
        .totals tr.grand td { border-top: 2px solid #0b0b0c; font-size: 13px; font-weight: 700; padding-top: 8px; }
        .totals tr.due td { background: #f4f4f5; font-weight: 600; }

        footer { margin-top: 26px; padding-top: 12px; border-top: 1px solid #e7e7ea; display: flex; gap: 28px; }
        .stamp { margin-top: 34px; text-align: center; width: 180px; }
        .stamp-line { border-top: 1px solid #8b8b94; padding-top: 4px; }
    </style>
</head>
<body>
    <header>
        <div>
            <div class="company-name">{{ $company?->name ?? config('app.name') }}</div>
            @if ($company?->address)
                <div class="muted tiny">{{ $company->address }}</div>
            @endif
            <div class="muted tiny">
                {{ collect([$company?->city, $company?->country])->filter()->implode(', ') }}
            </div>
            @if ($company?->phone || $company?->email)
                <div class="muted tiny">{{ collect([$company?->phone, $company?->email])->filter()->implode(' · ') }}</div>
            @endif
            @if ($company?->tax_number)
                <div class="muted tiny">Tax No. {{ $company->tax_number }}</div>
            @endif
        </div>

        <div>
            <div class="doc-title">{{ $isCreditNote ? 'CREDIT NOTE' : 'INVOICE' }}</div>
            <div class="doc-title ar tiny muted">{{ $isCreditNote ? 'إشعار دائن' : 'فاتورة' }}</div>
            <div class="doc-number num">{{ $invoice->number }}</div>
        </div>
    </header>

    <div class="parties">
        <div class="party">
            <div class="label">Bill to · إلى</div>
            <div style="font-weight: 600;">{{ $invoice->customer?->name }}</div>
            @if ($invoice->customer?->name_ar)
                <div class="ar">{{ $invoice->customer->name_ar }}</div>
            @endif
            <div class="muted tiny">
                {{ collect([$invoice->customer?->area, $invoice->customer?->city])->filter()->implode(', ') }}
            </div>
            @if ($invoice->customer?->phone)
                <div class="muted tiny num">{{ $invoice->customer->phone }}</div>
            @endif
        </div>

        <div class="party">
            <table style="width: 100%;">
                <tr>
                    <td class="label" style="padding: 0 0 3px;">Invoice date · التاريخ</td>
                    <td class="num" style="text-align: right;">{{ $invoice->invoice_date?->format('d M Y') }}</td>
                </tr>
                <tr>
                    <td class="label" style="padding: 0 0 3px;">Due date · تاريخ الاستحقاق</td>
                    <td class="num" style="text-align: right;">{{ $invoice->due_date?->format('d M Y') ?? '—' }}</td>
                </tr>
                <tr>
                    <td class="label" style="padding: 0;">Currency · العملة</td>
                    <td style="text-align: right;">{{ $currency }}</td>
                </tr>
            </table>
        </div>
    </div>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th>Description · الوصف</th>
                <th class="r" style="width: 10%;">Qty</th>
                <th class="r" style="width: 15%;">Unit price</th>
                <th class="r" style="width: 10%;">Disc.</th>
                <th class="r" style="width: 17%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->items as $index => $item)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td>
                        <div>{{ $item->description ?: $item->product?->name }}</div>
                        <div class="sku">{{ $item->product?->sku }}</div>
                    </td>
                    <td class="r num">{{ rtrim(rtrim(number_format((float) $item->quantity, 2), '0'), '.') }}</td>
                    <td class="r num">{{ $money($item->unit_price) }}</td>
                    <td class="r num">{{ (float) $item->discount_rate > 0 ? $item->discount_rate . '%' : '—' }}</td>
                    <td class="r num">{{ $money($item->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">Subtotal · المجموع</td>
            <td>{{ $money($invoice->subtotal) }}</td>
        </tr>
        @if ((float) $invoice->discount_total > 0)
            <tr>
                <td class="muted">Discount · الخصم</td>
                <td>&minus;{{ $money($invoice->discount_total) }}</td>
            </tr>
        @endif
        @if ((float) $invoice->tax_total > 0)
            <tr>
                <td class="muted">Tax · الضريبة</td>
                <td>{{ $money($invoice->tax_total) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total · الإجمالي</td>
            <td>{{ $money($invoice->total) }}</td>
        </tr>
        @if ((float) $invoice->amount_paid > 0)
            <tr>
                <td class="muted">Paid · المدفوع</td>
                <td>&minus;{{ $money($invoice->amount_paid) }}</td>
            </tr>
            <tr class="due">
                <td>Balance due · المتبقي</td>
                <td>{{ $money($invoice->amountDue()) }}</td>
            </tr>
        @endif
    </table>

    <footer>
        <div style="flex: 1;">
            @if ($invoice->terms || $invoice->customer?->payment_terms_days)
                <div class="label">Terms · الشروط</div>
                <div class="muted tiny">
                    {{ $invoice->terms ?: 'Payment due within '.$invoice->customer->payment_terms_days.' days of invoice date.' }}
                </div>
            @endif

            @if ($invoice->notes)
                <div class="label" style="margin-top: 8px;">Notes</div>
                <div class="muted tiny">{{ $invoice->notes }}</div>
            @endif

            @if (filled($bankDetails ?? null))
                <div class="label" style="margin-top: 8px;">Bank details</div>
                @foreach ($bankDetails as $key => $value)
                    <div class="muted tiny num">{{ ucfirst(str_replace('_', ' ', $key)) }}: {{ $value }}</div>
                @endforeach
            @endif
        </div>

        <div class="stamp">
            <div class="stamp-line tiny muted">Authorised signature · التوقيع</div>
        </div>
    </footer>
</body>
</html>
