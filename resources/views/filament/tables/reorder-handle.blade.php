{{-- Drag handle rendered into each Projects row. SortableJS (set up in
     projects-drag.blade.php) uses `.am-drag-handle` as its handle selector. --}}
<button
    type="button"
    class="am-drag-handle"
    title="Drag to reorder"
    aria-label="Drag to reorder"
>
    <svg viewBox="0 0 16 16" width="15" height="15" fill="currentColor" aria-hidden="true">
        <circle cx="5.5" cy="3" r="1.35" /><circle cx="10.5" cy="3" r="1.35" />
        <circle cx="5.5" cy="8" r="1.35" /><circle cx="10.5" cy="8" r="1.35" />
        <circle cx="5.5" cy="13" r="1.35" /><circle cx="10.5" cy="13" r="1.35" />
    </svg>
</button>
