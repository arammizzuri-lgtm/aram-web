{{--
    Smooth drag-to-reorder for the Projects table.

    Dragging is powered by SortableJS, which Filament already bundles and
    exposes as `window.Sortable` — the same engine its own reorder mode uses.
    Doing it ourselves (rather than turning on that mode) keeps the row
    actions, checkboxes and New Project button visible while you drag.

    On drop we hand the new order to Filament's own `reorderTable`, so the
    save path is unchanged. The handle is a real column (reorder-handle.blade),
    so it survives Livewire's re-render after a save; this file only wires the
    dragging and re-arms it after each Livewire update.

    Dragging is disabled while a column sort or a search is active — the
    visible order isn't the saved order then, and renumbering from it would
    scramble the sequence. SortableJS also carries the interaction to touch,
    so this works on a tablet as well as a mouse.
--}}
<style>
    /* Handle column: narrow and quiet. */
    .am-reorder-cell { width: 2.75rem; padding-inline-end: 0 !important; text-align: center; }
    .am-drag-handle {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.9rem; height: 1.9rem; border: 0; border-radius: .5rem;
        background: transparent; color: rgba(255, 255, 255, .28);
        cursor: grab; touch-action: none;
        transition: color .15s ease, background-color .15s ease, transform .15s ease;
    }
    .am-drag-handle:hover { color: rgba(245, 197, 24, .95); background: rgba(245, 197, 24, .1); }
    .am-drag-handle:active { cursor: grabbing; transform: scale(.94); }

    /* The row you have picked up — lifts off the table. */
    tr.fi-ta-row.am-chosen {
        position: relative; z-index: 20;
        background: rgba(245, 197, 24, .06) !important;
        box-shadow: 0 14px 34px -10px rgba(0, 0, 0, .7), inset 0 0 0 1px rgba(245, 197, 24, .35);
        border-radius: .6rem;
    }
    tr.fi-ta-row.am-chosen .am-drag-handle { color: #f5c518; }

    /* The gap that opens where the row will land. */
    tr.fi-ta-row.am-ghost { opacity: 0; }
    tr.fi-ta-row.am-ghost td {
        background:
            linear-gradient(rgba(245, 197, 24, .12), rgba(245, 197, 24, .12));
        box-shadow: inset 0 0 0 1px rgba(245, 197, 24, .35);
    }

    /* While a drag is in flight, mute the rest so the eye follows the row. */
    .am-reordering tbody tr.fi-ta-row:not(.am-chosen):not(.am-ghost) { opacity: .55; transition: opacity .2s ease; }
    .am-reordering .fi-ta-row { cursor: grabbing; }

    /* Sort/search active → reordering paused. */
    .am-reorder-off .am-drag-handle { opacity: .25; cursor: not-allowed; pointer-events: none; }

    @media (hover: none) { .am-drag-handle:hover { background: transparent; color: rgba(255, 255, 255, .45); } }
</style>
<script>
    (() => {
        // Each row's project id is stamped on its handle by the reorder-handle
        // column. Reading that is version-proof — unlike parsing Livewire's
        // wire:key, whose format is not ours to depend on and which, when it
        // failed to match, sent an empty order and broke reorderTable.
        const findTable = () => {
            const handle = document.querySelector('.am-drag-handle[data-record-key]');
            return handle ? handle.closest('table') : null;
        };

        // Reordering is only honest while the list shows the saved order.
        const isPaused = (table) => {
            if (table.querySelector('.fi-ta-header-cell-sorted')) return true;
            return Array.from(document.querySelectorAll('input')).some((input) =>
                Array.from(input.attributes).some(
                    (attr) => attr.name.startsWith('wire:model') && attr.value === 'tableSearch',
                ) && input.value.trim() !== '');
        };

        const persist = (tbody) => {
            const keys = Array.from(tbody.querySelectorAll('.am-drag-handle[data-record-key]'))
                .map((handle) => handle.dataset.recordKey)
                .filter((key) => key !== undefined && key !== '');

            // Never post an empty order: reorderTable would build `case end`
            // and fail with a SQL syntax error. If we cannot read the keys,
            // do nothing rather than corrupt the order.
            if (keys.length === 0) return;

            const root = tbody.closest('[wire\\:id]');
            if (root && window.Livewire) {
                window.Livewire.find(root.getAttribute('wire:id'))?.call('reorderTable', keys);
            }
        };

        const setup = () => {
            if (!window.Sortable) return;
            const table = findTable();
            const tbody = table?.querySelector('tbody');
            if (!tbody) return;

            const paused = isPaused(table);
            table.classList.toggle('am-reorder-off', paused);

            // Already wired (Livewire morphs the tbody in place) — just refresh
            // the paused state and stop; re-creating would fight the animation.
            if (tbody._amSortable) {
                tbody._amSortable.option('disabled', paused);
                return;
            }

            tbody._amSortable = window.Sortable.create(tbody, {
                draggable: 'tr.fi-ta-row',
                handle: '.am-drag-handle',
                disabled: paused,
                animation: 200,
                easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
                delay: 40,                       // a hair of intent, so a click never starts a drag
                delayOnTouchOnly: true,
                chosenClass: 'am-chosen',
                ghostClass: 'am-ghost',
                fallbackTolerance: 4,
                onStart: () => table.classList.add('am-reordering'),
                onEnd: (event) => {
                    table.classList.remove('am-reordering');
                    if (event.oldIndex !== event.newIndex) persist(tbody);
                },
            });

            // Because the table is `reorderable`, Filament wraps it in its own
            // x-sortable with an `x-on:end` that also calls reorderTable — with
            // an empty payload, since its items aren't present outside reorder
            // mode. Keep our SortableJS events from bubbling up to it, so ours
            // is the only reorderTable that ever fires.
            ['start', 'end', 'add', 'remove', 'update', 'sort', 'choose', 'unchoose', 'clone']
                .forEach((type) => tbody.addEventListener(type, (event) => event.stopPropagation()));
        };

        // Re-arm after every Livewire round-trip (save, search, sort, toggle).
        const hook = () => window.Livewire?.hook('commit', ({ succeed }) => succeed(() => queueMicrotask(setup)));
        if (window.Livewire) hook();
        document.addEventListener('livewire:init', hook);
        document.addEventListener('livewire:navigated', setup);

        document.readyState === 'loading'
            ? document.addEventListener('DOMContentLoaded', setup)
            : setup();
    })();
</script>
