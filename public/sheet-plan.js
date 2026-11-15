/* =========================================================
   The plan behind a sheet's title (partials/sheet-plan):
   - its court in sun and shade as the sun stands over Erbil now, the
     sun's mark round the north point, and at night the windows lit;
   - under a mouse, CAD's crosshair with the point's coordinates in
     metres, snapping to the grid's intersections.
   ========================================================= */
(function () {
    var svg = document.querySelector('.sheet-plan');
    if (!svg) return;

    // The sun's altitude and azimuth (degrees, azimuth clockwise from north)
    // over Erbil at time t: the usual low-precision formulae, good to a
    // fraction of a degree, which is all a drawing needs.
    function sunAt(t) {
        var rad = Math.PI / 180, lat = 36.19 * rad, lng = 44.01;
        var d = t / 86400000 - 10957.5;                     // days since 2000-01-01 12:00 UTC
        var g = (357.529 + 0.98560028 * d) * rad;           // mean anomaly
        var q = 280.459 + 0.98564736 * d;                   // mean longitude
        var L = (q + 1.915 * Math.sin(g) + 0.020 * Math.sin(2 * g)) * rad;
        var e = (23.439 - 0.00000036 * d) * rad;            // obliquity
        var ra = Math.atan2(Math.cos(e) * Math.sin(L), Math.cos(L));
        var dec = Math.asin(Math.sin(e) * Math.sin(L));
        var gmst = 18.697374558 + 24.06570982441908 * d;    // hours
        var ha = (gmst * 15 + lng) * rad - ra;              // hour angle
        var alt = Math.asin(Math.sin(lat) * Math.sin(dec) + Math.cos(lat) * Math.cos(dec) * Math.cos(ha));
        var az = Math.atan2(Math.sin(ha), Math.cos(ha) * Math.sin(lat) - Math.tan(dec) * Math.cos(lat));
        return { alt: alt / rad, az: ((az / rad + 180) % 360 + 360) % 360 };
    }

    // ---- Sun and shade -------------------------------------------------
    var WALL = 90;                                   // the court's walls and eaves: 3.6 m in drawing units
    var lit = svg.querySelectorAll('.p-sunlit');
    var ray = svg.querySelector('.p-sunray'), dot = svg.querySelector('.p-sundot');
    var note = svg.querySelector('.p-sunnote');
    var clock, SP = '  ';   // SVG text folds plain spaces into one
    try { clock = new Intl.DateTimeFormat('en-GB', { timeZone: 'Asia/Baghdad', hour: '2-digit', minute: '2-digit', hour12: false }); } catch (err) {}

    function light() {
        var s = sunAt(Date.now()), day = s.alt > 0, a = s.az * Math.PI / 180;
        // the sky over the court lands on its floor moved away from the sun by
        // the walls' height over the sun's tangent; at night, nowhere
        var len = day ? WALL / Math.tan(Math.max(s.alt, 1) * Math.PI / 180) : 1e4;
        var tf = 'translate(' + (-Math.sin(a) * len).toFixed(1) + 'px,' + (Math.cos(a) * len).toFixed(1) + 'px)';
        lit.forEach(function (r) { r.style.transform = tf; });
        var x = 610 + Math.sin(a) * 24, y = 432 - Math.cos(a) * 24;
        ray.setAttribute('d', 'M610 432L' + x.toFixed(1) + ' ' + y.toFixed(1));
        dot.setAttribute('cx', x.toFixed(1)); dot.setAttribute('cy', y.toFixed(1));
        var time = clock ? clock.format(new Date()) + ' HAWLÊR' : '';
        note.textContent = day ? 'SUN ' + Math.round(s.az) + '° / ' + Math.round(s.alt) + '°' + SP + '·' + SP + time : 'NIGHT' + SP + '·' + SP + time;
        svg.classList.toggle('is-night', !day);
    }
    light();
    svg.classList.add('has-sun');
    setInterval(light, 60000);

    // ---- The crosshair -------------------------------------------------
    if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) return;
    var hero = svg.closest('.sheet-hero');
    var h = svg.querySelector('.p-cross__h'), v = svg.querySelector('.p-cross__v');
    var box = svg.querySelector('.p-cross__box'), snap = svg.querySelector('.p-cross__snap');
    var xy = svg.querySelector('.p-cross__xy');
    var AX = [[80, '1'], [200, '2'], [440, '3'], [560, '4']], AY = [[70, 'A'], [150, 'B'], [300, 'C'], [380, 'D']];
    var M = 0.04, X0 = 80, Y0 = 380;                 // 4 cm a unit; the origin at axes 1 and D

    function near(list, at) {
        for (var i = 0; i < list.length; i++) if (Math.abs(list[i][0] - at) < 9) return list[i];
        return null;
    }
    function m(n) { return (n < 0 ? '−' : '') + Math.abs(n).toFixed(2); }

    hero.addEventListener('pointermove', function (e) {
        if (e.pointerType !== 'mouse') return;
        var ctm = svg.getScreenCTM();
        if (!ctm) return;
        var p = new DOMPoint(e.clientX, e.clientY).matrixTransform(ctm.inverse());
        var on = p.x > 26 && p.x < 614 && p.y > 26 && p.y < 444;
        svg.classList.toggle('is-aiming', on);
        document.body.classList.toggle('is-aiming-plan', on);
        if (!on) return;
        // like CAD's object snap: close to where two axes cross, the cross jumps there
        var sx = near(AX, p.x), sy = near(AY, p.y), snapped = !!(sx && sy);
        var x = snapped ? sx[0] : p.x, y = snapped ? sy[0] : p.y;
        h.style.transform = 'translate(0,' + y + 'px)';
        v.style.transform = 'translate(' + x + 'px,0)';
        box.style.transform = snap.style.transform = 'translate(' + x + 'px,' + y + 'px)';
        svg.classList.toggle('is-snapped', snapped);
        var flip = x > 470;
        xy.setAttribute('x', flip ? x - 10 : x + 10);
        xy.setAttribute('y', y - 9);
        xy.setAttribute('text-anchor', flip ? 'end' : 'start');
        xy.textContent = (snapped ? 'INT ' + sx[1] + '/' + sy[1] + SP : '') + 'X ' + m((x - X0) * M) + SP + 'Y ' + m((Y0 - y) * M);
    });
    hero.addEventListener('pointerleave', function () {
        svg.classList.remove('is-aiming');
        document.body.classList.remove('is-aiming-plan');
    });
})();
