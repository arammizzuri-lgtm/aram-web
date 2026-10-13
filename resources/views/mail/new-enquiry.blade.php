{{-- New-enquiry alert (App\Mail\NewEnquiry). Inline styles and tables only:
     mail apps ignore stylesheets and most layout CSS. --}}
@php
    /** @var \App\Models\ContactMessage $e */
    $rows = array_filter([
        'Phone' => $e->phone,
        'Email' => $e->email,
        'Project type' => $e->typeLabel(),
        'City' => $e->city,
        'Plot area' => $e->plotLabel(),
        'Budget' => $e->budgetLabel(),
        'Timeline' => $e->timelineLabel(),
        'Was looking at' => $e->project?->name,
        'Had saved' => $e->savedProjects()->pluck('name')->implode(' · '),
        'Wrote in' => $e->lang === 'ku' ? 'Kurdish' : ($e->lang === 'en' ? 'English' : null),
    ], 'filled');
    $btn = 'display:inline-block;margin:0 8px 8px 0;padding:11px 18px;border-radius:8px;font:600 13px/1 -apple-system,Segoe UI,Arial,sans-serif;text-decoration:none;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;padding:24px 12px;background:#f3f1ec;font:15px/1.6 -apple-system,Segoe UI,Arial,sans-serif;color:#1d1d1d;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:14px;overflow:hidden;">
    <tr><td style="background:#0b0b0b;padding:22px 28px;">
        <div style="color:#fff;font-size:13px;letter-spacing:4px;font-weight:600;">ARAM MIZURI</div>
        <div style="color:#F5C518;font-size:11px;letter-spacing:3px;margin-top:4px;">NEW ENQUIRY</div>
    </td></tr>
    <tr><td style="padding:28px;">
        <h1 style="margin:0 0 4px;font-size:22px;font-weight:600;">{{ $e->name }}</h1>
        <p style="margin:0 0 22px;color:#777;font-size:13px;">{{ $e->created_at?->timezone('Asia/Baghdad')->format('j M Y, H:i') }} (Erbil time)</p>

        <div style="margin-bottom:18px;">
            @if ($e->email)
                <a href="mailto:{{ $e->email }}" style="{{ $btn }}background:#F5C518;color:#0b0b0b;">Reply by email</a>
            @endif
            @if ($e->whatsappUrl())
                <a href="{{ $e->whatsappUrl() }}" style="{{ $btn }}background:#25D366;color:#fff;">WhatsApp</a>
            @endif
            @if ($e->telUrl())
                <a href="{{ $e->telUrl() }}" style="{{ $btn }}background:#eee;color:#1d1d1d;">Call</a>
            @endif
        </div>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
            @foreach ($rows as $label => $value)
                <tr>
                    <td style="padding:9px 12px 9px 0;border-top:1px solid #eee;color:#888;white-space:nowrap;vertical-align:top;">{{ $label }}</td>
                    <td style="padding:9px 0;border-top:1px solid #eee;" dir="auto">{{ $value }}</td>
                </tr>
            @endforeach
        </table>

        @if (filled($e->message))
            <div style="margin-top:22px;padding:16px 18px;background:#f7f6f2;border-radius:10px;white-space:pre-line;" dir="auto">{{ $e->message }}</div>
        @endif

        <p style="margin:26px 0 0;font-size:13px;">
            <a href="{{ $adminUrl }}" style="color:#9a7a00;">Open this enquiry in the admin →</a>
        </p>
    </td></tr>
</table>
<p style="text-align:center;color:#999;font-size:12px;margin-top:16px;">Sent by the enquiry form on your website.</p>
</body>
</html>
