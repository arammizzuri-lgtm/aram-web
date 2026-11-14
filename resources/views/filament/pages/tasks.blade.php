{{-- Tasks (App\Filament\Pages\Tasks): the board and the timeline. Cards
     are dragged with Sortable, which the admin already ships (a long press on
     a phone, where each card also has its own buttons); a card or a bar opens
     the task. --}}
@use('App\Models\Task')
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
        .tl-left { position: absolute; top: 5px; font-size: .7rem; line-height: 16px; color: #a0a0a0; white-space: nowrap; }
        .tl-left--danger { color: #ff7a85; font-weight: 600; }

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
        <div class="tk-counts">
            <span class="tk-count"><b>{{ $counts['open'] }}</b>open</span>
            <span @class(['tk-count', 'tk-count--late' => $counts['late']])><b>{{ $counts['late'] }}</b>late</span>
            <span class="tk-count"><b>{{ $counts['today'] }}</b>due today</span>
            <span class="tk-count"><b>{{ $counts['week'] }}</b>in the next 7 days</span>
        </div>
        <div class="tk-switch" role="group" aria-label="View">
            <button type="button" wire:click="$set('show', 'board')" @class(['is-on' => $show === 'board'])>Board</button>
            <button type="button" wire:click="$set('show', 'timeline')" @class(['is-on' => $show === 'timeline'])>Timeline</button>
        </div>
    </div>

    @if ($show === 'timeline')
        <div class="tl">
            @if ($timeline['groups']->isEmpty())
                <p class="tk-empty">Nothing on the timeline yet — add a task, or start from a template.</p>
            @else
                <div class="tl-row tl-head">
                    <div class="tl-name"></div>
                    <div class="tl-track">
                        @foreach ($timeline['ticks'] as $tick)
                            <span class="tl-tick" style="left: {{ $tick['left'] }}%">{{ $tick['label'] }}</span>
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
                                <button type="button" @class(['tl-bar', 'tl-bar--'.$t->urgency(), 'tl-bar--done' => $t->status === 'done'])
                                        style="left: {{ $row['left'] }}%; width: {{ $row['width'] }}%"
                                        wire:click="{{ $open }}" aria-label="{{ $t->title }}"
                                        title="{{ $t->startsAt()->timezone(Task::TZ)->format('j M') }} → {{ $t->due_at->timezone(Task::TZ)->format('D j M, H:i') }}">
                                    @if ($all)<span class="tl-fill" style="width: {{ round($ticked / $all * 100) }}%"></span>@endif
                                </button>
                                <span @class(['tl-left', 'tl-left--danger' => $t->isLate()])
                                      style="{{ $end > 72 ? 'right: calc('.(100 - $row['left']).'% + 6px)' : 'left: calc('.$end.'% + 6px)' }}">{{ $t->timeLeft() }}</span>
                            </div>
                        </div>
                    @endforeach
                @endforeach
            @endif
        </div>
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
                            @php [$ticked, $all] = $t->steps(); @endphp
                            <article @class(['tk-card', 'tk-card--'.$t->priority, 'tk-card--done' => $status === 'done', 'tk-card--pinned' => $t->pinned && $status !== 'done']) data-id="{{ $t->id }}" wire:key="task-{{ $t->id }}">
                                <button type="button" class="tk-card__title" wire:click="mountAction('openTask', { task: {{ $t->id }} })">{{ $t->title }}</button>
                                @if ($status !== 'done')
                                    <button type="button" class="tk-pin" wire:click="togglePin({{ $t->id }})" aria-pressed="{{ $t->pinned ? 'true' : 'false' }}"
                                            title="{{ $t->pinned ? 'Unpin' : 'Pin to the top' }}" aria-label="{{ $t->pinned ? 'Unpin' : 'Pin to the top' }}">
                                        <svg viewBox="0 0 24 24" fill="{{ $t->pinned ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 17v5M9 10.8a2 2 0 0 1-1.1 1.8l-1.8.9A2 2 0 0 0 5 15.2v.8a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-.8a2 2 0 0 0-1.1-1.8l-1.8-.9a2 2 0 0 1-1.1-1.8V7a1 1 0 0 1 1-1 2 2 0 0 0 0-4H8a2 2 0 0 0 0 4 1 1 0 0 1 1 1z"/></svg>
                                    </button>
                                @endif
                                @if ($place = $t->place())
                                    <p class="tk-card__place">{{ $place }}</p>
                                @endif
                                <div class="tk-card__meta">
                                    <span class="tk-left tk-left--{{ $t->urgency() }}">{{ $t->timeLeft() }}</span>
                                    @if ($status !== 'done')
                                        <span>{{ $t->due_at->timezone(Task::TZ)->format('D j M, H:i') }}</span>
                                    @endif
                                    @if ($t->repeat)
                                        <span class="tk-tag" title="Repeats">↻ {{ Task::REPEATS[$t->repeat] }}</span>
                                    @endif
                                    @if ($t->source_type)
                                        <span class="tk-tag" title="Made by the website; it closes itself once answered">⚡ From the site</span>
                                    @endif
                                </div>
                                @if ($all)
                                    <details class="tk-steps" wire:ignore.self>
                                        <summary>
                                            <span>☑ {{ $ticked }}/{{ $all }}</span>
                                            <span class="tk-meter"><span style="width: {{ round($ticked / $all * 100) }}%"></span></span>
                                        </summary>
                                        @foreach (array_values($t->checklist) as $i => $step)
                                            <label wire:key="step-{{ $t->id }}-{{ $i }}">
                                                <input type="checkbox" @checked($step['done'] ?? false) wire:click="toggleStep({{ $t->id }}, {{ $i }})">
                                                <span @class(['is-done' => $step['done'] ?? false])>{{ $step['text'] ?? '' }}</span>
                                            </label>
                                        @endforeach
                                    </details>
                                @endif
                                <div class="tk-moves">
                                    @if ($status !== 'done')
                                        <div class="tk-snooze" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                                            <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="true">Snooze</button>
                                            <div class="tk-snooze__menu" x-show="open" x-cloak x-transition.opacity>
                                                @foreach (\App\Filament\Pages\Tasks::SNOOZES as $days => $label)
                                                    <button type="button" wire:click="snooze({{ $t->id }}, {{ $days }})" @click="open = false">
                                                        {{ $label }} <small>{{ $t->snoozed($days)->format('D j M') }}</small>
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                    @if ($status === 'todo')
                                        <button type="button" wire:click="move({{ $t->id }}, 'doing')">Start →</button>
                                    @elseif ($status === 'doing')
                                        <button type="button" wire:click="move({{ $t->id }}, 'todo')">← To do</button>
                                        <button type="button" class="tk-done" wire:click="move({{ $t->id }}, 'done')">Done ✓</button>
                                    @else
                                        <button type="button" wire:click="move({{ $t->id }}, 'todo')">Reopen</button>
                                    @endif
                                </div>
                            </article>
                        @empty
                            <p class="tk-empty">{{ $status === 'done' ? 'Done tasks stay here for two weeks.' : 'Nothing here — drag a card in.' }}</p>
                        @endforelse
                    </div>
                </section>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
