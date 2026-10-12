<x-filament-widgets::widget>
    <div class="aram-card">
        <div class="aram-card__head">
            <h3 class="aram-card__title">Most viewed projects</h3>
            <span class="aram-card__note">{{ $range }}</span>
        </div>

        @if ($rows)
            <table class="aram-stat-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th class="is-num" title="Opened in the homepage popup + visits to its own page">Views</th>
                        <th class="is-num">WhatsApp</th>
                        <th class="is-num">Enquiries</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>
                                <a class="aram-stat-table__name" href="{{ \App\Filament\Resources\Projects\ProjectResource::getUrl('edit', ['record' => $r['project']]) }}">{{ $r['project']->name }}</a>
                                <span class="aram-bar"><span style="width: {{ round($r['views'] / $max * 100) }}%"></span></span>
                                <span class="aram-stat-table__sub">{{ $r['opens'] }} in the popup · {{ $r['pages'] }} on its page</span>
                            </td>
                            <td class="is-num">{{ $r['views'] }}</td>
                            <td class="is-num">{{ $r['whatsapp'] ?: '—' }}</td>
                            <td class="is-num {{ $r['enquiries'] ? 'is-good' : '' }}">{{ $r['enquiries'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <p class="aram-msg__empty">No project has been opened in this period yet. Openings in the homepage popup and visits to project pages are counted from now on.</p>
        @endif
    </div>
</x-filament-widgets::widget>
