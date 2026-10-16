/* =========================================================
   ARAM MIZURI ARCHITECTURE — photos.js
   Room by room (/photos): a photograph opened large, with the room and the
   project it is from — and on, through the rest of the wall, with the
   arrows, the arrow keys, or a swipe.
   ========================================================= */
(function () {
    'use strict';

    const wall = document.getElementById('phWall');
    const box = document.getElementById('phViewer');
    if (!wall || !box || typeof box.showModal !== 'function') return;
    let photos = [];
    try { photos = JSON.parse(wall.dataset.photos || '[]'); } catch (e) { return; }
    if (!photos.length) return;

    const img = box.querySelector('.ph-viewer__img');
    const room = box.querySelector('.ph-viewer__room');
    const link = box.querySelector('.ph-viewer__link');
    const name = box.querySelector('.ph-viewer__name');
    const rtl = document.documentElement.getAttribute('dir') === 'rtl';
    let cur = 0;

    function show(i) {
        cur = (i + photos.length) % photos.length;
        const p = photos[cur];
        img.classList.add('is-loading');
        img.onload = () => img.classList.remove('is-loading');
        img.src = p.src;
        img.alt = p.name + ' — ' + p.room;
        room.textContent = p.room;
        name.textContent = p.name;
        link.href = p.url;
        new Image().src = photos[(cur + 1) % photos.length].src;    // the next, ready
    }

    wall.querySelectorAll('[data-at]').forEach(btn => btn.addEventListener('click', () => {
        show(parseInt(btn.dataset.at, 10) || 0);
        box.showModal();
    }));
    box.querySelector('.ph-viewer__close').addEventListener('click', () => box.close());
    box.querySelector('.ph-viewer__prev').addEventListener('click', () => show(cur - 1));
    box.querySelector('.ph-viewer__next').addEventListener('click', () => show(cur + 1));
    box.addEventListener('click', e => { if (e.target === box) box.close(); });
    box.addEventListener('keydown', e => {
        // "forward" is to the left when reading right-to-left
        if (e.key === 'ArrowRight') show(cur + (rtl ? -1 : 1));
        if (e.key === 'ArrowLeft') show(cur + (rtl ? 1 : -1));
    });
    let startX = null;
    box.addEventListener('touchstart', e => { startX = e.touches[0].clientX; }, { passive: true });
    box.addEventListener('touchend', e => {
        if (startX === null) return;
        const dx = e.changedTouches[0].clientX - startX;
        startX = null;
        if (Math.abs(dx) > 45) show(cur + ((dx < 0) !== rtl ? 1 : -1));
    });
})();
