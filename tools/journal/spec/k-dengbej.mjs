// The Dengbêj House, Amed (Diyarbakır), ground floor (Ergün 2025, fig. 4c, "edited from Yıldırım 2002"; CC BY 4.0).
// The figure has no scale, and the iwan's measurements in the text (4.26 x 6.72 m) do not fit its proportions, so
// this is drawn without one: upm only sets its size (walls about the 60-70 cm the paper gives). Read off the figure
// in pixels of a 7x render (X, Y, T below); north is up, as the figure's arrow. The single lines on the figure, the
// garden's and the court's walls to the north and east, are drawn thin; the spaces it leaves unnamed stay unnamed.
const S = 0.0271, X = (x) => +((x - 5) * S).toFixed(3), Y = (y) => +((y - 45) * S).toFixed(3), T = (t) => +(t * S).toFixed(3);
// a wall from (x1, y1) to (x2, y2), th thick, its ends square (they are read off the figure's faces);
// ops [a, b, type, hinge, side] with a, b where the opening runs along it
const wall = (x1, y1, x2, y2, th, ops = []) => [[X(x1), Y(y1)], [X(x2), Y(y2)], T(th),
  ops.map(([a, b, t, h = 0, side = 1]) => [T(a - (x1 === x2 ? y1 : x1)), T(b - (x1 === x2 ? y1 : x1)), t, h, side]), [0, 0]];
// steps up from the court, as the figure draws them: the outline and its treads, no arrow
const steps = (x0, x1, y0, y1, n) => [['rect', X(x0), Y(y0), T(x1 - x0), T(y1 - y0)],
  ...[...Array(n - 1).keys()].map((i) => ['line', X(x0), Y(y0 + (y1 - y0) * (i + 1) / n), X(x1), Y(y0 + (y1 - y0) * (i + 1) / n)])];
export default {
  upm: 100, rot: 0, fs: 60, noScale: true, north: 0,
  walls: [
    // the west wing: two long rooms, one behind the other
    wall(200, 45, 200, 395, 14), wall(288, 45, 288, 395, 20, [[215, 245, 'd', 0, 1]]), wall(193, 52, 298, 52, 15),
    wall(207, 226, 278, 226, 17, [[232, 262, 'd', 0, -1]]), wall(193, 376, 298, 376, 23, [[232, 262, 'd', 0, -1]]),
    // the west room, its door to the court
    wall(47, 360, 47, 590, 15), wall(40, 367, 200, 367, 15), wall(215, 375, 215, 590, 14, [[505, 545, 'd', 0, 1]]),
    // the passage to the stair, and the room south of it
    wall(5, 584, 215, 584, 12), wall(10, 584, 10, 735, 10), wall(5, 730, 61, 730, 10), wall(215, 590, 215, 727, 14, [[600, 645, 'o']]),
    wall(61, 652, 61, 905, 13), wall(55, 652, 215, 652, 15, [[115, 160, 'd', 0, 1]]), wall(197, 652, 197, 940, 16), wall(55, 900, 205, 900, 10),
    // the south range: a room, the summer iwan open to the north, a room; the stair down behind the iwan
    wall(190, 928, 912, 928, 25, [[535, 575, 'o']]), wall(190, 718, 462, 718, 20, [[235, 258, 'w'], [342, 368, 'w'], [385, 410, 'w']]),
    wall(708, 725, 905, 725, 20, [[775, 800, 'w'], [820, 850, 'w']]),
    wall(449, 718, 449, 928, 25, [[810, 850, 'd', 0, 1]]), wall(718, 725, 718, 928, 20, [[765, 805, 'd', 0, -1]]),
    wall(521, 940, 521, 995, 12), wall(515, 990, 775, 990, 10), wall(770, 940, 770, 995, 10),
    // the east wall and its gate to the street
    wall(900, 515, 900, 940, 24, [[597, 655, 'dd', 0, 1]]),
    // single lines on the figure: the garden to the north, the court's east side, a closet in its corner
    wall(288, 285, 565, 285, 8), wall(565, 45, 565, 515, 8), wall(298, 45, 565, 45, 8), wall(565, 515, 900, 515, 8),
    wall(805, 515, 805, 598, 8), wall(805, 598, 900, 598, 8),
  ],
  stairs: [{ p: [X(37), Y(598)], dir: 90, w: T(32), n: 12, t: T(10), lbl: '@up' }],
  // the steps behind the iwan: the figure does not say where they go
  fix: [['basin', X(425), Y(440), T(62), T(42)], ...steps(265, 300, 683, 718, 5), ...steps(462, 500, 683, 718, 5), ...steps(735, 770, 683, 718, 5), ...steps(577, 637, 944, 984, 4)],
  over: [[X(462), Y(727), X(708), Y(727)]],
  split: [[X(462), Y(727), X(708), Y(727)]],
  rooms: [
    // named where the figure names them (court, iwan, room) or nothing else could be meant (the room east of the iwan)
    [X(420), Y(600), 'court', { no: '1' }], [X(585), Y(805), 'iwan', { no: '2' }], [X(320), Y(820), 'room', { no: '3' }], [X(805), Y(830), 'room', { no: '3' }],
  ],
  court: [X(420), Y(600)],
  entry: [X(912), Y(626), 270],
};
