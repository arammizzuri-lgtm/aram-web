{{-- Tasks → Set up → Phone calendar: the address to subscribe to, and how (App\Support\TaskCalendar). --}}
@php $webcal = preg_replace('#^https?://#', 'webcal://', $url); @endphp
<div x-data="{ copied: false }" style="display:grid;gap:14px;font-size:14px;">
    <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <x-filament::button tag="a" :href="$webcal" icon="heroicon-o-device-phone-mobile">Add on this iPhone</x-filament::button>
        <x-filament::button tag="a" :href="'https://calendar.google.com/calendar/r?cid='.rawurlencode($webcal)" target="_blank" rel="noopener" color="gray" icon="heroicon-o-calendar">Add to Google Calendar</x-filament::button>
    </div>

    <div>
        <p style="margin:0 0 6px;opacity:.75;">Or copy the address:</p>
        <textarea readonly rows="2" x-ref="url" onclick="this.select()" aria-label="Calendar address"
                  style="width:100%;font:12px/1.5 ui-monospace,Consolas,monospace;padding:10px;border-radius:8px;border:1px solid rgba(127,127,127,.3);background:rgba(127,127,127,.08);color:inherit;resize:none;word-break:break-all;">{{ $url }}</textarea>
        <x-filament::button color="gray" size="sm" style="margin-top:6px;"
                            x-on:click="navigator.clipboard.writeText($refs.url.value).then(() => { copied = true; setTimeout(() => copied = false, 1800) })">
            <span x-show="!copied">Copy the address</span><span x-show="copied" x-cloak>✓ Copied</span>
        </x-filament::button>
    </div>

    <ul style="margin:0;padding-inline-start:18px;list-style:disc;display:grid;gap:4px;opacity:.85;">
        <li><strong>iPhone</strong>, by hand: Settings → Calendar → Accounts → Add Account → Other → Add Subscribed Calendar, then paste.</li>
        <li><strong>Google Calendar</strong>, by hand: on a computer, Other calendars → + → From URL, then paste. It shows on an Android phone with the same Google account.</li>
        <li>Google takes some hours to pick up a change; an iPhone checks as often as set in Settings → Calendar → Accounts → Fetch New Data.</li>
    </ul>

    <p style="margin:0;font-size:12.5px;opacity:.7;">Keep the address to yourself: anyone who has it can see your task titles. If it gets out, make a new one below.</p>
</div>
