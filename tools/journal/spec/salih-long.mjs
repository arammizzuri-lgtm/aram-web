// Salih Chalabi's house, the long section, looking at the brick front of the main range (Mustafa & Ali 2024, fig. 4, C3 section a).
// The source gives no levels on this one; scaled so its arcade, windows and roof match the cross section's (93 px to the metre):
// x = (px - 100) / 93, y = (854 - py) / 93.
const seg = (x0, x1, y, r, n = 10) => {   // points along a segmental soffit, right to left
  const w = x1 - x0, R = (w * w / 4 + r * r) / (2 * r), cx = (x0 + x1) / 2, pts = [];
  for (let i = n; i >= 0; i--) { const x = x0 + w * i / n; pts.push([+x.toFixed(3), +(y + Math.sqrt(R * R - (x - cx) ** 2) - (R - r)).toFixed(3)]); }
  return pts;
};
// the stair from the court up to the tarma, down to the left: 7 risers
const flight = [];
for (let i = 0, x = 15.05, y = 2.09; i < 7; i++) { y -= 0.307; flight.push([x, +y.toFixed(3)]); x -= 0.253; flight.push([+x.toFixed(3), +y.toFixed(3)]); }
const sur = (x, w, y = 2.41, h = 1.93) => [{ rect: [x - 0.1, y, w + 0.2, h] }, [x - 0.1, y + h - 0.16, x + w + 0.1, y + h - 0.16]];
const brick = [];
for (let y = 2.3; y < 6.2; y += 0.3) brick.push([3.82, +y.toFixed(2), 12.15, +y.toFixed(2)]);
for (let y = 6.6; y < 7.4; y += 0.2) brick.push([3.82, +y.toFixed(2), 15.05, +y.toFixed(2)]);
export default {
  fs: 30,
  cut: [
    // the ground: the court at ±0.00, the stair up to the tarma, the raised east range over a vaulted serdab
    [[-0.48, -1.12], [24.52, -1.12], [24.52, -0.6], [23.55, -0.6], [23.55, 2.09], [15.05, 2.09], ...flight, [-0.48, -0.06]],
    [[15.38, -0.6], [17.58, -0.6], [17.58, 1.44], ...seg(15.38, 17.58, 1.44, 0.54), [15.38, 1.44]],
    // the west wing: walls, roof and first floor; the upper room is the hole
    [[0, -0.06], [0, 6.44], [3.82, 6.44], [3.82, -0.06], [3.39, -0.06], [3.39, 3.65], [0.54, 3.65], [0.54, -0.06]],
    [[0.54, 3.86], [3.39, 3.86], [3.39, 6.17], [0.54, 6.17]],
    // the east range: roof over the tarma and the upper room, and its walls
    [[17.63, 4.07], [17.63, 6.17], [15.05, 6.17], [15.05, 6.44], [22.58, 6.44], [22.58, 2.09], [21.72, 2.09], [21.72, 6.17], [18.33, 6.17], [18.33, 4.07]],
  ],
  edge: [
    // the higher rooms beyond, and the brick parapet over the court with its joist ends and timber corbels
    [0, 6.44, 0, 7.46], [3.76, 6.44, 3.76, 7.46], [22.58, 6.44, 22.58, 7.46], { rect: [3.82, 6.44, 11.23, 1.02] },
    ...[5.65, 6.51, 7.37, 8.23, 9.09, 9.95, 10.81, 11.67, 12.53, 13.39].map((x) => ({ rect: [x, 6.27, 0.15, 0.17] })),
    { rect: [3.82, 6.27, 0.97, 0.17] }, { rect: [14.08, 6.27, 0.97, 0.17] }, [3.82, 6.33, 15.05, 6.33],
    // the brick front of the main range on its platform, and the low arched door to the left
    { rect: [3.82, 2.14, 8.33, 4.14] }, [5.43, 2.04, 15.05, 2.04], [5.43, 0.26, 5.43, 2.04], [3.82, 0.26, 13.28, 0.26],
    { rect: [3.82, 0.26, 1.72, 3.01] }, { arch: [3.9, 0.26, 1.45, 2.55, 'r'] },
    ...sur(6.77, 0.81), ...sur(8.44, 0.86), ...sur(10.0, 0.81),
    // the tarma: a pilaster and a column carrying two segmental arches
    { rect: [12.15, 2.14, 0.16, 2.96] }, { rect: [15.08, 2.14, 0.38, 2.96] }, { rect: [14.99, 5.1, 0.56, 0.14] },
    { arch: [12.31, 5.24, 2.77, 0.75, ['s', 0.75]] }, { arch: [15.46, 5.24, 2.17, 0.65, ['s', 0.65]] },
    { arch: [12.21, 5.24, 2.97, 0.85, ['s', 0.85]] }, { arch: [15.36, 5.24, 2.27, 0.75, ['s', 0.75]] },
    // behind it the stair to the roof through an arched opening, two windows and a door
    { arch: [12.63, 2.14, 1.03, 2.31, ['s', 0.2]] }, ...sur(14.09, 0.86, 2.14, 2.31), ...sur(15.54, 0.75, 2.14, 2.31),
    // the upper room's niches
    { arch: [18.87, 4.77, 0.7, 0.97, 'r'] }, { arch: [18.95, 4.85, 0.54, 0.75, 'r'] }, { arch: [20.48, 4.77, 0.81, 0.97, 'r'] }, { arch: [20.56, 4.85, 0.65, 0.75, 'r'] },
    { arch: [18.87, 2.57, 0.7, 1.83, 'r'] }, { arch: [20.48, 2.57, 0.75, 1.83, 'r'] },
    [18.33, -0.6, 18.33, 2.09],
  ],
  over: [[0, 7.46, 3.76, 7.46], [15.05, 7.46, 22.58, 7.46]],
  win: [
    [6.94, 4.99, 0.43, 0.54, ['s', 0.1], 1, 0], [8.6, 4.99, 0.43, 0.54, ['s', 0.1], 1, 0], [10.16, 4.99, 0.43, 0.54, ['s', 0.1], 1, 0],
    [6.77, 2.57, 0.81, 1.56, 0, 1, 2], [8.44, 2.57, 0.86, 1.56, 0, 1, 2], [10.0, 2.57, 0.81, 1.56, 0, 1, 2],
    ...[12.96, 14.3, 15.64, 16.99].map((x) => [x, 4.99, 0.48, 0.54, ['s', 0.1], 1, 0]),
    [14.09, 2.41, 0.86, 1.72, 0, 1, 2], [15.54, 2.41, 0.75, 1.72, 0, 1, 2],
    // windows through the cut walls
    [0, 4.13, 0.54, 1.61, 0, 0, 0], [21.72, 2.41, 0.32, 2.0, 0, 0, 0],
  ],
  door: [[3.98, 0.26, 1.29, 2.36, 'r', 2], [16.72, 2.14, 0.86, 1.99, ['s', 0.15], 2], [0.7, 3.86, 0.86, 1.83, 0, 2]],
  steps: [[12.74, 2.14, 0.107, 0.229, 8, 1]],
  tex: brick,
  levels: [[0, '±0.00'], [2.14, '+2.15'], [6.44, '+6.40'], [7.46, '+7.40']],
};
