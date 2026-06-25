<x-filament-widgets::widget>
    <div class="aram-hero">
        <div class="aram-hero__text">
            <p class="aram-hero__eyebrow">{{ $date }}</p>
            <h2 class="aram-hero__title">{{ $greeting }}, {{ $name }}</h2>
            <p class="aram-hero__sub">Here’s how Aram Mizuri Architecture is doing.</p>
        </div>
        <a href="{{ url('/') }}" target="_blank" rel="noopener" class="aram-hero__cta">
            View live site
            <span aria-hidden="true">↗</span>
        </a>
    </div>
</x-filament-widgets::widget>
