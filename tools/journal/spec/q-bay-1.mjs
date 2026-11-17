// The bay as the sample bay of 2012 proposed (Kurapkat 2014): see q-bay.mjs.
import { base, arch, ring, gallery, rail, C } from './q-bay.mjs';
const b = base(), [rl, bal] = rail();
const leaves = [];
for (const x of [0.7, 1.2, 1.7]) leaves.push([x, 0, x, 1.5]);
for (let i = 0; i < 4; i++) leaves.push({ rect: [0.26 + i * 0.5, 0.08, 0.38, 1.34] });
export default {
  ...b,
  edge: [...b.edge, ...C.flatMap((c) => [arch(c), ...ring(c)]),
    // left: shut behind four folding leaves; right: open, the leaves folded back to the jambs
    { rect: [0.2, 0, 2.0, 1.5] }, ...leaves, { rect: [2.6, 0, 0.14, 1.5] }, { rect: [4.46, 0, 0.14, 1.5] },
    ...gallery(), ...rl, { rect: [1.8, 3.55, 1.2, 2.14] }],
  tex: [...b.tex, ...bal],
  win: [[0.22, 1.5, 1.96, 0.98, 'r', 3, 0], [2.62, 1.5, 1.96, 0.98, 'r', 3, 0]],
  door: [[1.95, 3.55, 0.9, 2.0, ['s', 0.15], 2]],
  rooms: [[3.6, 0.7, '@shop']],
};
