{{-- The client's portrait, drawn in this browser from their saved photo
     and kept as strokes (portrait-drawer-admin.js). Keyed to the photo, so
     a new photo brings a fresh field, which draws the new one. --}}
@php
    $pdRecord = $getRecord();
    $pdPhoto = route('filament.studio-aks-panel.testimonial-photo', $pdRecord, false).'?v='.md5((string) $pdRecord->portrait);
    $pdDrawing = $pdRecord->needsDrawing() ? '' : \Illuminate\Support\Facades\Storage::disk('public')->url($pdRecord->portrait_sketch);
    $pdVersioned = fn (string $file) => asset($file).'?v='.filemtime(public_path($file));
    $pdModule = $pdVersioned('portrait-drawer-admin.js');
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="pd" wire:ignore wire:key="pd-{{ md5((string) $pdRecord->portrait) }}"
         data-photo="{{ $pdPhoto }}" data-drawing="{{ $pdDrawing }}" data-detail="{{ $pdRecord->sketch_detail ?: 'balanced' }}"
         data-drawer="{{ $pdVersioned('portrait-drawer.js') }}" data-pencil="{{ $pdVersioned('pencil.js') }}"
         x-data x-init="import(@js($pdModule)).then(m => m.mount($el))">
        <div class="pd__paper"></div>
        <p class="pd__status" aria-live="polite"></p>
        <div class="pd__controls">
            <select class="pd__detail" aria-label="How many lines">
                <option value="soft">Fewer lines</option>
                <option value="balanced">Balanced</option>
                <option value="strong">More lines</option>
            </select>
            <label><input type="checkbox" class="pd__shade" checked> Shading</label>
            <label><input type="checkbox" class="pd__guides" checked> Guide lines</label>
            <button type="button" class="pd__again">Draw again</button>
        </div>
    </div>
    <style>
        .pd { display: grid; gap: 10px; justify-items: start; }
        .pd__paper { width: 220px; height: 220px; border-radius: 50%; overflow: hidden;
            background: radial-gradient(circle at 36% 30%, #F7F3EB 0%, #ECE6DB 62%, #DED7CA 100%);
            box-shadow: 0 0 0 1px rgba(0,0,0,.12); }
        .pd__paper svg { width: 100%; height: 100%; display: block; }
        .pd__status { font-size: 13px; opacity: .8; max-width: 34em; margin: 0; }
        .pd__status--bad { color: rgb(239,68,68); opacity: 1; }
        .pd__controls { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 14px; font-size: 13px; }
        .pd__controls select { font-size: 13px; padding: 4px 28px 4px 10px; border-radius: 8px; background-color: transparent; border: 1px solid rgba(128,128,128,.4); }
        .pd__controls label { display: inline-flex; gap: 6px; align-items: center; }
        .pd__again { padding: 5px 12px; border-radius: 8px; border: 1px solid rgba(128,128,128,.4); font-weight: 500; }
        .pd__again:disabled { opacity: .5; }
    </style>
</x-dynamic-component>
