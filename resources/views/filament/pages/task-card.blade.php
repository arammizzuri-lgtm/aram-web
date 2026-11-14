{{-- One task's card: on the board, and bigger on Today ($focus). Needs $t and $labels. --}}
@use('App\Models\Task')
@use('App\Filament\Pages\Tasks')
@php $status = $t->status; [$ticked, $all] = $t->steps(); @endphp
<article @class(['tk-card', 'tk-card--'.$t->priority, 'tk-card--done' => $status === 'done', 'tk-card--pinned' => $t->pinned && $status !== 'done']) data-id="{{ $t->id }}" wire:key="task-{{ $t->id }}">
    @php $chips = collect($t->labels ?? [])->map(fn ($k) => $labels[$k] ?? null)->filter(); @endphp
    @if ($chips->isNotEmpty())
        <div class="tk-chips">
            @foreach ($chips as $l)
                <span class="tk-chip" style="--c: {{ Tasks::LABEL_COLOURS[$l['colour']] ?? '#9a9a9a' }}">{{ $l['name'] }}</span>
            @endforeach
        </div>
    @endif
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
        @if ($status === 'done' && ($took = $t->timeSummary()))
            <span @class(['tk-tag', 'tk-tag--over' => $t->isOverEstimate()])>Took {{ $took }}</span>
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
    @if ($t->attachments)
        <div class="tk-files">
            @foreach (array_slice($t->files(), 0, 4) as $f)
                <a href="{{ $f['url'] }}" target="_blank" rel="noopener" title="{{ $f['name'] }}" @class(['tk-file', 'tk-file--doc' => ! $f['image']])>
                    @if ($f['image'])<img src="{{ $f['url'] }}" alt="{{ $f['name'] }}" loading="lazy">@else PDF @endif
                </a>
            @endforeach
            @if (count($t->attachments) > 4)
                <button type="button" class="tk-file tk-file--doc" wire:click="mountAction('openTask', { task: {{ $t->id }} })">+{{ count($t->attachments) - 4 }}</button>
            @endif
        </div>
    @endif
    <div class="tk-moves">
        @if ($status !== 'done')
            {{-- the phone's camera, straight onto the task: shrunk in the browser first, as the form does --}}
            <label class="tk-cam" title="Add a photo" x-bind:class="{ 'is-busy': busy }" x-data="{ busy: false, async add(file) {
                if (! file) return;
                this.busy = true;
                try {
                    const img = await createImageBitmap(file), s = Math.min(1, 2000 / Math.max(img.width, img.height)), c = document.createElement('canvas');
                    c.width = Math.round(img.width * s); c.height = Math.round(img.height * s);
                    c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
                    file = new File([await new Promise((r) => c.toBlob(r, 'image/jpeg', .85))], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' });
                } catch (e) {}
                this.$wire.upload('photo', file, () => { this.$wire.attachPhoto({{ $t->id }}); this.busy = false }, () => { this.busy = false });
            } }">
                <input type="file" accept="image/*" capture="environment" aria-label="Add a photo" x-on:change="add($event.target.files[0]); $event.target.value = ''">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3z"/><circle cx="12" cy="13" r="3"/></svg>
            </label>
            <div class="tk-snooze" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
                <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="true">Snooze</button>
                <div class="tk-snooze__menu" x-show="open" x-cloak x-transition.opacity>
                    @foreach (Tasks::SNOOZES as $days => $length)
                        <button type="button" wire:click="snooze({{ $t->id }}, {{ $days }})" @click="open = false">
                            {{ $length }} <small>{{ $t->snoozed($days)->format('D j M') }}</small>
                        </button>
                    @endforeach
                </div>
            </div>
            @php $running = $t->timer_started_at !== null; @endphp
            {{-- the timer: ticks in the browser from the seconds already spent --}}
            <button type="button" wire:click="toggleTimer({{ $t->id }})" wire:key="timer-{{ $t->id }}-{{ $t->timer_started_at?->timestamp ?? 0 }}"
                    @class(['tk-timer', 'is-on' => $running, 'is-over' => $t->isOverEstimate()])
                    title="{{ $running ? 'Stop the timer' : 'Start the timer' }}" aria-label="{{ $running ? 'Stop the timer' : 'Start the timer' }}"
                    @if ($running)
                        x-data="{ t: '', i: null,
                            init() { const b = {{ $t->spent_minutes * 60 + (int) $t->timer_started_at->diffInSeconds(now()) }}, r = Date.now();
                                const tick = () => { const x = b + Math.floor((Date.now() - r) / 1000);
                                    this.t = Math.floor(x / 3600) + ':' + String(Math.floor(x % 3600 / 60)).padStart(2, '0') + ':' + String(x % 60).padStart(2, '0') };
                                tick(); this.i = setInterval(tick, 1000) },
                            destroy() { clearInterval(this.i) } }"
                    @endif>
                @if ($running)
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                    <span x-text="t">{{ Task::hm($t->spent()) }}</span>
                @else
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l11-6.5a1 1 0 0 0 0-1.72l-11-6.5A1 1 0 0 0 8 5.5z"/></svg>
                    @if ($t->spent())<span>{{ Task::hm($t->spent()) }}</span>@endif
                @endif
                @if ($t->estimate_minutes)<small>/ {{ Task::hm($t->estimate_minutes) }}</small>@endif
            </button>
        @endif
        @if ($status === 'todo' && ($focus ?? false))
            <button type="button" class="tk-done" wire:click="move({{ $t->id }}, 'done')">Done ✓</button>
        @elseif ($status === 'todo')
            <button type="button" wire:click="move({{ $t->id }}, 'doing')">Start →</button>
        @elseif ($status === 'doing')
            <button type="button" wire:click="move({{ $t->id }}, 'todo')">← To do</button>
            <button type="button" class="tk-done" wire:click="move({{ $t->id }}, 'done')">Done ✓</button>
        @else
            <button type="button" wire:click="move({{ $t->id }}, 'todo')">Reopen</button>
        @endif
    </div>
</article>
