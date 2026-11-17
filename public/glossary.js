/* =========================================================
   The glossary's game (journal/glossary, [data-quiz]): one word's drawing,
   its name hidden, and three Kurdish names from the same drawing to pick
   from. The pick shows the name on the drawing, and the score so far.
   ========================================================= */
(function () {
    var quiz = document.querySelector('[data-quiz]');
    var cards = [].slice.call(document.querySelectorAll('.jg-word'));
    if (!quiz || cards.length < 3) return;

    var stage = quiz.querySelector('[data-quiz-stage]'), opts = quiz.querySelector('[data-quiz-opts]'),
        out = quiz.querySelector('[data-quiz-out]'), score = quiz.querySelector('[data-quiz-score]'),
        digits = quiz.dataset.digits, still = window.matchMedia('(prefers-reduced-motion: reduce)').matches,
        right = 0, asked = 0, last = null;
    var num = function (n) { return String(n).replace(/\d/g, function (d) { return digits.charAt(d); }); };
    var shuffle = function (a) { for (var i = a.length - 1; i > 0; i--) { var j = Math.random() * (i + 1) | 0, t = a[i]; a[i] = a[j]; a[j] = t; } return a; };

    function ask() {
        var card;
        do { card = cards[Math.random() * cards.length | 0]; } while (card === last);
        last = card;

        // the word's own drawing, drawn again; its name stays hidden until a pick
        var svg = card.querySelector('svg').cloneNode(true);
        svg.classList.remove('is-pending', 'is-drawn');
        stage.replaceChildren(svg);
        quiz.classList.remove('is-shown');
        if (!still) {
            svg.classList.add('is-pending');
            svg.getBoundingClientRect();
            requestAnimationFrame(function () { svg.classList.replace('is-pending', 'is-drawn'); });
        }

        // the right name and two others, from the same drawing where it has them
        var near = shuffle(cards.filter(function (c) { return c !== card && c.dataset.scene === card.dataset.scene; }));
        var far = shuffle(cards.filter(function (c) { return c.dataset.scene !== card.dataset.scene; }));
        opts.replaceChildren.apply(opts, shuffle([card].concat(near.concat(far).slice(0, 2))).map(function (c) {
            var b = document.createElement('button'), ku = document.createElement('span'), lat = document.createElement('small');
            b.type = 'button';
            ku.lang = 'ckb'; ku.dir = 'rtl'; ku.textContent = c.dataset.ku;
            lat.dir = 'ltr'; lat.textContent = c.dataset.lat;
            b.append(ku, lat);
            b.addEventListener('click', function () { pick(b, c === card, card); });
            b.dataset.right = c === card ? '1' : '';
            return b;
        }));
        out.textContent = '';
    }

    function pick(btn, ok, card) {
        asked++;
        if (ok) right++;
        opts.querySelectorAll('button').forEach(function (b) {
            b.disabled = true;
            if (b.dataset.right) b.classList.add('is-right');
        });
        if (!ok) btn.classList.add('is-wrong');
        quiz.classList.add('is-shown');
        // the Kurdish in its own run, so its full stop stays on the right side of it in any page
        var ku = document.createElement('bdi');
        ku.lang = 'ckb'; ku.textContent = card.dataset.ku;
        var lat = document.createElement('span');
        lat.className = 'jg-lat'; lat.textContent = card.dataset.name + ' · ' + card.dataset.lat;
        out.replaceChildren(quiz.dataset[ok ? 'right' : 'wrong'] + ' ', ku, ok ? ' · ' : '. ', lat);
        score.textContent = num(right) + ' / ' + num(asked);
    }

    quiz.querySelector('[data-quiz-next]').addEventListener('click', ask);
    quiz.hidden = false;
    ask();
})();
