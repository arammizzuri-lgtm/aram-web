<x-filament-widgets::widget>
    <div class="aram-card">
        <div class="aram-card__head">
            <h3 class="aram-card__title">Where visitors come from</h3>
            <span class="aram-card__note">{{ $range }}</span>
        </div>

        @if ($rows)
            <table class="aram-stat-table">
                <thead>
                    <tr>
                        <th>Source</th>
                        <th class="is-num">Visitors</th>
                        <th class="is-num" title="WhatsApp buttons pressed by visitors who came from here">WA</th>
                        <th class="is-num" title="Enquiries sent by visitors who came from here">Enq.</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $r)
                        <tr>
                            <td>
                                <span class="aram-stat-table__name">{{ $r['label'] }}</span>
                                <span class="aram-bar"><span style="width: {{ round($r['visitors'] / $max * 100) }}%"></span></span>
                            </td>
                            <td class="is-num">{{ $r['visitors'] }}</td>
                            <td class="is-num">{{ $r['whatsapp'] ?: '—' }}</td>
                            <td class="is-num {{ $r['enquiries'] ? 'is-good' : '' }}">{{ $r['enquiries'] ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="aram-card__foot">WhatsApp and most phone apps open links without saying where they came from, so many of those visits count as <em>Direct / unknown</em>. A link that ends in <code>?utm_source=whatsapp</code> (or instagram…) is always counted correctly.</p>
        @else
            <p class="aram-msg__empty">No visits in this period yet.</p>
        @endif
    </div>
</x-filament-widgets::widget>
