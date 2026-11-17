// A gate from a bazaar lane into a Qaysari, in elevation: as the sample bay of 2013 on the West Qaysari's east front
// was restored (Kurapkat 2014): the stone gateway freed and completed, the bare brick repaired, a wrought-iron grille
// in a timber frame over it. An illustration of that description, not a survey; x from the gate's axis.
const ring = [{ arch: [-1.75, 0, 3.5, 4.35, 'r'] }];
for (const y of [0.5, 1.0, 1.5, 2.0]) ring.push([-1.75, y, -1.4, y], [1.4, y, 1.75, y]);
for (let k = 1; k < 11; k++) { const a = k * Math.PI / 11; ring.push([Math.cos(a) * 1.4, 2.6 + Math.sin(a) * 1.4, Math.cos(a) * 1.75, 2.6 + Math.sin(a) * 1.75]); }
const brick = [];
for (let y = 0.15; y < 6.75; y += 0.15) {
  brick.push([-3.6, y, -1.85, y], [1.85, y, 3.6, y]);
  if (y > 4.45 && (y < 4.75 || y > 6.15)) brick.push([-1.85, y, 1.85, y]);
  else if (y > 4.45) brick.push([-1.85, y, -0.8, y], [0.8, y, 1.85, y]);
}
const dent = []; for (let x = -3.5; x < 3.6; x += 0.25) dent.push({ rect: [x, 6.78, 0.12, 0.12] });
export default {
  fs: 22,
  cut: [[-4.4, -0.3, 8.8, 0.3]],
  edge: [...ring, [-3.6, 0, -3.6, 7.2], [3.6, 0, 3.6, 7.2], { rect: [-3.6, 6.9, 7.2, 0.3] }, [-3.6, 6.75, 3.6, 6.75], ...dent,
    { rect: [-0.8, 4.65, 1.6, 1.5] }],
  tex: brick,
  door: [[-1.4, 0, 2.8, 4.0, 'r', 2]],
  grille: [[-0.62, 4.82, 1.24, 1.16]],
  levels: [[0, '±0.00'], [4.0, '+4.00'], [7.2, '+7.20']],
  levelX: -4.4,
};
