{{--
    Always-on drag reordering for the Projects table.

    Filament's own reorder mode works, but it hides the row actions, the
    checkboxes and the New Project button while it is active — a modal edit
    the studio found unusable. Instead every row carries a permanent handle,
    and dropping a row calls the same public `reorderTable()` endpoint the
    native mode uses, so the persistence path is Filament's own.

    Dragging is only honest while the list shows the saved order: with a
    column sort or a search active the visible order is not `sort_order`,
    and renumbering from it would scramble the curated order — so the
    handles hide themselves until the sort or search is cleared. Touch
    devices keep using the native reorder toggle (HTML5 drag events do not
    fire on touch), so the handles hide there too.
--}}
<style>
    .am-drag-handle {
        display: inline-flex; align-items: center; justify-content: center;
        width: 1.75rem; height: 1.75rem; flex: none;
        margin-inline-end: .25rem; border: none; border-radius: .5rem;
        background: transparent; color: rgba(255, 255, 255, .3);
        cursor: grab; vertical-align: middle;
    }
    .am-drag-handle:hover { background: rgba(255, 255, 255, .08); color: rgba(255, 255, 255, .8); }
    .am-drag-handle:active { cursor: grabbing; }
    tr.fi-ta-row td.fi-ta-selection-cell { white-space: nowrap; }
    tr.fi-ta-row[draggable="true"] { user-select: none; }
    tr.am-dragging { opacity: .35; }
    @media (hover: none) { .am-drag-handle { display: none; } }
</style>
<script>
    (() => {
        const ROW = 'tr.fi-ta-row';
        const MARKER = '.table.records.';
        let dragged = null;
        let startOrder = '';

        const keyOf = (row) => {
            const key = row.getAttribute('wire:key') || '';
            const at = key.indexOf(MARKER);
            return at === -1 ? null : key.slice(at + MARKER.length);
        };
        const rows = () => Array.from(document.querySelectorAll(ROW)).filter(keyOf);

        const blocked = () => {
            if (document.querySelector('.fi-ta-header-cell-sorted')) return true;
            return Array.from(document.querySelectorAll('input')).some((input) => {
                for (const attr of input.attributes) {
                    if (attr.name.startsWith('wire:model') && attr.value === 'tableSearch') {
                        return input.value.trim() !== '';
                    }
                }
                return false;
            });
        };

        const HANDLE =
            '<svg viewBox="0 0 16 16" width="14" height="14" fill="currentColor" aria-hidden="true">'
            + '<circle cx="5.5" cy="3" r="1.3"/><circle cx="10.5" cy="3" r="1.3"/>'
            + '<circle cx="5.5" cy="8" r="1.3"/><circle cx="10.5" cy="8" r="1.3"/>'
            + '<circle cx="5.5" cy="13" r="1.3"/><circle cx="10.5" cy="13" r="1.3"/>'
            + '</svg>';

        const ensureHandles = () => {
            const off = blocked();
            rows().forEach((row) => {
                const existing = row.querySelector('.am-drag-handle');
                if (off) { existing?.remove(); return; }
                if (existing) return;
                const cell = row.querySelector('td');
                if (!cell) return;
                const handle = document.createElement('button');
                handle.type = 'button';
                handle.className = 'am-drag-handle';
                handle.title = 'Drag to reorder';
                handle.innerHTML = HANDLE;
                // The row only becomes draggable while the pointer is on the
                // handle, so text selection and row clicks stay normal.
                handle.addEventListener('mousedown', () => row.setAttribute('draggable', 'true'));
                cell.insertBefore(handle, cell.firstChild);
            });
        };

        document.addEventListener('mouseup', () => {
            if (dragged) return;
            document.querySelectorAll(`${ROW}[draggable]`).forEach((row) => row.removeAttribute('draggable'));
        });

        document.addEventListener('dragstart', (event) => {
            const row = event.target.closest?.(ROW);
            if (!row || row.getAttribute('draggable') !== 'true') return;
            dragged = row;
            startOrder = rows().map(keyOf).join();
            row.classList.add('am-dragging');
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', '');
        });

        document.addEventListener('dragover', (event) => {
            if (!dragged) return;
            const row = event.target.closest?.(ROW);
            if (!row || row === dragged || row.parentNode !== dragged.parentNode) return;
            event.preventDefault();
            const box = row.getBoundingClientRect();
            row.parentNode.insertBefore(dragged, event.clientY - box.top < box.height / 2 ? row : row.nextSibling);
        });

        document.addEventListener('drop', (event) => {
            if (dragged) event.preventDefault();
        });

        document.addEventListener('dragend', () => {
            if (!dragged) return;
            const row = dragged;
            dragged = null;
            row.classList.remove('am-dragging');
            row.removeAttribute('draggable');

            const keys = rows().map(keyOf);
            if (keys.join() === startOrder) return;

            const root = row.closest('[wire\\:id]');
            if (root && window.Livewire) {
                window.Livewire.find(root.getAttribute('wire:id')).call('reorderTable', keys);
            }
        });

        // Livewire re-renders the table after every save, search or toggle,
        // which discards the injected handles — put them back when it does.
        const observer = new MutationObserver(() => {
            clearTimeout(observer._debounce);
            observer._debounce = setTimeout(ensureHandles, 150);
        });
        const boot = () => {
            ensureHandles();
            observer.observe(document.body, { childList: true, subtree: true });
        };
        document.readyState === 'loading' ? document.addEventListener('DOMContentLoaded', boot) : boot();
    })();
</script>
