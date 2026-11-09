{{-- What visitors did with this partner on the website, over the last 7,
     30 and 90 days: people, not clicks (each kind counted once per visitor
     per day). Robots and the studio's own signed-in visits are not counted. --}}
@php
    /** @var \App\Models\Partner $sRecord */
    $sRecord = $getRecord();
    $sPeriods = [7 => 'Last 7 days', 30 => 'Last 30 days', 90 => 'Last 90 days'];
    $sCounts = collect($sPeriods)->map(fn ($l, $days) => \App\Models\PartnerEvent::countsSince(now()->subDays($days), null, $sRecord->id)[$sRecord->id] ?? []);
    $sAny = $sCounts->flatten()->sum() > 0;
    $cell = 'padding:9px 12px;border-top:1px solid rgba(127,127,127,.2);';
@endphp
@if (! $sAny)
    <p style="font-size:13.5px;opacity:.75;margin:0;">
        Nothing yet. Numbers appear here once visitors open this partner, call, message on WhatsApp, visit their website, ask for directions or arrive from their badge.
    </p>
@else
    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr>
                    <th style="text-align:start;padding:6px 12px;font-weight:600;opacity:.7;"></th>
                    @foreach ($sPeriods as $label)
                        <th style="text-align:end;padding:6px 12px;font-weight:600;opacity:.7;white-space:nowrap;">{{ $label }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach (\App\Models\PartnerEvent::TYPES as $type => $label)
                    <tr>
                        <td style="{{ $cell }}">{{ $label }}</td>
                        @foreach ($sPeriods as $days => $_)
                            <td style="{{ $cell }}text-align:end;font-variant-numeric:tabular-nums;">{{ $sCounts[$days][$type] ?? 0 }}</td>
                        @endforeach
                    </tr>
                @endforeach
                <tr>
                    <td style="{{ $cell }}font-weight:600;">Reached the partner <span style="font-weight:400;opacity:.65;">(calls, WhatsApp, website, directions)</span></td>
                    @foreach ($sPeriods as $days => $_)
                        <td style="{{ $cell }}text-align:end;font-weight:600;color:#c99a00;">{{ collect(\App\Models\PartnerEvent::CONTACTS)->sum(fn ($t) => $sCounts[$days][$t] ?? 0) }}</td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
    <p style="font-size:12.5px;opacity:.65;margin-top:10px;">People, not clicks: each is counted once per visitor per day. Robots and your own signed-in visits are left out.</p>
@endif
