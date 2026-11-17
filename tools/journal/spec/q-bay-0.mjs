// The bay as found in 2012 (Kurapkat 2014): rolling shutters under the arches, the stone painted over, the gallery
// gone but for a few beam ends, the room's door made a grilled window, cables along the wall. See q-bay.mjs.
import { base, arch, gallery, C } from './q-bay.mjs';
const b = base();
const slats = []; for (let y = 0.1; y < 2.15; y += 0.1) slats.push([0.2, y, 2.2, y]);
export default {
  ...b,
  edge: [...b.edge, ...C.map(arch),
    { rect: [0.2, 0, 2.0, 2.15] }, { rect: [0.2, 2.15, 2.0, 0.3] }, { rect: [2.6, 2.15, 2.0, 0.3] }, { rect: [2.5, 2.52, 2.2, 0.4] },
    ...gallery().slice(0, 11).filter((_, j) => [0, 3, 4, 8].includes(j))],
  over: [[1.95, 3.55, 1.95, 5.4, 2.85, 5.4, 2.85, 3.55]],
  grille: [[1.95, 4.3, 0.9, 1.1]],
  tex: [...b.tex, ...slats,
    [-0.6, 3.0, 2.4, 2.84, 5.4, 3.0], [-0.6, 2.95, 1.2, 2.88, 3.0, 2.92, 5.4, 2.97],
    [1.2, 2.68, 1.3, 2.9, 1.22, 3.1, 1.35, 3.25]],
  rooms: [[3.6, 0.7, '@shop']],
};
