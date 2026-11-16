/* =========================================================
   The journal's drawings (journal/drawing):
   - each draws itself line by line as it scrolls into view;
   - where the plan has a court and a north point, the court is lit
     as the sun stands over Erbil now, with the sun's mark round the
     north point, as on the sheet's plan (sheet-plan.js).
   ========================================================= */
(function () {
    // "Print the plates": the plates alone, one to an A3 sheet (journal-essay.css, html.ja-printing)
    var root = document.documentElement;
    document.querySelectorAll('[data-print-plates]').forEach(function (b) {
        b.addEventListener('click', function () { root.classList.add('ja-printing'); window.print(); });
    });
    window.addEventListener('afterprint', function () { root.classList.remove('ja-printing'); });

    var svgs = document.querySelectorAll('.ja-dwg');
    if (!svgs.length) return;

    if ('IntersectionObserver' in window && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (e) {
                if (!e.isIntersecting) return;
                e.target.classList.replace('is-pending', 'is-drawn');
                io.unobserve(e.target);
            });
        }, { rootMargin: '0px 0px -12% 0px' });
        svgs.forEach(function (s) { s.classList.add('is-pending'); io.observe(s); });
    }

    // the sun over Erbil at time t (as in sheet-plan.js): altitude, and azimuth clockwise from north
    function sunAt(t) {
        var rad = Math.PI / 180, lat = 36.19 * rad, lng = 44.01;
        var d = t / 86400000 - 10957.5;
        var g = (357.529 + 0.98560028 * d) * rad;
        var q = 280.459 + 0.98564736 * d;
        var L = (q + 1.915 * Math.sin(g) + 0.020 * Math.sin(2 * g)) * rad;
        var e = (23.439 - 0.00000036 * d) * rad;
        var ra = Math.atan2(Math.cos(e) * Math.sin(L), Math.cos(L));
        var dec = Math.asin(Math.sin(e) * Math.sin(L));
        var gmst = 18.697374558 + 24.06570982441908 * d;
        var ha = (gmst * 15 + lng) * rad - ra;
        var alt = Math.asin(Math.sin(lat) * Math.sin(dec) + Math.cos(lat) * Math.cos(dec) * Math.cos(ha));
        var az = Math.atan2(Math.sin(ha), Math.cos(ha) * Math.sin(lat) - Math.tan(dec) * Math.cos(lat));
        return { alt: alt / rad, az: ((az / rad + 180) % 360 + 360) % 360 };
    }

    var lit = [].filter.call(svgs, function (s) { return s.dataset.north; });
    if (!lit.length) return;
    var clock, SP = '  ';
    try { clock = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Baghdad', hour: '2-digit', minute: '2-digit', hour12: false }); } catch (err) {}

    function light() {
        var s = sunAt(Date.now()), day = s.alt > 0, time = clock ? clock.format(new Date()) + ' HAWLÊR' : '';
        lit.forEach(function (svg) {
            // the sun's direction on this drawing: its azimuth turned by where the drawing's north points
            var a = (s.az + +svg.dataset.north) * Math.PI / 180;
            var len = day ? +svg.dataset.wallh / Math.tan(Math.max(s.alt, 1) * Math.PI / 180) : 1e6;
            var tf = 'translate(' + (-Math.sin(a) * len).toFixed(1) + 'px,' + (Math.cos(a) * len).toFixed(1) + 'px)';
            svg.querySelectorAll('.d-sunlit').forEach(function (r) { r.style.transform = tf; });
            var np = svg.dataset.np.split(','), r = +svg.dataset.nr * 1.9, x = +np[0] + Math.sin(a) * r, y = +np[1] - Math.cos(a) * r;
            svg.querySelector('.d-sunray').setAttribute('d', 'M' + np[0] + ' ' + np[1] + 'L' + x.toFixed(1) + ' ' + y.toFixed(1));
            var dot = svg.querySelector('.d-sundot'); dot.setAttribute('cx', x.toFixed(1)); dot.setAttribute('cy', y.toFixed(1));
            var note = svg.querySelector('.d-sunnote');
            if (note) note.textContent = day ? 'SUN ' + Math.round(s.az) + '° / ' + Math.round(s.alt) + '°' + SP + '·' + SP + time : 'NIGHT' + SP + '·' + SP + time;
            svg.classList.toggle('is-night', !day);
            svg.classList.add('has-sun');
        });
    }
    light();
    setInterval(light, 60000);
})();
