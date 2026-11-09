{{-- The partner's "Official partner" badge for their own website: both
     styles, previewed, with the code to copy. The badge links to /p/{id},
     which counts the visit and opens this partner on /partners. --}}
@php
    /** @var \App\Models\Partner $bRecord */
    $bRecord = $getRecord();
    $bStyles = ['dark' => 'Dark — for dark websites', 'light' => 'Light — for white or light websites'];
@endphp
<div style="display:grid;gap:18px;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));">
    @foreach ($bStyles as $style => $label)
        @php $code = $bRecord->badgeCode($style); @endphp
        <div x-data="{ copied: false }" style="border:1px solid rgba(127,127,127,.25);border-radius:12px;padding:16px;">
            <p style="font-size:13px;font-weight:600;margin:0 0 12px;">{{ $label }}</p>
            <div style="display:grid;place-items:center;padding:22px;border-radius:10px;margin-bottom:12px;background:{{ $style === 'dark' ? '#1b1b1b' : '#ffffff' }};border:1px solid rgba(127,127,127,.2);">
                <img src="{{ asset('badges/partner-'.$style.'.svg') }}?v={{ filemtime(public_path('badges/partner-'.$style.'.svg')) }}" width="232" height="64" alt="">
            </div>
            <textarea readonly rows="4" x-ref="code" onclick="this.select()"
                      style="width:100%;font:12px/1.5 ui-monospace,Consolas,monospace;padding:10px;border-radius:8px;border:1px solid rgba(127,127,127,.3);background:rgba(127,127,127,.08);color:inherit;resize:none;">{{ $code }}</textarea>
            <button type="button"
                    x-on:click="navigator.clipboard.writeText($refs.code.value).then(() => { copied = true; setTimeout(() => copied = false, 1800) })"
                    style="margin-top:10px;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;background:#F5C518;color:#0b0b0b;border:0;cursor:pointer;">
                <span x-show="!copied">Copy badge code</span><span x-show="copied" x-cloak>✓ Copied</span>
            </button>
        </div>
    @endforeach
</div>
<p style="font-size:12.5px;opacity:.7;margin-top:12px;">
    Send this code to the partner to paste on their website (any page — the footer works well). It links to their page here,
    <a href="{{ $bRecord->badgeUrl() }}" target="_blank" style="text-decoration:underline;">{{ $bRecord->badgeUrl() }}</a>,
    and every visit through it is counted in the statistics below. It only works while the partner is shown on the website.
</p>
