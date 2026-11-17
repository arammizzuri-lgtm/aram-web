// Qaysari, cross section through a passage and a shop on either side, looking east to the gate at the far end.
// Widths from the published description (passage 3.6, shops 2 m deep, walls 40 cm); the heights are ours, for the
// drawing: none are published. x from the north back wall, y up from the passage floor.
const arc = (cx, cy, r, a0, a1, n = 18) => [...Array(n + 1).keys()].map((i) => { const a = (a0 + (a1 - a0) * i / n) * Math.PI / 180; return [+(cx + r * Math.cos(a)).toFixed(3), +(cy + r * Math.sin(a)).toFixed(3)]; });
// the vault: soffit 1.8 m radius springing at +6.20, 40 cm thick, its haunches filled up to the roofs
const vault = [[2.6, 6.2], [2.6, 6.75], ...arc(4.8, 6.2, 2.2, 165.5, 14.5), [7.0, 6.75], [7.0, 6.2], [6.6, 6.2], ...arc(4.8, 6.2, 1.8, 0, 180), [3.0, 6.2]];
const side = (f) => {   // one side, f(x) mirrors it about the passage axis
  const r = ([x, y, w, h]) => { const a = f(x), b = f(x + w); return [Math.min(a, b), y, Math.abs(b - a), h]; };
  return [r([0, 0, 0.6, 7.3]), r([0.6, 3.0, 2.0, 0.55]), r([0.6, 6.35, 2.0, 0.4]), r([2.6, 2.5, 0.4, 3.7]), r([3.0, 3.25, 0.9, 0.3])];
};
const N = (x) => x, S = (x) => 9.6 - x;
const rail = (f) => [[f(3.86), 3.55, f(3.86), 4.55], [f(3.0), 4.5, f(3.9), 4.5], [f(3.0), 4.05, f(3.9), 4.05]];
export default {
  fs: 30,
  cut: [
    [-0.4, -2.7, 3.6, 2.7], [0.6, -2.4, 2.0, 2.05],   // the ground, with the cellar under the north shop (the hole)
    [3.2, -0.5, 6.8, 0.5],
    ...side(N), ...side(S),
    vault,
  ],
  edge: [
    // the gate at the far end of the passage, and the shop arches seen edge-on
    { arch: [3.4, 0, 2.8, 4.0, 'r'] }, { arch: [3.15, 0, 3.3, 4.25, 'r'] },
    ...rail(N), ...rail(S),
    // the piers between the next shops, beyond
    { rect: [2.6, 0, 0.4, 2.5] }, { rect: [6.6, 0, 0.4, 2.5] },
  ],
  over: [[0.6, -0.35, 2.6, -0.35]],
  levels: [[-2.4, '−2.40'], [0, '±0.00'], [3.55, '+3.55'], [6.2, '+6.20'], [8.4, '+8.40']],
  levelX: -1.6,
  rooms: [[1.6, 1.2, '@shop'], [8.0, 1.2, '@shop'], [4.8, 1.6, '@passage'], [1.6, 4.4, '@room'], [8.0, 4.4, '@room'], [4.8, 3.75, '@gallery'], [1.6, -1.5, '@cellar']],
};
