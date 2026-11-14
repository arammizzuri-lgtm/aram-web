{{-- Someone asked for a visit on the website (App\Mail\VisitRequested).
     Inline styles and tables only: mail apps ignore stylesheets. --}}
@php
    /** @var \App\Models\VisitRequest $visit */
    $btn = 'display:inline-block;margin:0 8px 8px 0;padding:11px 18px;border-radius:8px;font:600 13px/1 -apple-system,Segoe UI,Arial,sans-serif;text-decoration:none;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:24px 12px;background:#f3f1ec;font:15px/1.6 -apple-system,Segoe UI,Arial,sans-serif;color:#1d1d1d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;">
    <tr><td style="background:#0b0b0b;padding:22px 28px;">
        <div style="color:#fff;font-size:13px;letter-spacing:4px;font-weight:600;">ARAM MIZURI</div>
        <div style="color:#F5C518;font-size:11px;letter-spacing:3px;margin-top:4px;">A VISIT ASKED FOR</div>
    </td></tr>
    <tr><td style="padding:28px;">
        <h1 style="margin:0 0 6px;font-size:20px;font-weight:600;color:#9a7a00;" dir="auto">{{ \App\Models\VisitRequest::KINDS[$visit->kind] }} — {{ $visit->name }}</h1>
        <p style="margin:0 0 18px;font-size:16px;">{{ $visit->wanted_on->format('l j F Y') }}, {{ $visit->wanted_time }} · <a href="tel:+{{ phone_digits($visit->phone) }}" style="color:#1d1d1d;">{{ $visit->phone }}</a></p>
        <div style="margin-bottom:22px;padding:16px 18px;background:#f7f6f2;border-radius:10px;white-space:pre-line;" dir="auto">{{ $visit->about }}</div>
        <a href="{{ $adminUrl }}" style="{{ $btn }}background:#F5C518;color:#0b0b0b;">Confirm it or suggest another time</a>
    </td></tr>
</table>
<p style="text-align:center;color:#999;font-size:12px;margin-top:16px;">Sent by your website when someone asks for a visit.</p>
</body>
</html>
