{{-- The morning e-mail (App\Mail\MorningTasksMail). Inline styles and tables
     only: mail apps ignore stylesheets and most layout CSS. --}}
@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\Task> $late */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Task> $today */
    $hours = $late->concat($today)->sum(fn ($t) => $t->remaining());
    $h2 = 'margin:26px 0 8px;font-size:12px;letter-spacing:2px;text-transform:uppercase;';
    $row = 'padding:10px 0;border-bottom:1px solid #eee;font-size:14px;';
    $groups = [['Late', $late, '#c0392b'], ['Due today', $today, '#a07d00']];
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:24px 12px;background:#f3f1ec;font:15px/1.6 -apple-system,Segoe UI,Arial,sans-serif;color:#1d1d1d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;">
    <tr><td style="background:#0b0b0b;padding:22px 28px;">
        <div style="color:#fff;font-size:13px;letter-spacing:4px;font-weight:600;">ARAM MIZURI</div>
        <div style="color:#F5C518;font-size:11px;letter-spacing:3px;margin-top:4px;">GOOD MORNING</div>
    </td></tr>
    <tr><td style="padding:28px;">
        <h1 style="margin:0 0 4px;font-size:22px;font-weight:600;">{{ now(\App\Models\Task::TZ)->format('l j F') }}</h1>
        <p style="margin:0;color:#777;font-size:13px;">
            {{ $today->count() }} due today{{ $late->count() ? ', '.$late->count().' late' : '' }}{{ $hours ? ' · about '.\App\Models\Task::hm($hours).' of work' : '' }}.
        </p>

        @foreach ($groups as [$label, $list, $color])
            @if ($list->isNotEmpty())
                <div style="{{ $h2 }}color:{{ $color }};">{{ $label }}</div>
                @foreach ($list as $t)
                    <div style="{{ $row }}">
                        <strong>{{ $t->pinned ? '📌 ' : '' }}{{ $t->title }}</strong>
                        <span style="float:right;color:{{ $color }};font-size:13px;">{{ $t->isLate() ? $t->timeLeft() : 'at '.$t->due_at->copy()->timezone(\App\Models\Task::TZ)->format('H:i') }}</span>
                        @php $about = array_filter([$t->place(), $t->remaining() ? \App\Models\Task::hm($t->remaining()).' left to do' : null]); @endphp
                        @if ($about)
                            <div style="color:#777;font-size:13px;">{{ implode(' · ', $about) }}</div>
                        @endif
                    </div>
                @endforeach
            @endif
        @endforeach

        <div style="margin-top:28px;">
            <a href="{{ \App\Filament\Pages\Tasks::getUrl(['show' => 'today'], panel: 'studio-aks-panel') }}" style="display:inline-block;padding:11px 18px;border-radius:8px;background:#F5C518;color:#0b0b0b;font:600 13px/1 -apple-system,Segoe UI,Arial,sans-serif;text-decoration:none;">Open Today</a>
        </div>
    </td></tr>
    <tr><td style="padding:16px 28px;background:#faf8f3;color:#999;font-size:12px;">
        Sent each morning from 08:00 by your website, only on a day with something to do. To stop it: admin → Website settings → Enquiry alerts → Morning tasks.
    </td></tr>
</table>
</body>
</html>
