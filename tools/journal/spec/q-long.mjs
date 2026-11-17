// Qaysari, long section down the passage, looking north at its wall: the shop arches, the gallery on its beams,
// one door for every two shops, and one of them made a grilled window. Through the gate wall at the west end.
// x from the gate wall's inner face (plan x - 1.0), y up from the passage floor. A type drawing, heights ours.
const L = 19.2, bays = [...Array(8).keys()];
const vaultLines = [7.1, 7.58, 7.89].map((y) => [0, y, L, y]);
const beams = [...Array(33).keys()].map((j) => ({ rect: [0.05 + j * 0.6, 3.25, 0.14, 0.22] }));
const posts = [...Array(17).keys()].map((j) => ({ rect: [0.05 + j * 1.2, 3.55, 0.07, 0.95] }));
export default {
  fs: 30,
  cut: [
    [-3.2, -0.5, L + 3.6, 0.5],
    [-1.0, 4.0, 1.0, 4.9],   // the gate wall over the gate
    [0, 8.0, L, 0.4],        // the vault's crown
  ],
  edge: [
    // shop arches, each in a stone ring
    ...bays.flatMap((i) => [{ arch: [2.4 * i, 0, 2.0, 2.5, 'r'] }, { arch: [2.4 * i - 0.17, 0, 2.34, 2.67, 'r'] }]),
    // the gallery: beam ends, deck, posts and rails
    ...beams, { rect: [0, 3.47, L, 0.08] }, ...posts, { rect: [0, 4.45, L, 0.07] }, [0, 4.0, L, 4.0],
    // wall head and the vault's springing
    [0, 6.2, L, 6.2],
    // the gate leaves, folded back against the reveal
    { rect: [-1.0, 0, 0.12, 2.6] },
  ],
  tex: vaultLines,
  door: [[1.75, 3.55, 0.9, 2.0, ['s', 0.15], 2], [6.55, 3.55, 0.9, 2.0, ['s', 0.15], 2], [11.35, 3.55, 0.9, 2.0, ['s', 0.15], 2]],
  grille: [[16.15, 4.3, 0.9, 1.15]],
  levels: [[0, '±0.00'], [3.55, '+3.55'], [6.2, '+6.20'], [8.4, '+8.40']],
  levelX: -4.4,
  rooms: [[1.0, 1.0, '@shop'], [10.6, 5.3, '@gallery'], [-2.1, 1.0, '@lane']],
};
