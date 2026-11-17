// Salim Qaqa's house, the long section, looking at the court's brick front (Mustafa & Ali 2024, fig. 4, C2 section a).
// Scaled from the cross section's levels, drawn beside it at one scale: 59.5 px to the metre, x = (px - 75) / 59.5, y = (534 - py) / 59.5.
const brick = [];
for (let y = 0.1; y < 7.9; y += 0.35) brick.push(y < 6.12 ? [2.35, y, 6.81, y] : [0, y, 8.49, y]);
export default {
  fs: 26,
  cut: [
    // the ground, and the raised floor of the west part
    [-0.84, -1.08, 18.74, 0.31], [0, -0.77, 8.57, 0.59],
    // the west wall with its upper floor, one mass (the tall opening through it is the room's window bay)
    [[0, -0.18], [0, 6.12], [2.35, 6.12], [2.35, 1.83], [0.92, 1.83], [0.92, -0.18]],
    [[0.92, 2.59], [1.76, 2.59], [1.76, 5.78], [0.92, 5.78]],
    // the east range: roof, first floor over the serdab, its walls; the upper room is the hole
    [[8.57, -0.77], [8.57, 1.75], [11.01, 1.75], [11.01, 5.78], [8.57, 5.78], [8.57, 7.96], [8.66, 7.96], [8.66, 6.12], [16.72, 6.12], [16.72, 7.96], [16.81, 7.96], [16.81, -0.77], [15.29, -0.77], [15.29, 1.33], [11.85, 1.33], [11.85, 1.0], [11.01, 1.0], [11.01, 1.33], [8.82, 1.33], [8.82, -0.77]],
    [[11.85, 1.75], [15.29, 1.75], [15.29, 5.78], [11.85, 5.78]],
  ],
  edge: [
    // the brick front beyond, to +7.96, and its cornice
    [0, 6.12, 0, 7.96, 8.49, 7.96], [2.35, -0.18, 2.35, 6.12], [0, 6.45, 2.35, 6.45],
    // hoods over the two coloured-glass windows
    { rect: [2.35, 3.95, 1.43, 0.5] }, { rect: [4.54, 3.95, 1.34, 0.5] },
    // the arcade of the tarma: two segmental arches on a banded column
    { arch: [6.81, 1.75, 1.76, 3.61, ['s', 0.6]] }, { arch: [8.99, 1.75, 1.93, 3.61, ['s', 0.6]] },
    { rect: [8.57, 1.75, 0.42, 3.4] }, [8.57, 2.6, 8.99, 2.6], [8.57, 3.45, 8.99, 3.45], [8.57, 4.3, 8.99, 4.3],
  ],
  win: [
    // seven pointed niches along the parapet
    ...[1.09, 2.18, 3.24, 4.32, 5.38, 6.44, 7.48].map((c) => [c - 0.19, 6.62, 0.38, 1.0, ['p', 0.25], 0, 0]),
    [3.87, 5.29, 0.59, 0.66, 0, 0, 1], [9.83, 4.77, 0.51, 0.5, 0, 0, 1],
    // the two tall coloured-glass windows of the hall
    [2.44, 2.42, 1.26, 1.6, ['s', 0.3], 1, 2], [4.62, 2.42, 1.18, 1.6, ['s', 0.3], 1, 2],
    [2.35, 0.07, 0.76, 1.17, 0, 0, 1],
    // the upper room beyond, and the east wall's windows, cut
    [13.11, 4.77, 0.59, 0.59, 0, 0, 1], [14.12, 4.77, 0.59, 0.59, 0, 0, 1],
    [15.38, 4.77, 0.33, 0.59, 0, 0, 0], [15.38, 2.34, 0.33, 1.36, 0, 0, 0],
  ],
  door: [[4.96, -0.18, 1.09, 2.0, 0, 1], [9.33, 1.75, 1.59, 2.27, ['s', 0.45], 2], [12.94, 1.75, 0.67, 1.66, 0, 1], [14.12, 1.75, 1.09, 1.68, 0, 2], [11.06, -0.77, 0.7, 1.75, 0, 1]],
  // the brick screen of the stair to the tarma
  grille: [[7.14, -0.18, 1.01, 1.84]],
  tex: brick,
  levels: [[0, '±0.00'], [2.08, '+2.08'], [6.28, '+6.28'], [7.96, '+7.96']],
};
