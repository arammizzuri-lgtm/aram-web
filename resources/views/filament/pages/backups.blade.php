<x-filament-panels::page>
    <style>
        .bk__table { width: 100%; border-collapse: collapse; font-size: .875rem; }
        .bk__table th { text-align: start; font-weight: 500; color: rgba(255,255,255,.5); padding: .5rem .75rem; border-bottom: 1px solid rgba(255,255,255,.1); }
        .bk__table td { padding: .65rem .75rem; border-bottom: 1px solid rgba(255,255,255,.06); }
        .bk__table td:last-child, .bk__table th:last-child { text-align: end; }
        .bk__muted { color: rgba(255,255,255,.5); }
        .bk__steps { margin: 0; padding-inline-start: 1.25rem; line-height: 1.7; font-size: .875rem; color: rgba(255,255,255,.8); }
        .bk__steps code { font-size: .8125rem; color: #F5C518; }
    </style>

    <x-filament::section heading="Taken before each deploy"
        description="Every deploy copies the database before it changes it, and keeps the last {{ \App\Support\DatabaseBackup::KEEP }}. The ERP's database is copied the same way, into erp/storage/app/private/backups.">
        @php($snapshots = $this->getSnapshots())
        @if ($snapshots === [])
            <p class="bk__muted">None yet — the first one is taken on the next deploy. “Download a copy now” works at any time.</p>
        @else
            <table class="bk__table">
                <thead><tr><th>Taken</th><th>Size</th><th></th></tr></thead>
                <tbody>
                    @foreach ($snapshots as $s)
                        <tr>
                            <td>
                                {{ \Illuminate\Support\Carbon::createFromTimestamp($s['time'])->setTimezone(config('app.timezone'))->format('j M Y, H:i') }}
                                <span class="bk__muted">· {{ $s['label'] === 'deploy' ? 'before a deploy' : $s['label'] }}</span>
                            </td>
                            <td class="bk__muted">{{ number_format($s['size'] / 1024) }} KB</td>
                            <td>
                                <x-filament::link :href="route('filament.studio-aks-panel.backup-download', $s['name'])" icon="heroicon-o-arrow-down-tray">
                                    Download
                                </x-filament::link>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>

    <x-filament::section heading="Putting a copy back" collapsible collapsed>
        <ol class="bk__steps">
            <li>Download the copy you want to go back to.</li>
            <li>In cPanel, open <b>File Manager</b> and go to <code>aram-web/database</code>.</li>
            <li>Rename <code>database.sqlite</code> to <code>database-old.sqlite</code> — keep it until you are sure.</li>
            <li>Upload the downloaded copy there and rename it to <code>database.sqlite</code>.</li>
        </ol>
    </x-filament::section>
</x-filament-panels::page>
