<x-filament-widgets::widget>
    <div class="aram-card">
        <div class="aram-card__head">
            <h3 class="aram-card__title">Recent enquiries</h3>
            <a href="{{ \App\Filament\Resources\ContactMessages\ContactMessageResource::getUrl() }}" class="aram-card__link">View all</a>
        </div>

        @forelse ($messages as $message)
            <a href="{{ \App\Filament\Resources\ContactMessages\ContactMessageResource::getUrl('edit', ['record' => $message]) }}" class="aram-msg">
                <span class="aram-msg__dot {{ $message->is_read ? 'is-read' : '' }}"></span>
                <span class="aram-msg__body">
                    <span class="aram-msg__name">{{ $message->name }}</span>
                    <span class="aram-msg__meta">{{ $message->project?->name ?? $message->typeLabel() ?? 'General enquiry' }} · {{ $message->status?->getLabel() }} · {{ $message->created_at?->diffForHumans() }}</span>
                </span>
            </a>
        @empty
            <p class="aram-msg__empty">No enquiries yet. Submissions from your website form will appear here.</p>
        @endforelse
    </div>
</x-filament-widgets::widget>
