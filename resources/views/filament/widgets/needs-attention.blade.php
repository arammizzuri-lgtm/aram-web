<x-filament-widgets::widget>
    <div class="aram-card aram-todo">
        <div class="aram-card__head">
            <h3 class="aram-card__title">Needs attention</h3>
        </div>

        @if ($items->isEmpty())
            <p class="aram-msg__empty">All caught up — nothing is waiting.</p>
        @else
            <div class="aram-todo__grid">
                @foreach ($items as $item)
                    <a href="{{ $item['url'] }}" class="aram-todo__item">
                        <span class="aram-todo__num">{{ $item['count'] }}</span>
                        <span class="aram-todo__label">{{ $item['label'] }}</span>
                        <span class="aram-todo__go" aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
