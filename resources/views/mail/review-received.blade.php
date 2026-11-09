{{-- A client's words arrived through a review link (App\Mail\ReviewReceivedMail). --}}
@php
    /** @var \App\Models\Testimonial $q */
    /** @var \App\Models\ReviewRequest $r */
    $rows = array_filter([
        'Project' => $r->project?->name,
        'Their role' => $q->{'person_role_'.$r->lang},
        'Language' => ['en' => 'English', 'ku' => 'Kurdish', 'ar' => 'Arabic'][$r->lang] ?? null,
        'Voice note' => $q->voice ? 'Yes — listen to it in the admin' : null,
        'Photo' => $q->portrait ? 'Yes — the pencil portrait is drawn when you open it' : null,
    ], 'filled');
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:24px 12px;background:#f3f1ec;font:15px/1.6 -apple-system,Segoe UI,Arial,sans-serif;color:#1d1d1d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;">
    <tr><td style="background:#0b0b0b;padding:22px 28px;">
        <div style="color:#fff;font-size:13px;letter-spacing:4px;font-weight:600;">ARAM MIZURI</div>
        <div style="color:#F5C518;font-size:11px;letter-spacing:3px;margin-top:4px;">A CLIENT'S WORDS</div>
    </td></tr>
    <tr><td style="padding:28px;">
        <h1 style="margin:0 0 4px;font-size:22px;font-weight:600;">{{ $q->person_name }}</h1>
        <p style="margin:0 0 20px;color:#777;font-size:13px;">{{ $q->submitted_at?->timezone('Asia/Baghdad')->format('j M Y, H:i') }} (Erbil time)</p>

        <div style="padding:18px 20px;background:#faf6e8;border-left:3px solid #F5C518;border-radius:8px;font-size:16px;font-style:italic;white-space:pre-line;" dir="auto">“{{ $words }}”</div>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;margin-top:18px;">
            @foreach ($rows as $label => $value)
                <tr>
                    <td style="padding:9px 12px 9px 0;border-top:1px solid #eee;color:#888;white-space:nowrap;vertical-align:top;">{{ $label }}</td>
                    <td style="padding:9px 0;border-top:1px solid #eee;" dir="auto">{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        <p style="margin:24px 0 0;font-size:13px;color:#555;">
            It is saved as a hidden quote. Open it, add the translations you want (the homepage shows each language's quotes), and switch on "Shown on the website".
        </p>
        <p style="margin:16px 0 0;">
            <a href="{{ $adminUrl }}" style="display:inline-block;padding:11px 18px;border-radius:8px;background:#F5C518;color:#0b0b0b;font:600 13px/1 -apple-system,Segoe UI,Arial,sans-serif;text-decoration:none;">Open the quote in the admin</a>
        </p>
    </td></tr>
</table>
<p style="text-align:center;color:#999;font-size:12px;margin-top:16px;">Sent by a review link on your website.</p>
</body>
</html>
