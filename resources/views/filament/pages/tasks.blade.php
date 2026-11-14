{{-- Tasks (App\Filament\Pages\Tasks): the board and the timeline. Cards
     are dragged with Sortable, which the admin already ships (a long press on
     a phone, where each card also has its own buttons); a card or a bar opens
     the task. --}}
@use('App\Models\Task')
@use('App\Filament\Pages\Tasks')
<x-filament-panels::page>
    <style>
        .tk-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .tk-counts { display: flex; flex-wrap: wrap; gap: 8px; }
        .tk-count { padding: 6px 12px; border-radius: 10px; font-size: .82rem; color: #b8b8b8; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.07); }
        .tk-count b { margin-inline-end: 4px; font-size: 1.05rem; color: #f2f2f2; font-variant-numeric: tabular-nums; }
        .tk-count--late { border-color: rgba(206,17,38,.55); background: rgba(206,17,38,.12); }
        .tk-count--late b { color: #ff7a85; }
        .tk-switch { display: inline-flex; padding: 3px; border-radius: 999px; background: rgba(255,255,255,.05); }
        .tk-switch button { padding: 6px 16px; border-radius: 999px; font-size: .82rem; color: #b8b8b8; }
        .tk-switch button.is-on { background: #F5C518; color: #111; font-weight: 600; }

        .tk-chip { --c: #9a9a9a; display: inline-flex; align-items: center; gap: 6px; padding: 1px 8px; border-radius: 999px; font-size: .68rem; font-weight: 600; color: var(--c); background: color-mix(in srgb, var(--c) 16%, transparent); }
        .tk-chips { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 6px; padding-inline-end: 26px; }
        .tk-quick { position: relative; }
        .tk-quick > svg { position: absolute; top: 12px; inset-inline-start: 14px; width: 18px; height: 18px; color: #F5C518; pointer-events: none; }
        .tk-quick input { width: 100%; padding: 10px 14px; padding-inline-start: 42px; border-radius: 12px; font-size: .9rem; color: #f0f0f0; background: rgba(255,255,255,.04); border: 1px solid rgba(255,255,255,.1); }
        .tk-quick input:focus { outline: none; border-color: rgba(245,197,24,.6); background: rgba(255,255,255,.06); }
        .tk-quick input::placeholder { color: #777; }
        .tk-quick__preview, .tk-quick__help { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin: 7px 4px 0; font-size: .76rem; color: #8d8d8d; }
        .tk-quick__preview b { font-size: .84rem; font-weight: 600; color: #f0f0f0; }
        .tk-quick__preview small { margin-inline-start: auto; color: #6f6f6f; }
        .tk-filter { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; }
        .tk-filter button { padding: 5px 12px; border-radius: 999px; font-size: .78rem; }
        .tk-filter .tk-chip span { font-weight: 500; opacity: .7; font-variant-numeric: tabular-nums; }
        .tk-filter .tk-chip.is-on { background: var(--c); color: #111; }
        .tk-filter__all { color: #b8b8b8; background: rgba(255,255,255,.05); }
        .tk-filter__all.is-on { background: #e8e8e8; color: #111; font-weight: 600; }
        .tk-filter .tk-filter__edit { margin-inline-start: auto; color: #8d8d8d; font-size: .74rem; }
        .tk-filter .tk-filter__edit:hover { color: #F5C518; }

        .tk-board { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; align-items: start; }
        .tk-col { border-radius: 14px; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.06); padding: 10px; }
        .tk-col__head { display: flex; justify-content: space-between; padding: 4px 6px 10px; font-size: .78rem; letter-spacing: .08em; text-transform: uppercase; color: #9a9a9a; }
        .tk-col__head b { color: #e8e8e8; }
        .tk-list { display: grid; gap: 8px; min-height: 90px; align-content: start; }
        .tk-empty { padding: 18px 8px; text-align: center; font-size: .8rem; color: #6f6f6f; border: 1px dashed rgba(255,255,255,.1); border-radius: 10px; }
        .tk-card { position: relative; padding: 11px 12px 10px 14px; border-radius: 11px; background: #18181b; border: 1px solid rgba(255,255,255,.08); cursor: grab; }
        .tk-card::before { content: ''; position: absolute; inset: 10px auto 10px 0; width: 3px; border-radius: 0 3px 3px 0; background: transparent; }
        .tk-card--high::before { background: #CE1126; }
        .tk-card--low::before { background: #5aa9e6; }
        .tk-card.sortable-ghost { opacity: .35; }
        .tk-card--done { opacity: .62; }
        .tk-card--pinned { border-color: rgba(245,197,24,.4); }
        .tk-pin { position: absolute; top: 8px; inset-inline-end: 8px; display: grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; color: #5f5f5f; }
        .tk-pin svg { width: 15px; height: 15px; }
        .tk-pin:hover, .tk-card--pinned .tk-pin { color: #F5C518; }
        .tk-card__title { display: block; width: 100%; padding-inline-end: 26px; text-align: start; font-size: .9rem; line-height: 1.35; font-weight: 500; color: #f0f0f0; }
        .tk-card__title:hover { color: #F5C518; }
        .tk-card--done .tk-card__title { text-decoration: line-through; text-decoration-color: rgba(255,255,255,.35); }
        .tk-card__place { margin-top: 3px; font-size: .74rem; color: #8d8d8d; }
        .tk-card__meta { display: flex; flex-wrap: wrap; align-items: center; gap: 6px; margin-top: 8px; font-size: .72rem; color: #8d8d8d; }
        .tk-left { padding: 2px 8px; border-radius: 999px; font-weight: 600; font-size: .72rem; }
        .tk-left--danger { background: rgba(206,17,38,.18); color: #ff7a85; }
        .tk-left--warning { background: rgba(245,197,24,.16); color: #F5C518; }
        .tk-left--info { background: rgba(90,169,230,.15); color: #8cc6f2; }
        .tk-left--gray { background: rgba(255,255,255,.07); color: #b5b5b5; }
        .tk-tag { padding: 2px 7px; border-radius: 999px; background: rgba(255,255,255,.05); }
        .tk-steps { margin-top: 8px; font-size: .78rem; color: #c8c8c8; }
        .tk-steps summary { display: flex; align-items: center; gap: 8px; cursor: pointer; list-style: none; color: #9a9a9a; }
        .tk-steps summary::-webkit-details-marker { display: none; }
        .tk-meter { flex: 1; height: 4px; border-radius: 2px; background: rgba(255,255,255,.08); overflow: hidden; }
        .tk-meter span { display: block; height: 100%; background: #2e9e63; }
        .tk-steps label { display: flex; gap: 8px; align-items: flex-start; padding: 4px 0; cursor: pointer; }
        .tk-steps input { margin-top: 2px; accent-color: #2e9e63; }
        .tk-steps .is-done { color: #7d7d7d; text-decoration: line-through; }
        .tk-moves { display: flex; justify-content: flex-end; gap: 6px; margin-top: 8px; }
        .tk-moves button { padding: 3px 10px; border-radius: 999px; font-size: .72rem; color: #c8c8c8; border: 1px solid rgba(255,255,255,.12); }
        .tk-moves button:hover { border-color: #F5C518; color: #F5C518; }
        .tk-moves .tk-done { border-color: rgba(46,158,99,.6); color: #6fd39b; }
        .tk-moves .tk-timer { display: inline-flex; align-items: center; gap: 5px; color: #b8b8b8; font-variant-numeric: tabular-nums; }
        .tk-timer svg { width: 10px; height: 10px; }
        .tk-timer small { color: #7d7d7d; font-size: .7rem; }
        .tk-moves .tk-timer.is-on { border-color: rgba(46,158,99,.7); background: rgba(46,158,99,.14); color: #6fd39b; }
        .tk-moves .tk-timer.is-over, .tk-moves .tk-timer.is-over small { color: #ff7a85; }
        .tk-tag--over { color: #ff7a85; }
        .tk-count--timing { display: inline-flex; align-items: center; gap: 6px; border-color: rgba(46,158,99,.55); background: rgba(46,158,99,.1); }
        .tk-count--timing i { width: 8px; height: 8px; border-radius: 50%; background: #3fbf7f; animation: tk-blink 1.4s ease-in-out infinite; }
        .tk-count--timing b { font-size: .82rem; margin: 0; }
        .tk-count--timing span { color: #6fd39b; font-weight: 600; }
        @keyframes tk-blink { 50% { opacity: .25; } }
        @media (prefers-reduced-motion: reduce) { .tk-count--timing i { animation: none; } }
        .tk-snooze { position: relative; margin-inline-end: auto; }
        .tk-moves .tk-snooze > button { border-color: transparent; color: #8d8d8d; padding-inline: 4px; }
        .tk-snooze__menu { position: absolute; z-index: 20; bottom: calc(100% + 6px); inset-inline-start: 0; display: grid; min-width: 170px; padding: 5px; border-radius: 10px; background: #232327; border: 1px solid rgba(255,255,255,.12); box-shadow: 0 10px 30px rgba(0,0,0,.45); }
        .tk-moves .tk-snooze__menu button { display: flex; justify-content: space-between; gap: 12px; border: 0; border-radius: 7px; padding: 7px 9px; font-size: .78rem; text-align: start; }
        .tk-moves .tk-snooze__menu button:hover { background: rgba(255,255,255,.06); }
        .tk-snooze__menu small { color: #8d8d8d; }

        .tl { border-radius: 14px; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.06); padding: 10px 14px 16px; }
        .tl-row { display: grid; grid-template-columns: minmax(0, 230px) minmax(0, 1fr); gap: 12px; align-items: center; min-height: 34px; }
        .tl-head { position: sticky; top: 0; }
        .tl-track { position: relative; height: 26px; }
        .tl-head .tl-track { height: 22px; border-bottom: 1px solid rgba(255,255,255,.08); }
        .tl-tick { position: absolute; top: 2px; font-size: .68rem; color: #7a7a7a; white-space: nowrap; transform: translateX(-50%); }
        .tl-tick:first-child { transform: none; }
        .tl-now { position: absolute; top: -4px; bottom: -4px; width: 2px; background: rgba(245,197,24,.55); }
        .tl-head .tl-now::after { content: 'Today'; position: absolute; top: 0; left: 5px; font-size: .68rem; font-weight: 600; color: #F5C518; }
        .tl-group { margin: 14px 0 4px; font-size: .74rem; letter-spacing: .08em; text-transform: uppercase; color: #9a9a9a; }
        .tl-name { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; text-align: start; font-size: .82rem; color: #dcdcdc; }
        .tl-name:hover { color: #F5C518; }
        .tl-bar { position: absolute; top: 5px; height: 16px; border-radius: 6px; overflow: hidden; background: rgba(255,255,255,.18); }
        .tl-bar--danger { background: rgba(206,17,38,.7); }
        .tl-bar--warning { background: rgba(245,197,24,.75); }
        .tl-bar--info { background: rgba(90,169,230,.65); }
        .tl-bar--done { background: rgba(46,158,99,.45); }
        .tl-fill { position: absolute; inset: 0 auto 0 0; background: rgba(255,255,255,.28); }
        .tl-bar.is-draggable { cursor: grab; touch-action: pan-y; }
        .tl-bar.is-draggable:active { cursor: grabbing; }
        .tl-end { position: absolute; inset: 0 0 0 auto; width: 8px; max-width: 40%; cursor: ew-resize; }
        .tl-bar:hover .tl-end { background: rgba(255,255,255,.35); }
        .tl-left { position: absolute; top: 5px; font-size: .7rem; line-height: 16px; color: #a0a0a0; white-space: nowrap; }
        .tl-left--danger { color: #ff7a85; font-weight: 600; }

        /* Today: one calm column, big enough for a thumb on site */
        .tk-focus { display: grid; gap: 12px; width: 100%; max-width: 640px; margin-inline: auto; }
        .tk-focus__head h2 { font-size: 1.35rem; font-weight: 600; color: #f2f2f2; }
        .tk-focus__head p { margin-top: 2px; font-size: .85rem; color: #8d8d8d; }
        .tk-focus .tk-card { padding: 16px 18px 14px 20px; cursor: default; }
        .tk-focus .tk-card__title { font-size: 1.15rem; }
        .tk-focus .tk-card__meta, .tk-focus .tk-steps { font-size: .85rem; }
        .tk-focus .tk-steps label { padding: 7px 0; }
        .tk-focus .tk-moves { margin-top: 12px; }
        .tk-focus .tk-moves button { padding: 9px 16px; font-size: .85rem; }

        /* the busy meter: up to half a day (or week), up to full, more than fits */
        .busy-1 { --b: #9c8a3c; } .busy-2 { --b: #F5C518; } .busy-3 { --b: #ff7a85; }
        .tk-count--busy { border-color: rgba(206,17,38,.55); background: rgba(206,17,38,.12); }
        .tk-count--busy b { font-size: .82rem; color: #ff7a85; }
        .tk-count--busy:hover { border-color: #ff7a85; }
        .tl-heat { position: absolute; bottom: -2px; height: 4px; border-radius: 2px; background: var(--b); }
        .cal-day__h { float: inline-end; font-size: .7rem; font-weight: 600; color: var(--b); }
        .cal-busy { display: block; height: 3px; border-radius: 2px; background: rgba(255,255,255,.06); overflow: hidden; }
        .cal-busy i, .cal-week__bar i { display: block; height: 100%; background: var(--b); }
        .cal-week { grid-column: 1 / -1; display: flex; align-items: center; gap: 10px; padding: 0 6px 6px; font-size: .72rem; color: #8d8d8d; }
        .cal-week__bar { flex: 0 0 120px; height: 4px; border-radius: 2px; background: rgba(255,255,255,.08); overflow: hidden; }
        .cal-week.busy-3 { color: #ff7a85; font-weight: 600; }

        .cal { border-radius: 14px; background: rgba(255,255,255,.025); border: 1px solid rgba(255,255,255,.06); padding: 12px; }
        .cal-head { display: flex; align-items: center; gap: 8px; margin-bottom: 10px; }
        .cal-head h2 { min-width: 9.5em; text-align: center; font-size: 1rem; font-weight: 600; color: #f0f0f0; }
        .cal-head button { display: grid; place-items: center; min-width: 32px; height: 32px; padding: 0 10px; border-radius: 9px; font-size: 1.1rem; color: #c8c8c8; background: rgba(255,255,255,.05); }
        .cal-head button:hover { color: #F5C518; }
        .cal-head .cal-head__today { margin-inline-start: auto; font-size: .78rem; }
        .cal-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
        .cal-wd { padding: 2px 6px; font-size: .7rem; letter-spacing: .08em; text-transform: uppercase; color: #7a7a7a; }
        .cal-day { min-height: 104px; padding: 6px; border-radius: 9px; background: rgba(255,255,255,.03); display: flex; flex-direction: column; gap: 4px; }
        .cal-day.is-out { opacity: .45; }
        .cal-day.is-today { box-shadow: inset 0 0 0 1px rgba(245,197,24,.7); }
        .cal-day__n { font-size: .78rem; color: #a8a8a8; font-variant-numeric: tabular-nums; }
        .cal-day__n em { margin-inline-start: 6px; font-style: normal; font-size: .68rem; font-weight: 600; color: #F5C518; }
        .cal-day__wd, .cal-day__m { display: none; }
        .cal-list { flex: 1; display: grid; gap: 3px; align-content: start; min-height: 30px; }
        .cal-task { display: block; width: 100%; overflow: hidden; padding: 3px 6px; border-radius: 6px; text-align: start; white-space: nowrap; text-overflow: ellipsis; font-size: .72rem; line-height: 1.35; color: #e2e2e2; background: rgba(255,255,255,.07); border-inline-start: 3px solid rgba(255,255,255,.25); cursor: grab; }
        .cal-task b { font-weight: 600; color: #9a9a9a; }
        .cal-task:hover { color: #F5C518; }
        .cal-task--danger { border-inline-start-color: #CE1126; background: rgba(206,17,38,.14); }
        .cal-task--warning { border-inline-start-color: #F5C518; }
        .cal-task--info { border-inline-start-color: #5aa9e6; }
        .cal-task.is-done { opacity: .5; text-decoration: line-through; border-inline-start-color: #2e9e63; }
        .cal-task.sortable-ghost { opacity: .35; }

        @media (max-width: 700px) {
            /* a phone: the month as a list of the days that have tasks */
            .cal-grid { grid-template-columns: minmax(0, 1fr); }
            .cal-wd, .cal-day.is-out, .cal-day.is-empty:not(.is-today) { display: none; }
            .cal-day { min-height: 0; }
            .cal-day__wd, .cal-day__m { display: inline; }
            .cal-task { white-space: normal; font-size: .8rem; padding: 6px 8px; }
            .cal-head h2 { min-width: 0; flex: 1; }
        }

        @media (max-width: 900px) {
            .tk-board { grid-template-columns: minmax(0, 1fr); }
            .tl-row { grid-template-columns: minmax(0, 1fr); gap: 2px; }
            .tl-head .tl-name { display: none; }
            /* narrow: every second date, under the "Today" word */
            .tl-head .tl-track { height: 36px; }
            .tl-tick { top: 18px; }
            .tl-tick:nth-child(even) { display: none; }
            .tl-row + .tl-row { margin-top: 6px; }
        }
    </style>

    <div class="tk-bar">
        {{-- Today is calm: no counts, no labels, no help — only today's cards --}}
        @unless ($show === 'today')
        <div class="tk-counts">
            <span class="tk-count"><b>{{ $counts['open'] }}</b>open</span>
            <span @class(['tk-count', 'tk-count--late' => $counts['late']])><b>{{ $counts['late'] }}</b>late</span>
            <span class="tk-count"><b>{{ $counts['today'] }}</b>due today</span>
            <span class="tk-count"><b>{{ $counts['week'] }}</b>in the next 7 days</span>
            @if ($timing)
                <button type="button" class="tk-count tk-count--timing" wire:click="toggleTimer({{ $timing->id }})" title="Stop the timer">
                    <i aria-hidden="true"></i>Timing <b>{{ Str::limit($timing->title, 40) }}</b><span>Stop</span>
                </button>
            @endif
            @if ($busyWeek)
                <a class="tk-count tk-count--busy" href="{{ Tasks::getUrl(['show' => 'calendar', 'month' => $busyWeek['from']->format('Y-m')]) }}">
                    <b>Busy week</b> {{ $busyWeek['from']->format('j M') }} – {{ $busyWeek['to']->format('j M') }}: {{ Task::hm($busyWeek['minutes']) }} of work, more than a {{ Task::hm(Tasks::WEEK_MINUTES) }} week
                </a>
            @endif
        </div>
        @endunless
        <div class="tk-switch" role="group" aria-label="View">
            <button type="button" wire:click="$set('show', 'today')" @class(['is-on' => $show === 'today'])>Today</button>
            <button type="button" wire:click="$set('show', 'board')" @class(['is-on' => $show === 'board'])>Board</button>
            <button type="button" wire:click="$set('show', 'timeline')" @class(['is-on' => $show === 'timeline'])>Timeline</button>
            <button type="button" wire:click="$set('show', 'calendar')" @class(['is-on' => $show === 'calendar'])>Calendar</button>
        </div>
    </div>

    <form class="tk-quick" wire:submit="quickAdd($el.querySelector('input').value)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
        <input type="text" wire:model.live.debounce.400ms="quick" maxlength="300" autocomplete="off" enterkeyhint="done"
               aria-label="Quick add a task" aria-describedby="tk-quick-help"
               placeholder="Quick add — e.g. Call Dara tomorrow 4pm #client !high @Afrin">
        @if ($preview)
            <p class="tk-quick__preview" aria-live="polite">
                <b>{{ $preview['title'] ?: 'What to do?' }}</b>
                <span class="tk-left tk-left--gray">Due {{ $preview['due_at']->format('D j M, H:i') }}</span>
                @foreach ($preview['labels'] as $key)
                    <span class="tk-chip" style="--c: {{ Tasks::LABEL_COLOURS[$labels[$key]['colour'] ?? ''] ?? '#9a9a9a' }}">{{ $labels[$key]['name'] ?? $key }}</span>
                @endforeach
                @if ($preview['priority'] !== 'normal')
                    <span class="tk-tag">{{ Task::PRIORITIES[$preview['priority']] }} priority</span>
                @endif
                @if ($preview['place'])
                    <span class="tk-tag">{{ $preview['place'] }}</span>
                @endif
                <small>Enter to add</small>
            </p>
        @elseif ($show !== 'today')
            <p class="tk-quick__help" id="tk-quick-help">Words for the day and time — today, tonight, tomorrow, fri, in 2 weeks, 5/12, 3 Jan, 4pm, 16:30 — and #label, !high or !low, @client or @project.</p>
        @endif
    </form>

    @if ($labels->isNotEmpty() && $show !== 'today')
        <div class="tk-filter" role="group" aria-label="Show only one label">
            <button type="button" wire:click="$set('label', '')" @class(['tk-filter__all', 'is-on' => $label === ''])>All</button>
            @foreach ($labels as $key => $l)
                <button type="button" wire:click="$set('label', @js($label === $key ? '' : (string) $key))" aria-pressed="{{ $label === $key ? 'true' : 'false' }}"
                        @class(['tk-chip', 'is-on' => $label === $key]) style="--c: {{ Tasks::LABEL_COLOURS[$l['colour']] ?? '#9a9a9a' }}">
                    {{ $l['name'] }}<span>{{ $labelCounts[$key] ?? 0 }}</span>
                </button>
            @endforeach
            <button type="button" class="tk-filter__edit" wire:click="mountAction('labels')">Edit labels</button>
        </div>
    @endif

    @if ($show === 'timeline')
        {{-- drag a bar to move the task, its start with it, or its end to change only when it is due; in whole days --}}
        <div class="tl" x-data="{
            moved: false,
            grab(e, endOnly) {
                if (e.button !== 0) return;
                const bar = e.target.closest('.tl-bar'), w = bar.parentElement.getBoundingClientRect().width, x0 = e.clientX;
                const left = parseFloat(bar.style.left), width = parseFloat(bar.style.width), days = {{ $timeline['days'] ?? 1 }};
                const said = bar.parentElement.querySelector('.tl-left'), was = said.textContent;
                let n = 0;
                this.moved = false;
                bar.setPointerCapture(e.pointerId);
                const move = (ev) => {
                    this.moved ||= Math.abs(ev.clientX - x0) > 4;
                    if (! this.moved) return;
                    n = Math.round((ev.clientX - x0) / w * days);
                    const d = n / days * 100;
                    endOnly ? bar.style.width = Math.max(1.2, width + d) + '%' : bar.style.left = (left + d) + '%';
                    const due = new Date(bar.dataset.due + 'T12:00');
                    due.setDate(due.getDate() + n);
                    said.textContent = (endOnly ? 'Due ' : 'To ') + due.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
                };
                const up = () => {
                    bar.removeEventListener('pointermove', move);
                    bar.removeEventListener('pointerup', up);
                    bar.removeEventListener('pointercancel', up);
                    if (n) { this.$wire.shiftOnTimeline(+bar.dataset.id, n, endOnly) } else { bar.style.left = left + '%'; bar.style.width = width + '%'; said.textContent = was }
                };
                bar.addEventListener('pointermove', move);
                bar.addEventListener('pointerup', up);
                bar.addEventListener('pointercancel', up);
            },
        }">
            @if ($timeline['groups']->isEmpty())
                <p class="tk-empty">Nothing on the timeline yet — add a task, or start from a template.</p>
            @else
                <div class="tl-row tl-head">
                    <div class="tl-name"></div>
                    <div class="tl-track">
                        @foreach ($timeline['ticks'] as $tick)
                            <span class="tl-tick" style="left: {{ $tick['left'] }}%">{{ $tick['label'] }}</span>
                        @endforeach
                        @foreach ($timeline['heat'] as $h)
                            <span class="tl-heat busy-{{ $h['level'] }}" style="left: {{ $h['left'] }}%; width: {{ $h['width'] }}%" title="{{ $h['title'] }}"></span>
                        @endforeach
                        <span class="tl-now" style="left: {{ $timeline['today'] }}%"></span>
                    </div>
                </div>
                @foreach ($timeline['groups'] as $group => $rows)
                    <p class="tl-group">{{ $group }}</p>
                    @foreach ($rows as $row)
                        @php
                            $t = $row['task'];
                            [$ticked, $all] = $t->steps();
                            $end = $row['left'] + $row['width'];
                            $open = "mountAction('openTask', { task: {$t->id} })";
                        @endphp
                        <div class="tl-row" wire:key="tl-{{ $t->id }}">
                            <button type="button" class="tl-name" wire:click="{{ $open }}" title="{{ $t->title }}">{{ $t->title }}</button>
                            <div class="tl-track">
                                <span class="tl-now" style="left: {{ $timeline['today'] }}%"></span>
                                @if ($t->status === 'done')
                                    <button type="button" class="tl-bar tl-bar--done" style="left: {{ $row['left'] }}%; width: {{ $row['width'] }}%"
                                            wire:click="{{ $open }}" aria-label="{{ $t->title }}"
                                            title="{{ $t->startsAt()->timezone(Task::TZ)->format('j M') }} → {{ $t->due_at->copy()->timezone(Task::TZ)->format('D j M, H:i') }}">
                                        @if ($all)<span class="tl-fill" style="width: {{ round($ticked / $all * 100) }}%"></span>@endif
                                    </button>
                                @else
                                    <button type="button" class="tl-bar tl-bar--{{ $t->urgency() }} is-draggable" style="left: {{ $row['left'] }}%; width: {{ $row['width'] }}%"
                                            data-id="{{ $t->id }}" data-due="{{ $t->due_at->copy()->timezone(Task::TZ)->toDateString() }}"
                                            x-on:pointerdown="grab($event, false)" x-on:click="moved ? moved = false : $wire.{{ $open }}" aria-label="{{ $t->title }}"
                                            title="{{ $t->startsAt()->timezone(Task::TZ)->format('j M') }} → {{ $t->due_at->copy()->timezone(Task::TZ)->format('D j M, H:i') }} · drag to move, or drag its end">
                                        @if ($all)<span class="tl-fill" style="width: {{ round($ticked / $all * 100) }}%"></span>@endif
                                        <span class="tl-end" x-on:pointerdown.stop="grab($event, true)" aria-hidden="true"></span>
                                    </button>
                                @endif
                                <span @class(['tl-left', 'tl-left--danger' => $t->isLate()])
                                      style="{{ $end > 72 ? 'right: calc('.(100 - $row['left']).'% + 6px)' : 'left: calc('.$end.'% + 6px)' }}">{{ $t->timeLeft() }}</span>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            @endif
        </div>
    @elseif ($show === 'calendar')
        {{-- a month: drag a task to another day to move it there, at the same time --}}
        <div class="cal">
            <div class="cal-head">
                <button type="button" wire:click="$set('month', @js($calendar['prev']))" aria-label="Previous month">‹</button>
                <h2>{{ $calendar['month']->format('F Y') }}</h2>
                <button type="button" wire:click="$set('month', @js($calendar['next']))" aria-label="Next month">›</button>
                <button type="button" class="cal-head__today" wire:click="$set('month', '')">This month</button>
            </div>
            <div class="cal-grid">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $wd)
                    <div class="cal-wd">{{ $wd }}</div>
                @endforeach
                @foreach ($calendar['days'] as $day)
                    <div @class(['cal-day', 'is-out' => ! $day['in'], 'is-today' => $day['today'], 'is-empty' => $day['tasks']->isEmpty()]) wire:key="day-{{ $day['key'] }}">
                        @php $dayLevel = $day['busy'] ? 'busy-'.Tasks::busyLevel($day['busy'], Tasks::DAY_MINUTES) : '' @endphp
                        <p class="cal-day__n"><span class="cal-day__wd">{{ $day['date']->format('D') }}</span> {{ $day['date']->format('j') }}<span class="cal-day__m"> {{ $day['date']->format('M') }}</span>@if ($day['today'])<em>Today</em>@endif
                            @if ($day['busy'])<b class="cal-day__h {{ $dayLevel }}" title="{{ Task::hm($day['busy']) }} of work; a full day is {{ Task::hm(Tasks::DAY_MINUTES) }}">{{ Task::hm($day['busy']) }}</b>@endif</p>
                        @if ($day['busy'])
                            <span class="cal-busy {{ $dayLevel }}" aria-hidden="true"><i style="width: {{ min(100, round($day['busy'] / Tasks::DAY_MINUTES * 100)) }}%"></i></span>
                        @endif
                        <div class="cal-list" data-day="{{ $day['key'] }}"
                             x-data x-init="Sortable.create($el, {
                                 group: 'cal', sort: false, animation: 150, delay: 220, delayOnTouchOnly: true, draggable: '.cal-task',
                                 onAdd: (e) => { e.item.remove(); $wire.moveToDay(+e.item.dataset.id, e.to.dataset.day) },
                             })">
                            @foreach ($day['tasks'] as $t)
                                <button type="button" data-id="{{ $t->id }}" wire:key="cal-{{ $t->id }}"
                                        @class(['cal-task', 'cal-task--'.$t->urgency(), 'is-done' => $t->status === 'done'])
                                        wire:click="mountAction('openTask', { task: {{ $t->id }} })"
                                        title="{{ $t->title }} — {{ $t->timeLeft() }}{{ ($place = $t->place()) ? ' · '.$place : '' }}">
                                    <b>{{ $t->due_at->copy()->timezone(Task::TZ)->format('H:i') }}</b> {{ $t->title }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    @if ($loop->iteration % 7 === 0 && ($weekBusy = $calendar['weeks'][intdiv($loop->index, 7)]))
                        @php $weekLevel = Tasks::busyLevel($weekBusy, Tasks::WEEK_MINUTES) @endphp
                        <div class="cal-week busy-{{ $weekLevel }}">
                            <span class="cal-week__bar" aria-hidden="true"><i style="width: {{ min(100, round($weekBusy / Tasks::WEEK_MINUTES * 100)) }}%"></i></span>
                            Week of {{ $calendar['days'][$loop->index - 6]['date']->format('j M') }}: {{ Task::hm($weekBusy) }} of work{{ $weekLevel === 3 ? ', more than a '.Task::hm(Tasks::WEEK_MINUTES).' week' : ' of '.Task::hm(Tasks::WEEK_MINUTES) }}
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @elseif ($show === 'today')
        <section class="tk-focus">
            <header class="tk-focus__head">
                <h2>{{ now(Task::TZ)->format('l j F') }}</h2>
                <p>{{ $focus['tasks']->count() ?: 'Nothing' }} left for today{{ $focus['done'] ? ' · '.$focus['done'].' done' : '' }}</p>
            </header>
            @forelse ($focus['tasks'] as $t)
                @include('filament.pages.task-card', ['focus' => true])
            @empty
                <p class="tk-empty">All clear. Enjoy the rest of the day.</p>
            @endforelse
        </section>
    @else
        <div class="tk-board">
            @foreach ($columns as $status => $tasks)
                <section class="tk-col">
                    <header class="tk-col__head">
                        <span>{{ Task::STATUSES[$status] }}</span>
                        <b>{{ $tasks->count() }}</b>
                    </header>
                    <div class="tk-list" data-status="{{ $status }}" wire:key="list-{{ $status }}"
                         x-data x-init="Sortable.create($el, {
                             group: 'tasks', sort: false, animation: 150, delay: 220, delayOnTouchOnly: true,
                             draggable: '.tk-card', filter: 'button, input, label, summary', preventOnFilter: false,
                             onAdd: (e) => { e.item.remove(); $wire.move(+e.item.dataset.id, e.to.dataset.status) },
                         })">
                        @forelse ($tasks as $t)
                            @include('filament.pages.task-card')
                        @empty
                            <p class="tk-empty">{{ $status === 'done' ? 'Done tasks stay here for two weeks.' : 'Nothing here — drag a card in.' }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
