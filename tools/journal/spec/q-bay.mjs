// Qaysari, one bay of a passage wall (two shops and the room over them) in elevation, as found and as the DAI and
// TU Berlin's sample bay of 2012 proposed: the stone arch freed of its paint, folding shutters of steel and timber
// in place of the rolling shutters, the timber gallery repaired. Shared parts; q-bay-0 and q-bay-1 import it.
// x from the first pier's centre, y up from the passage floor. Illustrations of a description, not a survey.
export const W0 = -0.6, W1 = 5.4;
const C = [1.2, 3.6];   // the two arches' centres; each 2.0 wide, springing at +1.50
export const base = () => ({
  fs: 22,
  cut: [[W0 - 0.2, -0.3, W1 - W0 + 0.4, 0.3]],
  edge: [[W0, 6.2, W1, 6.2]],
  // the wall goes on to either side
  tex: [[W0, -0.1, W0, 6.6], [W1, -0.1, W1, 6.6]],
  levels: [[0, '±0.00'], [1.5, '+1.50'], [3.55, '+3.55'], [6.2, '+6.20']],
  levelX: W0 - 1.0,
});
export const arch = (c) => ({ arch: [c - 1.0, 0, 2.0, 2.5, 'r'] });
export const ring = (c) => {   // the stone ring: jambs in courses, nine voussoirs, the extrados
  const out = [{ arch: [c - 1.18, 0, 2.36, 2.68, 'r'] }];
  for (const y of [0.38, 0.76, 1.14]) out.push([c - 1.18, y, c - 1.0, y], [c + 1.0, y, c + 1.18, y]);
  for (let k = 1; k < 9; k++) { const a = k * Math.PI / 9; out.push([c + Math.cos(a) * 1.0, 1.5 + Math.sin(a) * 1.0, c + Math.cos(a) * 1.18, 1.5 + Math.sin(a) * 1.18]); }
  return out;
};
export const gallery = (n = 11) => [
  ...[...Array(n).keys()].map((j) => ({ rect: [W0 + 0.25 + j * 0.6, 3.25, 0.14, 0.22] })),
  { rect: [W0, 3.47, W1 - W0, 0.08] },
];
export const rail = () => {
  const out = [{ rect: [W0, 4.45, W1 - W0, 0.08] }, { rect: [W0, 3.6, W1 - W0, 0.05] }];
  for (let x = W0 + 0.3; x < W1; x += 1.2) out.push({ rect: [x, 3.55, 0.08, 0.98] });
  const bal = []; for (let x = W0 + 0.12; x < W1; x += 0.12) bal.push([x, 3.65, x, 4.45]);
  return [out, bal];
};
export { C };
