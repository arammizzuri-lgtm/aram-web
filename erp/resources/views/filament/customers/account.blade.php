@php
    use App\Support\Money;

    $customer   = $this->customer();
    $summary    = $this->summary();
    $chart      = $this->chart();
    $statement  = $this->statement();
    $ageing     = $summary['ageing'];
    $balance    = $summary['balance'];

    /* Dinars are never quoted with fractions; dollars always are. */
    $usd = fn ($money) => ($money->isNegative() ? '−' : '') . '$' . number_format(abs($money->toFloat()), 2);

    $overdue = $ageing['30']->plus($ageing['60'])->plus($ageing['90']);
    $agedTotal = $ageing['current']->plus($overdue)->toFloat();

    $buckets = [
        ['Current',    $ageing['current'], 'var(--erp-series-1)'],
        ['31–60 days', $ageing['30'],      'var(--erp-warning)'],
        ['61–90 days', $ageing['60'],      'var(--erp-serious)'],
        ['90+ days',   $ageing['90'],      'var(--erp-critical)'],
    ];
@endphp

<x-filament-panels::page>
    {{-- ─────────────────────────────────────────── the balance, and its meaning --}}
    <section class="rounded-xl border overflow-hidden"
             style="border-color: var(--erp-border); background: var(--erp-bg-surface)">

        <div class="grid gap-px" style="background: var(--erp-border); grid-template-columns: repeat(auto-fit, minmax(190px, 1fr))">

            {{-- The headline. Deliberately not coloured by sign: a customer owing
                 you money is ordinary business, and painting every open account red
                 would spend the alarm on nothing. The word underneath says which
                 way it points; colour is saved for the overdue figure, which is the
                 one that actually wants attention. --}}
            <div class="p-5" style="background: var(--erp-bg-surface); grid-column: span 2">
                <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--erp-text-muted)">
                    Account balance
                </div>
                <div class="mt-2 text-4xl font-semibold erp-numeric" style="color: var(--erp-text-primary)">
                    {{ $usd($balance) }}
                </div>
                <div class="mt-1 text-sm" style="color: var(--erp-text-secondary)">
                    {{ $summary['balance_meaning'] }}
                </div>
            </div>

            @foreach ([
                ['Deposits',   $summary['deposits'],  'money they have paid in'],
                ['Spending',   $summary['spending'],  'what you have invoiced'],
                ['Credit held', $summary['credit'],   'not yet against an invoice'],
            ] as [$label, $value, $hint])
                <div class="p-5" style="background: var(--erp-bg-surface)">
                    <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--erp-text-muted)">
                        {{ $label }}
                    </div>
                    <div class="mt-2 text-xl font-semibold erp-numeric" style="color: var(--erp-text-primary)">
                        {{ $usd($value) }}
                    </div>
                    <div class="mt-1 text-xs" style="color: var(--erp-text-muted)">{{ $hint }}</div>
                </div>
            @endforeach

            <div class="p-5" style="background: var(--erp-bg-surface)">
                <div class="text-xs font-semibold uppercase tracking-wide" style="color: var(--erp-text-muted)">
                    Overdue
                </div>
                <div class="mt-2 text-xl font-semibold erp-numeric"
                     style="color: {{ $overdue->isPositive() ? 'var(--erp-critical)' : 'var(--erp-text-primary)' }}">
                    {{ $usd($overdue) }}
                </div>
                <div class="mt-1 text-xs" style="color: var(--erp-text-muted)">more than 30 days old</div>
            </div>
        </div>

        @if ($summary['credit']->isPositive())
            <div class="flex items-center gap-2 border-t px-5 py-3 text-sm"
                 style="border-color: var(--erp-border); background: var(--erp-bg-surface-2); color: var(--erp-text-secondary)">
                <x-filament::icon icon="heroicon-o-sparkles" class="h-4 w-4" style="color: var(--erp-series-1)" />
                <span>
                    <strong class="erp-numeric">{{ $usd($summary['credit']) }}</strong>
                    of their money is not against any invoice.
                    It goes onto their next invoice automatically.
                </span>
            </div>
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- ───────────────────────────────────────────────── balance over time --}}
        <section class="rounded-xl border p-5 lg:col-span-2"
                 style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
            <div class="flex items-baseline justify-between">
                <div>
                    <h2 class="text-sm font-semibold" style="color: var(--erp-text-primary)">Balance over time</h2>
                    <p class="mt-0.5 text-xs" style="color: var(--erp-text-muted)">
                        Where the account stood at the end of each month.
                        Below the line is money they owed you.
                    </p>
                </div>
            </div>

            <div class="mt-4">
                {{-- Twelve points and a zero line need no charting library, and
                     drawn here it takes its colours from the same tokens as the
                     rest of the page and cannot fail to load. --}}
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
                         drawn as a line rather than left to the reader. --}}
                    <line x1="0" x2="100" y1="{{ $chart['zero'] }}" y2="{{ $chart['zero'] }}"
                          stroke="var(--erp-axis)" stroke-width="0.4" stroke-dasharray="1.5 1.5"
                          vector-effect="non-scaling-stroke" />

                    <polyline points="{{ $chart['points'] }}" fill="none"
                              stroke="var(--erp-series-1)" stroke-width="1.6"
                              stroke-linejoin="round" stroke-linecap="round"
                              vector-effect="non-scaling-stroke" />
                </svg>

                <div class="mt-2 flex justify-between text-[10px]" style="color: var(--erp-axis-text)">
                    @foreach ($chart['months'] as $month)
                        <span>{{ $month }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- ────────────────────────────────────────────────────────── how overdue --}}
        <section class="rounded-xl border p-5"
                 style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
            <h2 class="text-sm font-semibold" style="color: var(--erp-text-primary)">How overdue</h2>
            <p class="mt-0.5 text-xs" style="color: var(--erp-text-muted)">
                What is still owed, by age of the invoice.
            </p>

            @if ($agedTotal > 0)
                <div class="mt-4 flex h-2 overflow-hidden rounded-full" style="background: var(--erp-bg-sunken)">
                    @foreach ($buckets as [$label, $amount, $colour])
                        @if ($amount->isPositive())
                            <div style="width: {{ round($amount->toFloat() / $agedTotal * 100, 2) }}%; background: {{ $colour }}"
                                 title="{{ $label }}"></div>
                        @endif
                    @endforeach
                </div>
            @endif

            <dl class="mt-4 space-y-2">
                @foreach ($buckets as [$label, $amount, $colour])
                    <div class="flex items-center justify-between text-sm">
                        <dt class="flex items-center gap-2" style="color: var(--erp-text-secondary)">
                            <span class="h-2 w-2 rounded-full" style="background: {{ $colour }}"></span>
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
                <p class="mt-4 text-sm" style="color: var(--erp-good)">Nothing outstanding.</p>
            @endif
        </section>
    </div>

    {{-- ──────────────────────────────────────────────────────────── the statement --}}
    <section class="rounded-xl border overflow-hidden"
             style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
        <div class="border-b px-5 py-4" style="border-color: var(--erp-border)">
            <h2 class="text-sm font-semibold" style="color: var(--erp-text-primary)">Statement</h2>
            <p class="mt-0.5 text-xs" style="color: var(--erp-text-muted)">
                Everything that has passed between you, newest first. The balance column is
                where the account stood after that line.
            </p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr style="background: var(--erp-bg-surface-2); color: var(--erp-text-muted)">
                        <th class="px-5 py-2 text-left font-medium">Date</th>
                        <th class="px-5 py-2 text-left font-medium">What</th>
                        <th class="px-5 py-2 text-right font-medium">In</th>
                        <th class="px-5 py-2 text-right font-medium">Out</th>
                        <th class="px-5 py-2 text-right font-medium">Balance</th>
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
                                <div class="text-xs" style="color: var(--erp-text-muted)">
                                    {{ $movement['detail'] }}
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right erp-numeric"
                                style="color: {{ $isIn ? 'var(--erp-good)' : 'var(--erp-text-muted)' }}">
                                {{ $isIn ? $usd($change) : '—' }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right erp-numeric"
                                style="color: {{ $isIn ? 'var(--erp-text-muted)' : 'var(--erp-text-secondary)' }}">
                                {{ $isIn ? '—' : '$' . number_format(abs($change->toFloat()), 2) }}
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-right font-medium erp-numeric"
                                style="color: var(--erp-text-primary)">
                                {{ $usd($movement['balance']) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-10 text-center" style="color: var(--erp-text-muted)">
                                Nothing has happened on this account yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ───────────────────────────────────────────────────── what it was all for --}}
    <div class="grid gap-6 lg:grid-cols-2">
        <section class="rounded-xl border overflow-hidden"
                 style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
            <div class="border-b px-5 py-4" style="border-color: var(--erp-border)">
                <h2 class="text-sm font-semibold" style="color: var(--erp-text-primary)">Invoices</h2>
            </div>
            <div class="divide-y" style="--tw-divide-opacity: 1">
                @forelse ($this->invoices() as $invoice)
                    <div class="flex items-center justify-between border-t px-5 py-3 text-sm"
                         style="border-color: var(--erp-border)">
                        <div>
                            <div class="font-medium" style="color: var(--erp-text-primary)">{{ $invoice->number }}</div>
                            <div class="text-xs" style="color: var(--erp-text-muted)">
                                {{ $invoice->invoice_date?->format('d M Y') }}
                                @if ($invoice->deal) · {{ $invoice->deal->number }} @endif
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="erp-numeric" style="color: var(--erp-text-primary)">
                                {{ number_format((float) $invoice->total, $invoice->currency === 'IQD' ? 0 : 2) }}
                                {{ $invoice->currency }}
                            </div>
                            <div class="text-xs erp-numeric"
                                 style="color: {{ $invoice->isPaid() ? 'var(--erp-good)' : 'var(--erp-text-muted)' }}">
                                {{ $invoice->isPaid() ? 'settled' : $usd($invoice->outstandingBase()) . ' left' }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-10 text-center text-sm" style="color: var(--erp-text-muted)">
                        Nothing billed yet.
                    </div>
                @endforelse
            </div>
        </section>

        <section class="rounded-xl border overflow-hidden"
                 style="border-color: var(--erp-border); background: var(--erp-bg-surface)">
            <div class="border-b px-5 py-4" style="border-color: var(--erp-border)">
                <h2 class="text-sm font-semibold" style="color: var(--erp-text-primary)">Deals</h2>
            </div>
            <div>
                @forelse ($this->deals() as $deal)
                    <a href="{{ \App\Filament\Resources\Deals\DealResource::getUrl('edit', ['record' => $deal]) }}"
                       class="flex items-center justify-between border-t px-5 py-3 text-sm transition"
                       style="border-color: var(--erp-border)">
                        <div>
                            <div class="font-medium" style="color: var(--erp-text-primary)">{{ $deal->number }}</div>
                            <div class="text-xs" style="color: var(--erp-text-muted)">
                                {{ $deal->deal_date?->format('d M Y') }} · {{ $deal->lines->count() }} items
                            </div>
                        </div>
                        <span class="rounded-full px-2 py-0.5 text-xs"
                              style="background: var(--erp-bg-sunken); color: var(--erp-text-secondary)">
                            {{ \App\Models\Deal::STATUSES[$deal->status] ?? $deal->status }}
                        </span>
                    </a>
                @empty
                    <div class="px-5 py-10 text-center text-sm" style="color: var(--erp-text-muted)">
                        No deals yet.
                    </div>
                @endforelse
            </div>
        </section>
    </div>
</x-filament-panels::page>
