@php
    $summary   = $this->summary();
    $chart     = $this->chart();
    $statement = $this->statement();
    $ageing    = $summary['ageing'];
    $balance   = $summary['balance'];

    /* A minus sign, not a hyphen, and never a bracket: this figure is read
       aloud to the customer as often as it is read on the screen. */
    $usd = fn ($money) => ($money->isNegative() ? '−' : '') . '$' . number_format(abs($money->toFloat()), 2);

    $overdue   = $ageing['30']->plus($ageing['60'])->plus($ageing['90']);
    $agedTotal = $ageing['current']->plus($overdue)->toFloat();

    $buckets = [
        ['Current',    $ageing['current'], 'var(--erp-series-1)'],
        ['31–60 days', $ageing['30'],      'var(--erp-warning)'],
        ['61–90 days', $ageing['60'],      'var(--erp-serious)'],
        ['90+ days',   $ageing['90'],      'var(--erp-critical)'],
    ];
@endphp

<x-filament-panels::page>
    {{-- ───────────────────────────────────────── the balance, and its meaning --}}
    <x-erp.card flush>
        <div class="erp-figures">
            {{-- The one figure this screen is about, so the only one at display
                 size. It is deliberately not coloured by sign: a customer owing
                 you money is ordinary business, and painting every open account
                 red spends the alarm on nothing. The word underneath says which
                 way it points. --}}
            <x-erp.figure
                label="Account balance"
                :value="$usd($balance)"
                :hint="$summary['balance_meaning']"
                lead
                class="sm:col-span-2"
            />

            <x-erp.figure label="Deposits" :value="$usd($summary['deposits'])" hint="money they have paid in" />
            <x-erp.figure label="Spending" :value="$usd($summary['spending'])" hint="what you have invoiced" />
            <x-erp.figure label="Credit held" :value="$usd($summary['credit'])" hint="not yet against an invoice" />

            {{-- Colour lands here and nowhere else on the row: this is the only
                 figure that asks for something to be done. --}}
            <x-erp.figure
                label="Overdue"
                :value="$usd($overdue)"
                hint="more than 30 days old"
                :tone="$overdue->isPositive() ? 'critical' : null"
            />
        </div>

        @if ($summary['credit']->isPositive())
            <div class="flex items-center gap-2 border-t px-5 py-3 text-sm"
                 style="border-color: var(--erp-border); background: var(--erp-bg-surface-2); color: var(--erp-text-secondary)">
                <x-filament::icon icon="heroicon-o-sparkles" class="h-4 w-4 shrink-0"
                                  style="color: var(--erp-series-1)" />
                <span>
                    <strong class="erp-numeric">{{ $usd($summary['credit']) }}</strong>
                    of their money is not against any invoice — it goes onto their next invoice automatically.
                </span>
            </div>
        @endif
    </x-erp.card>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ─────────────────────────────────────────────── balance over time --}}
        <x-erp.card
            class="lg:col-span-2"
            title="Balance over time"
            hint="Where the account stood at the end of each month. Below the line is money they owed you."
        >
            {{-- Twelve points and a zero line need no charting library, and drawn
                 here it takes its colours from the same tokens as everything
                 around it and cannot fail to load. --}}
            <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="h-40 w-full" role="img"
                 aria-label="Account balance over the last twelve months">
                <defs>
                    <linearGradient id="balance-fill" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="var(--erp-series-1)" stop-opacity="0.28" />
                        <stop offset="100%" stop-color="var(--erp-series-1)" stop-opacity="0.02" />
                    </linearGradient>
                </defs>

                <polygon points="{{ $chart['area'] }}" fill="url(#balance-fill)" />

                {{-- Zero is the fact the whole chart is read against, so it is
                     drawn rather than left to the reader to imagine. --}}
                <line x1="0" x2="100" y1="{{ $chart['zero'] }}" y2="{{ $chart['zero'] }}"
                      stroke="var(--erp-axis)" stroke-width="0.4" stroke-dasharray="1.5 1.5"
                      vector-effect="non-scaling-stroke" />

                <polyline points="{{ $chart['points'] }}" fill="none"
                          stroke="var(--erp-series-1)" stroke-width="1.6"
                          stroke-linejoin="round" stroke-linecap="round"
                          vector-effect="non-scaling-stroke" />
            </svg>

            <div class="mt-2 flex justify-between" style="font-size: 10px; color: var(--erp-axis-text)">
                @foreach ($chart['months'] as $month)
                    <span>{{ $month }}</span>
                @endforeach
            </div>
        </x-erp.card>

        {{-- ──────────────────────────────────────────────────────── how overdue --}}
        <x-erp.card title="How overdue" hint="What is still owed, by age of the invoice.">
            @if ($agedTotal > 0)
                <div class="flex h-2 overflow-hidden rounded-full" style="background: var(--erp-bg-sunken)">
                    @foreach ($buckets as [$label, $amount, $colour])
                        @if ($amount->isPositive())
                            <div style="width: {{ round($amount->toFloat() / $agedTotal * 100, 2) }}%; background: {{ $colour }}"
                                 title="{{ $label }}"></div>
                        @endif
                    @endforeach
                </div>
            @endif

            <dl @class(['space-y-2', 'mt-4' => $agedTotal > 0])>
                @foreach ($buckets as [$label, $amount, $colour])
                    <div class="flex items-center justify-between text-sm">
                        <dt class="flex items-center gap-2" style="color: var(--erp-text-secondary)">
                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $colour }}"></span>
                            {{ $label }}
                        </dt>
                        <dd class="erp-numeric"
                            style="color: {{ $amount->isPositive() ? 'var(--erp-text-primary)' : 'var(--erp-text-muted)' }}">
                            {{ $usd($amount) }}
                        </dd>
                    </div>
                @endforeach
            </dl>

            @if ($agedTotal <= 0)
                <p class="mt-4 text-sm erp-good">Nothing outstanding.</p>
            @endif
        </x-erp.card>
    </div>

    {{-- ────────────────────────────────────────────────────────── the statement --}}
    <x-erp.card
        flush
        title="Statement"
        hint="Everything that has passed between you, newest first. The balance column is where the account stood after that line."
    >
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--erp-bg-surface-2)">
                        <th class="erp-label px-5 py-2 text-start">Date</th>
                        <th class="erp-label px-5 py-2 text-start">What</th>
                        <th class="erp-label px-5 py-2 text-end">In</th>
                        <th class="erp-label px-5 py-2 text-end">Out</th>
                        <th class="erp-label px-5 py-2 text-end">Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($statement as $movement)
                        @php
                            $change = $movement['change'];
                            $isIn = ! $change->isNegative();
                        @endphp
                        <tr class="border-t" style="border-color: var(--erp-border)">
                            <td class="whitespace-nowrap px-5 py-3" style="color: var(--erp-text-secondary)">
                                {{ \Illuminate\Support\Carbon::parse($movement['date'])->format('d M Y') }}
                            </td>
                            <td class="px-5 py-3">
                                <div class="font-medium" style="color: var(--erp-text-primary)">
                                    {{ $movement['title'] }}
                                </div>
                                <div style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                                    {{ $movement['detail'] }}
                                </div>
                            </td>
                            <td @class(['whitespace-nowrap px-5 py-3 erp-numeric', 'erp-good' => $isIn])
                                @style(['color: var(--erp-text-muted)' => ! $isIn])>
                                {{ $isIn ? $usd($change) : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 erp-numeric"
                                style="color: {{ $isIn ? 'var(--erp-text-muted)' : 'var(--erp-text-secondary)' }}">
                                {{ $isIn ? '—' : '$' . number_format(abs($change->toFloat()), 2) }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 font-medium erp-numeric"
                                style="color: var(--erp-text-primary)">
                                {{ $usd($movement['balance']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-erp.empty title="Nothing has happened on this account yet">
                                    Invoices and payments will appear here as they are recorded.
                                </x-erp.empty>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-erp.card>

    {{-- ─────────────────────────────────────────────────── what it was all for --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <x-erp.card flush title="Invoices">
            @forelse ($this->invoices() as $invoice)
                <div class="flex items-center justify-between gap-4 border-t px-5 py-3 text-sm"
                     style="border-color: var(--erp-border)">
                    <div class="min-w-0">
                        <div class="truncate font-medium" style="color: var(--erp-text-primary)">
                            {{ $invoice->number }}
                        </div>
                        <div style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                            {{ $invoice->invoice_date?->format('d M Y') }}
                            @if ($invoice->deal) · {{ $invoice->deal->number }} @endif
                        </div>
                    </div>
                    <div class="shrink-0 text-end">
                        <div class="erp-numeric" style="color: var(--erp-text-primary)">
                            {{ number_format((float) $invoice->total, $invoice->currency === 'IQD' ? 0 : 2) }}
                            {{ $invoice->currency }}
                        </div>
                        <div @class(['erp-numeric', 'erp-good' => $invoice->isPaid()])
                             style="font-size: var(--text-hint){{ $invoice->isPaid() ? '' : '; color: var(--erp-text-muted)' }}">
                            {{ $invoice->isPaid() ? 'settled' : $usd($invoice->outstandingBase()) . ' left' }}
                        </div>
                    </div>
                </div>
            @empty
                <x-erp.empty title="Nothing billed yet">
                    Invoices raised against this customer's deals appear here.
                </x-erp.empty>
            @endforelse
        </x-erp.card>

        <x-erp.card flush title="Deals">
            @forelse ($this->deals() as $deal)
                <a href="{{ \App\Filament\Resources\Deals\DealResource::getUrl('edit', ['record' => $deal]) }}"
                   class="erp-transition flex items-center justify-between gap-4 border-t px-5 py-3 text-sm hover:bg-[var(--erp-bg-hover)]"
                   style="border-color: var(--erp-border)">
                    <div class="min-w-0">
                        <div class="truncate font-medium" style="color: var(--erp-text-primary)">
                            {{ $deal->number }}
                        </div>
                        <div style="font-size: var(--text-hint); color: var(--erp-text-muted)">
                            {{ $deal->deal_date?->format('d M Y') }} · {{ $deal->lines->count() }} items
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full px-2 py-0.5"
                          style="font-size: var(--text-hint); background: var(--erp-bg-sunken); color: var(--erp-text-secondary)">
                        {{ \App\Models\Deal::STATUSES[$deal->status] ?? $deal->status }}
                    </span>
                </a>
            @empty
                <x-erp.empty title="No deals yet">
                    Every order for this customer will be listed here.
                </x-erp.empty>
            @endforelse
        </x-erp.card>
    </div>
</x-filament-panels::page>
