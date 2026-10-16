/* =========================================================
   ARAM MIZURI ARCHITECTURE — greeting.js
   The holiday card page (/card): the card redrawn as the visitor types
   their name, picks the card's language and its photograph (cards.js
   draws it), then sent on — WhatsApp through the phone's share sheet —
   or saved. The name stays in the browser: nothing here is sent to the
   studio but the count of cards made.
   ========================================================= */
(function () {
    'use strict';

    const page = document.getElementById('gc');
    const canvas = document.getElementById('gcCanvas');
    if (!page || !canvas || !window.siteCards) return;

    let card = null;
    try { card = JSON.parse(page.dataset.card); } catch (e) { return; }

    const name = document.getElementById('gcName');
    const send = document.getElementById('gcSend');
    const save = document.getElementById('gcSave');
    const form = document.getElementById('gcForm');

    const choice = () => ({
        name: name.value,
        lang: (form.querySelector('[name="gcLang"]:checked') || { value: card.lang }).value,
        photo: +((form.querySelector('[name="gcPhoto"]:checked') || { value: 0 }).value),
    });

    // drawn at most once a frame, however fast the typing
    let queued = false, drawing = Promise.resolve();
    function redraw() {
        if (queued) return;
        queued = true;
        requestAnimationFrame(() => {
            queued = false;
            drawing = window.siteCards.greeting(card, choice(), canvas).then(() => {
                send.disabled = false;
                save.disabled = false;
                page.classList.add('is-drawn');
            });
        });
    }

    form.addEventListener('input', redraw);
    form.addEventListener('change', redraw);

    // on a computer there is no share sheet: the one button saves
    if (!window.siteCards.canShareFiles()) send.hidden = true;

    const go = share => drawing.then(() => window.siteCards.send(canvas, { file: card.file, placement: 'card', share }));
    send.addEventListener('click', () => go(true));
    save.addEventListener('click', () => go(false));

    redraw();
})();
