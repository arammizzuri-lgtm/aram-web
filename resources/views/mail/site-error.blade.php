{{-- The website had an error (App\Mail\SiteError, sent by App\Support\SiteAlerts). --}}
@php
    $rows = array_filter([
        'Page' => $url ? $method.' '.$url : null,
        'Came from' => $referrer,
        'Where in the code' => $where,
        'Kind' => $type,
        'Browser' => $agent,
    ], 'filled');
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:24px 12px;background:#f3f1ec;font:15px/1.6 -apple-system,Segoe UI,Arial,sans-serif;color:#1d1d1d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;">
    <tr><td style="background:#0b0b0b;padding:22px 28px;">
        <div style="color:#fff;font-size:13px;letter-spacing:4px;font-weight:600;">ARAM MIZURI</div>
        <div style="color:#ff8a7a;font-size:11px;letter-spacing:3px;margin-top:4px;">WEBSITE ERROR</div>
    </td></tr>
    <tr><td style="padding:28px;">
        <p style="margin:0 0 6px;color:#777;font-size:13px;">{{ $at }} (server time)</p>
        <div style="padding:16px 18px;background:#fdf0ee;border-left:3px solid #d9534f;border-radius:8px;font-size:15px;white-space:pre-line;word-break:break-word;">{{ $error }}</div>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin-top:18px;">
            @foreach ($rows as $label => $value)
                <tr>
                    <td style="padding:7px 12px 7px 0;color:#777;white-space:nowrap;vertical-align:top;">{{ $label }}</td>
                    <td style="padding:7px 0;word-break:break-all;">{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        <p style="margin:22px 0 0;font-size:13px;color:#777;">
            The visitor saw an error page. If this keeps coming, send this e-mail to whoever looks after the website — the full details are in the server's log (storage/logs/laravel.log).
            The same error is reported at most once every 30 minutes.
        </p>
    </td></tr>
</table>
</body>
</html>
