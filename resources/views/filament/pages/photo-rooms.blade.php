<x-filament-panels::page>
    <style>
        .pr-bar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
        .pr-tabs { display: inline-flex; gap: 6px; }
        .pr-tabs button { padding: 6px 14px; border-radius: 999px; font-size: 0.82rem; border: 1px solid rgba(255,255,255,0.15); color: #c8c8c8; }
        .pr-tabs button.is-on { border-color: #F5C518; color: #F5C518; }
        .pr-progress { min-width: 260px; font-size: 0.82rem; color: #9aa0a6; }
        .pr-progress b { color: #e6e6e6; }
        .pr-meter { height: 4px; margin-top: 6px; border-radius: 2px; background: rgba(255,255,255,0.1); overflow: hidden; }
        .pr-meter span { display: block; height: 100%; background: #F5C518; }
        .pr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 14px; }
        .pr-card { border-radius: 12px; overflow: hidden; border: 1px solid rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); }
        .pr-card img { display: block; width: 100%; aspect-ratio: 4 / 3; object-fit: cover; background: #141416; }
        .pr-name { padding: 8px 10px 4px; font-size: 0.8rem; color: #c8c8c8; display: flex; justify-content: space-between; gap: 8px; }
        .pr-name em { color: #9aa0a6; font-style: normal; }
        .pr-rooms { display: flex; flex-wrap: wrap; gap: 5px; padding: 6px 10px 12px; }
        .pr-rooms button { padding: 4px 9px; border-radius: 999px; font-size: 0.74rem; border: 1px solid rgba(255,255,255,0.14); color: #c8c8c8; }
        .pr-rooms button:hover { border-color: rgba(245,197,24,0.6); color: #F5C518; }
        .pr-rooms button.is-on { background: #F5C518; border-color: #F5C518; color: #111; font-weight: 600; }
        .pr-done { padding: 28px; text-align: center; color: #9aa0a6; border: 1px dashed rgba(255,255,255,0.15); border-radius: 12px; }
    </style>

    <div class="pr-bar">
        <div class="pr-tabs">
            <button type="button" wire:click="$set('show', 'untagged')" @class(['is-on' => $show === 'untagged'])>Not said yet</button>
            <button type="button" wire:click="$set('show', 'all')" @class(['is-on' => $show === 'all'])>All photographs</button>
        </div>
        <div class="pr-progress">
            <b>{{ $tagged }}</b> of <b>{{ $total }}</b> photographs have their room
            @if ($tagged)
                · <a href="{{ $publicUrl }}" target="_blank" rel="noopener" style="color:#F5C518">see them on the site ↗</a>
            @endif
            <div class="pr-meter"><span style="width: {{ $total ? round($tagged / $total * 100) : 0 }}%"></span></div>
        </div>
    </div>

    @if (empty($photos))
        <div class="pr-done">Every photograph has its room. New ones will wait here as they are added.</div>
    @else
        <div class="pr-grid">
            @foreach ($photos as $photo)
                <div class="pr-card" wire:key="pr-{{ $photo['project'] }}-{{ md5($photo['img']) }}">
                    <img src="{{ $photo['thumb'] }}" alt="" loading="lazy">
                    <div class="pr-name">
                        <span>{{ $photo['name'] }}</span>
                        @unless ($photo['published'])<em>not published</em>@endunless
                    </div>
                    <div class="pr-rooms">
                        @foreach ($rooms as $key => $label)
                            <button type="button" wire:click="setRoom({{ $photo['project'] }}, @js($photo['img']), '{{ $key }}')" @class(['is-on' => $photo['room'] === $key])>{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-panels::page>
