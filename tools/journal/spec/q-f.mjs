// Qaysari, the same stretch, upper floor: each room over two shops, entered only from a timber gallery along the
// passage wall; two rooms joined, the door no longer needed made a grilled window (Kurapkat 2014). A type drawing.
const X0 = 1.0, XE = 20.2;
const door = (k, t = 'd', side = -1) => [4.8 * k + 1.75, 4.8 * k + 2.65, t, 0, side];
export default {
  upm: 100, rot: 0, fs: 34,
  walls: [
    [[0, 0.3], [XE, 0.3], 0.6, [], [1, 0]],
    [[0, 9.3], [XE, 9.3], 0.6, [], [1, 0]],
    [[0.5, 0], [0.5, 9.6], 1.0, [[4.2, 5.4, 'w']], [0, 0]],
    [[X0, 2.8], [XE, 2.8], 0.4, [door(0), door(1), door(2), door(3, 'w')], [0, 0]],
    [[X0, 6.8], [XE, 6.8], 0.4, [door(0, 'd', 1), [8.2, 9.1, 'd', 1, 1], door(2, 'd', 1), door(3, 'd', 1)], [0, 0]],
    ...[5.6, 10.4, 20.0].map((x) => [[x, 0.3], [x, 2.8], 0.4, [], [0, 0]]),
    [[15.2, 0.3], [15.2, 2.8], 0.4, [[1.0, 1.9, 'o']], [0, 0]],
    ...[5.6, 10.4, 15.2, 20.0].map((x) => [[x, 6.8], [x, 9.3], 0.4, [], [0, 0]]),
  ],
  stairs: [{ p: [9.08, 8.45], dir: 180, w: 0.95, n: 11, t: 0.28, lbl: '@dn' }],
  // the galleries: a deck along each wall, its rail on the void
  fix: [['rect', X0, 3.84, XE - X0, 0.06], ['rect', X0, 5.7, XE - X0, 0.06]],
  ctx: [[20.6, -0.2, 20.6, 4.5, 20.9, 4.65, 20.3, 4.95, 20.6, 5.1, 20.6, 9.8]],
  rooms: [
    [4.4, 1.5, 'room', { no: '7' }], [9.2, 1.5, 'room', { no: '7' }], [14.0, 1.5, 'room', { no: '8' }], [18.8, 1.5, 'room', { no: '8' }],
    [4.4, 8.15, 'room', { no: '7' }], [14.0, 8.15, 'room', { no: '7' }], [18.8, 8.15, 'room', { no: '7' }],
    [11.0, 3.45, 'gallery', { no: '6' }], [11.0, 6.2, 'gallery', { no: '6' }], [11.0, 4.85, 'void'],
  ],
  split: [[X0, 3.87, XE, 3.87], [X0, 5.73, XE, 5.73]],
  axes: { x: [3.2, 5.6, 8.0, 10.4, 12.8, 15.2, 17.6, 20.0], y: [0.6, 3.0, 6.6, 9.0] },
};
