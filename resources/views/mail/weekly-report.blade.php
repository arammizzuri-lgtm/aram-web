{{-- The weekly report (App\Mail\WeeklyReportMail). Inline styles and tables
     only: mail apps ignore stylesheets and most layout CSS. --}}
@php
    /** @var array<string, mixed> $r */
    $change = function (int $now, int $before) {
        if ($before === 0) {
            return $now > 0 ? ['new', '#2e9d57'] : ['—', '#999'];
        }
        $pct = (int) round(($now - $before) / $before * 100);

        return [($pct > 0 ? '▲ ' : ($pct < 0 ? '▼ ' : '')).abs($pct).'%', $pct > 0 ? '#2e9d57' : ($pct < 0 ? '#c0392b' : '#999')];
    };
    $figures = [
        ['Visitors', $r['visitors'][0], $r['visitors'][1]],
        ['Pages viewed', $r['views'][0], $r['views'][1]],
        ['Enquiries', $r['enquiryList']->count(), $r['enquiriesBefore']],
        ['WhatsApp taps', $r['whatsapp'][0], $r['whatsapp'][1]],
    ];
    $admin = url('/studio-aks-panel');
    // a page's address as a person would name it: "About page (Arabic)", a project's name
    $pageName = function (string $path) {
        $lang = ['ku' => 'Kurdish', 'ar' => 'Arabic'];
        $in = 'English';
        if (preg_match('#^/(ku|ar)(/.*)?$#', $path, $m)) { $in = $lang[$m[1]]; $path = $m[2] ?? '/'; }
        $named = ['/' => 'Homepage', '/projects' => 'All projects', '/aram-mizuri' => 'About page', '/journal' => 'Journal',
            '/kurdistan-heritage' => 'Kurdistan heritage', '/partners' => 'Partners', '/estimate' => 'Cost estimate', '/careers' => 'Careers', '/photos' => 'Photos by room', '/saved' => 'Saved projects'];
        if (isset($named[$path])) return $named[$path].' ('.$in.')';
        if (preg_match('#^/projects/([a-z0-9-]+)$#', $path, $m)) {
            return (\App\Models\Project::where('slug', $m[1])->value('name') ?? $m[1]).' ('.$in.')';
        }
        return $path.($in !== 'English' ? ' ('.$in.')' : '');
    };
    $h2 = 'margin:30px 0 10px;font-size:12px;letter-spacing:2px;text-transform:uppercase;color:#a07d00;';
    $row = 'padding:9px 0;border-bottom:1px solid #eee;font-size:14px;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:24px 12px;background:#f3f1ec;font:15px/1.6 -apple-system,Segoe UI,Arial,sans-serif;color:#1d1d1d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;">
    <tr><td style="background:#0b0b0b;padding:22px 28px;">
        <div style="color:#fff;font-size:13px;letter-spacing:4px;font-weight:600;">ARAM MIZURI</div>
        <div style="color:#F5C518;font-size:11px;letter-spacing:3px;margin-top:4px;">YOUR WEEK ON THE WEBSITE</div>
    </td></tr>
    <tr><td style="padding:28px;">
        <h1 style="margin:0 0 4px;font-size:22px;font-weight:600;">{{ $r['from']->format('j M') }} – {{ $r['to']->format('j M Y') }}</h1>
        <p style="margin:0 0 20px;color:#777;font-size:13px;">Compared with the week before.</p>

        {{-- the four figures --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
            <tr>
                @foreach ($figures as [$label, $now, $before])
                    @php [$txt, $col] = $change($now, $before); @endphp
                    <td width="25%" style="padding:12px 8px;background:#faf8f3;border-radius:10px;text-align:center;vertical-align:top;">
                        <div style="font-size:26px;font-weight:600;line-height:1.1;">{{ number_format($now) }}</div>
                        <div style="font-size:11px;color:#777;margin-top:4px;">{{ $label }}</div>
                        <div style="font-size:11px;color:{{ $col }};margin-top:4px;font-weight:600;">{{ $txt }}</div>
                    </td>
                    @if (! $loop->last)<td width="6"></td>@endif
                @endforeach
            </tr>
        </table>

        {{-- enquiries, each one --}}
        <div style="{{ $h2 }}">Enquiries</div>
        @forelse ($r['enquiryList'] as $e)
            <div style="{{ $row }}">
                <strong>{{ $e->name }}</strong>
                <span style="color:#777;"> · {{ $e->created_at?->timezone('Asia/Baghdad')->format('D j M') }}{{ $e->typeLabel() ? ' · '.$e->typeLabel() : '' }}{{ $e->project ? ' · about '.$e->project->name : '' }}</span>
            </div>
        @empty
            <p style="margin:0;color:#777;font-size:14px;">No enquiries this week.</p>
        @endforelse
        @if ($r['estimates']['run'] > 0)
            <p style="margin:10px 0 0;color:#555;font-size:13px;">The cost estimator was used {{ $r['estimates']['run'] }} {{ $r['estimates']['run'] === 1 ? 'time' : 'times' }}; {{ $r['estimates']['sent'] }} sent their estimate to you.</p>
        @endif

        {{-- how they found the site --}}
        @if ($r['sources'])
            <div style="{{ $h2 }}">How visitors found you</div>
            @foreach ($r['sources'] as $s)
                <div style="{{ $row }}">{{ $s['label'] }} <span style="float:right;color:#555;">{{ $s['visitors'] }} {{ $s['visitors'] === 1 ? 'visitor' : 'visitors' }}{{ $s['enquiries'] ? ' · '.$s['enquiries'].' enq.' : '' }}</span></div>
            @endforeach
        @endif

        {{-- the projects looked at most --}}
        @if ($r['projects'])
            <div style="{{ $h2 }}">Most viewed projects</div>
            @foreach ($r['projects'] as $p)
                <div style="{{ $row }}">{{ $p['project']->name }} <span style="float:right;color:#555;">{{ $p['views'] }} {{ $p['views'] === 1 ? 'view' : 'views' }}{{ $p['saves'] ? ' · '.$p['saves'].' saved' : '' }}</span></div>
            @endforeach
        @endif

        {{-- what visitors did with the partners — only in a week something happened --}}
        @if (! empty($r['partners']))
            <div style="{{ $h2 }}">Partners this week</div>
            @foreach ($r['partners'] as $pp)
                <div style="{{ $row }}">
                    <strong>{{ $pp['name'] }}</strong>
                    <span style="float:right;color:#555;">{{ $pp['open'] }} opened{{ $pp['reached'] ? ' · '.$pp['reached'].' reached them' : '' }}{{ $pp['badge'] ? ' · '.$pp['badge'].' from their badge' : '' }}</span>
                </div>
            @endforeach
            <p style="margin:8px 0 0;color:#888;font-size:12px;">"Reached them" = calls, WhatsApp, website visits and directions.</p>
        @endif

        {{-- the pages read most --}}
        @if ($r['pages'])
            <div style="{{ $h2 }}">Pages read most</div>
            @foreach ($r['pages'] as $path => $count)
                <div style="{{ $row }}">{{ $pageName($path) }} <span style="float:right;color:#555;">{{ $count }}</span></div>
            @endforeach
        @endif

        <div style="margin-top:28px;">
            <a href="{{ $admin }}" style="display:inline-block;padding:11px 18px;border-radius:8px;background:#F5C518;color:#0b0b0b;font:600 13px/1 -apple-system,Segoe UI,Arial,sans-serif;text-decoration:none;">Open the dashboard</a>
        </div>
    </td></tr>
    <tr><td style="padding:16px 28px;background:#faf8f3;color:#999;font-size:12px;">
        Sent every Sunday morning by your website. To stop it: admin → Website settings → Enquiry alerts → Weekly report.
    </td></tr>
</table>
</body>
</html>
