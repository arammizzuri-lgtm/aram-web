{{-- App\Filament\Widgets\TasksDue --}}
@use('App\Models\Task')
<x-filament-widgets::widget>
    <style>
        .aram-tasks__nums { display: flex; flex-wrap: wrap; gap: .5rem; margin-top: .7rem; }
        .aram-tasks__num { display: flex; align-items: baseline; gap: .4rem; padding: .45rem .8rem; border-radius: .7rem; font-size: .8rem; color: #b8b8b8; background: rgba(255,255,255,.04); text-decoration: none; }
        .aram-tasks__num b { font-size: 1.25rem; color: #f2f2f2; font-variant-numeric: tabular-nums; }
        .aram-tasks__num--late { background: rgba(206,17,38,.14); }
        .aram-tasks__num--late b { color: #ff7a85; }
        .aram-tasks__list { margin-top: .8rem; display: grid; gap: .35rem; }
        .aram-tasks__item { display: flex; align-items: center; gap: .7rem; padding: .5rem .2rem; border-top: 1px solid rgba(255,255,255,.05); text-decoration: none; }
        .aram-tasks__title { flex: 1; min-width: 0; font-size: .86rem; color: #e2e2e2; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .aram-tasks__title small { margin-inline-start: .4rem; color: #858585; }
        .aram-tasks__item:hover .aram-tasks__title { color: #f5c518; }
        .aram-tasks__left { flex: none; padding: 2px 8px; border-radius: 999px; font-size: .72rem; font-weight: 600; background: rgba(255,255,255,.07); color: #b5b5b5; }
        .aram-tasks__left--danger { background: rgba(206,17,38,.18); color: #ff7a85; }
        .aram-tasks__left--warning { background: rgba(245,197,24,.16); color: #f5c518; }
        .aram-tasks__left--info { background: rgba(90,169,230,.15); color: #8cc6f2; }
    </style>
    <div class="aram-card">
        <div class="aram-card__head">
            <h3 class="aram-card__title">Tasks</h3>
            <a class="aram-card__link" href="{{ $url }}">Open the board →</a>
        </div>
        @if ($counts['open'] === 0)
            <p class="aram-msg__empty">No open tasks. <a class="aram-card__link" href="{{ $url }}">Add one</a></p>
        @else
            <div class="aram-tasks__nums">
                <a href="{{ $url }}" @class(['aram-tasks__num', 'aram-tasks__num--late' => $counts['late']])><b>{{ $counts['late'] }}</b>late</a>
                <a href="{{ $url }}" class="aram-tasks__num"><b>{{ $counts['today'] }}</b>due today</a>
                <a href="{{ $url }}" class="aram-tasks__num"><b>{{ $counts['week'] }}</b>in the next 7 days</a>
                <a href="{{ $url }}" class="aram-tasks__num"><b>{{ $counts['open'] }}</b>open in all</a>
            </div>
            <div class="aram-tasks__list">
                @foreach ($next as $t)
                    <a href="{{ $url }}" class="aram-tasks__item">
                        <span class="aram-tasks__title">{{ $t->title }}@if ($place = $t->place())<small>{{ $place }}</small>@endif</span>
                        <span class="aram-tasks__left aram-tasks__left--{{ $t->urgency() }}">{{ $t->timeLeft() }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
