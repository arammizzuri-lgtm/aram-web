// Qaysari, a stretch of passage, ground floor. Not a survey: a type drawing from the published description
// (Kurapkat 2014): passages 3-4 m wide, shops of about 4 m2 opening under stone arches, party walls about 40 cm,
// gates to the bazaar lanes, stair houses, a cellar under each shop. Plan y runs south.
const X0 = 1.0, XE = 20.2, B = 2.4;
const xs = (i) => X0 + B * i, wc = (i) => X0 + B * i + 2.2;
const arches = (skip = []) => [...Array(8).keys()].filter((i) => !skip.includes(i)).map((i) => [B * i, B * i + 2.0, 'o']);
export default {
  upm: 100, rot: 0, fs: 34,
  walls: [
    [[0, 0.3], [XE, 0.3], 0.6, [], [1, 0]],
    [[0, 9.3], [XE, 9.3], 0.6, [], [1, 0]],
    [[0.5, 0], [0.5, 9.6], 1.0, [[3.4, 6.2, 'dd', 0, -1]], [0, 0]],
    [[X0, 2.8], [XE, 2.8], 0.4, arches(), [0, 0]],
    [[X0, 6.8], [XE, 6.8], 0.4, [...arches([2, 3]), [5.0, 6.0, 'd', 0, 1]], [0, 0]],
    ...[...Array(8).keys()].map((i) => [[wc(i), 0.3], [wc(i), 2.8], 0.4, [], [0, 0]]),
    ...[...Array(8).keys()].filter((i) => i !== 2).map((i) => [[wc(i), 6.8], [wc(i), 9.3], 0.4, [], [0, 0]]),
  ],
  stairs: [{ p: [6.0, 8.45], dir: 0, w: 0.95, n: 11, t: 0.28, lbl: '@up' }],
  // the one cellar hatch left open
  fix: [['rect', 14.2, 0.8, 0.6, 0.55], ['line', 14.2, 0.8, 14.8, 1.35], ['line', 14.8, 0.8, 14.2, 1.35]],
  // the timber galleries overhead
  over: [[X0, 3.9, XE, 3.9], [X0, 5.7, XE, 5.7]],
  // the passage goes on
  ctx: [[20.6, -0.2, 20.6, 4.5, 20.9, 4.65, 20.3, 4.95, 20.6, 5.1, 20.6, 9.8]],
  rooms: [
    ...[...Array(8).keys()].map((i) => [xs(i) + 1.0, 1.75, 'shop', { no: '1' }]),
    ...[0, 1, 4, 5, 6, 7].map((i) => [xs(i) + 1.0, 8.0, 'shop', { no: '1' }]),
    [12.0, 4.8, 'passage', { no: '2' }],
    [8.6, 7.55, 'stair', { no: '4' }],
  ],
  tags: [[2.9, 4.95, '3'], [13.85, 1.2, '5']],
  notes: [[-0.45, 4.8, '@lane', 'middle', -90]],
  axes: { x: [3.2, 5.6, 8.0, 10.4, 12.8, 15.2, 17.6, 20.0], y: [0.6, 3.0, 6.6, 9.0] },
};
