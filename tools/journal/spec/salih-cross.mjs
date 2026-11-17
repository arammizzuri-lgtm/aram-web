// Salih Chalabi's house, the cross section facing the arcade (Mustafa & Ali 2024, fig. 4, C3 section b), redrawn.
// Scaled from the source's ±0.00, −0.50, +6.40 and +7.40, which agree (111 px to the metre); its first-floor tag
// does not sit on its line, so that level is read off the drawing. x = (px - 155) / 111, y = (913 - py) / 111.
const seg = (x0, x1, y, r, n = 10) => {   // points along a segmental soffit, right to left
  const w = x1 - x0, R = (w * w / 4 + r * r) / (2 * r), cx = (x0 + x1) / 2, pts = [];
  for (let i = n; i >= 0; i--) { const x = x0 + w * i / n; pts.push([+x.toFixed(3), +(y + Math.sqrt(R * R - (x - cx) ** 2) - (R - r)).toFixed(3)]); }
  return pts;
};
const sur = (x, w) => [{ rect: [x - 0.12, 2.46, w + 0.24, 2.03] }, [x - 0.12, 4.31, x + w + 0.12, 4.31]];   // a window's stone surround and head
const brick = [];
for (let y = 6.55; y < 7.4; y += 0.15) brick.push([4.82, y, 13.38, y]);
const treads = [];
for (let y = 0.27; y < 1.6; y += 0.27) treads.push([8.15, y, 10.04, y]);
export default {
  fs: 30,
  cut: [
    // the ground: the west wing's floor half a metre down, the court at ±0.00
    [[-0.95, -1.01], [5.32, -1.01], [5.32, -0.5], [17.57, -0.5], [17.57, 0], [4.82, 0], [4.82, -0.5], [-0.95, -0.5]],
    // the west wing: walls, roof and the first floor over a vaulted room; the upper room is the hole
    [[0, -0.5], [0, 6.42], [4.82, 6.42], [4.82, -0.5], [4.01, -0.5], ...seg(0.9, 4.01, 1.51, 0.5), [0.9, -0.5]],
    [[0.9, 2.16], [4.01, 2.16], [4.01, 6.2], [0.9, 6.2]],
    // the east wing the same way
    [[13.38, 0], [13.38, 6.42], [16.53, 6.42], [16.53, 0], [15.63, 0], [15.63, 1.51], [13.6, 1.51], [13.6, 0]],
    [[13.6, 2.16], [15.63, 2.16], [15.63, 6.2], [13.6, 6.2]],
  ],
  edge: [
    // the higher rooms beyond, and the brick parapet over the arcade with its timber corbels
    [0, 6.42, 0, 7.46], [16.53, 6.42, 16.53, 7.46], { rect: [4.82, 6.42, 8.56, 1.04] }, { rect: [4.82, 6.42, 0.99, 0.16] }, { rect: [12.39, 6.42, 0.99, 0.16] },
    // the arcade: three segmental arches on two stone columns and two half-columns, a tie over each column
    [4.82, 6.33, 13.38, 6.33],
    { arch: [5.0, 5.46, 2.52, 0.72, ['s', 0.72]] }, { arch: [7.9, 5.46, 2.44, 0.72, ['s', 0.72]] }, { arch: [10.72, 5.46, 2.48, 0.72, ['s', 0.72]] },
    { arch: [4.9, 5.46, 2.72, 0.82, ['s', 0.82]] }, { arch: [7.8, 5.46, 2.64, 0.82, ['s', 0.82]] }, { arch: [10.62, 5.46, 2.68, 0.82, ['s', 0.82]] },
    { rect: [7.52, 2.16, 0.38, 3.3] }, { rect: [7.43, 5.32, 0.56, 0.14] }, { rect: [7.43, 2.16, 0.56, 0.12] }, [7.71, 5.46, 7.71, 6.33],
    { rect: [10.34, 2.16, 0.38, 3.3] }, { rect: [10.25, 5.32, 0.56, 0.14] }, { rect: [10.25, 2.16, 0.56, 0.12] }, [10.53, 5.46, 10.53, 6.33],
    { rect: [4.82, 2.16, 0.18, 3.3] }, { rect: [13.2, 2.16, 0.18, 3.3] },
    // stone surrounds round the main rooms' windows and doors
    ...sur(5.18, 0.8), ...sur(6.62, 0.75), ...sur(10.9, 0.72), ...sur(12.35, 0.76),
    { rect: [7.93, 2.16, 0.98, 2.33] }, { rect: [9.36, 2.16, 0.98, 2.33] },
    // the tarma's podium with the steps up its middle
    [4.82, 2.01, 13.38, 2.01], [4.82, 0.39, 8.15, 0.39], [10.04, 0.39, 13.38, 0.39],
    { rect: [8.15, 0, 1.89, 1.6] }, { rect: [8.6, 1.6, 0.99, 0.56] }, [8.6, 1.88, 9.59, 1.88],
    // arched surrounds of the podium's windows, and of the upper rooms' doors
    { arch: [5.6, 0.39, 0.98, 1.33, ['s', 0.2]] }, { arch: [11.67, 0.39, 0.95, 1.33, ['s', 0.2]] },
    { arch: [1.4, 2.16, 1.08, 2.1, ['s', 0.25]] }, { arch: [13.74, 2.16, 1.08, 2.1, ['s', 0.25]] },
    // the vaulted room under the west wing: an inner arch and a few steps
    { arch: [1.71, -0.5, 2.3, 2.25, ['s', 0.45]] },
  ],
  over: [[0, 7.46, 4.82, 7.46], [13.38, 7.46, 16.53, 7.46]],
  win: [
    [5.32, 5.03, 0.45, 0.58, 0, 1, 0], [6.76, 5.03, 0.5, 0.58, 0, 1, 0], [10.9, 5.03, 0.54, 0.58, 0, 1, 0], [12.39, 5.03, 0.53, 0.58, 0, 1, 0],
    [5.18, 2.59, 0.8, 1.54, 0, 1, 2], [6.62, 2.59, 0.75, 1.54, 0, 1, 2], [10.9, 2.59, 0.72, 1.54, 0, 1, 2], [12.35, 2.59, 0.76, 1.54, 0, 1, 2],
    [5.72, 0.57, 0.72, 1.08, ['s', 0.15], 1, 2], [11.76, 0.57, 0.72, 1.08, ['s', 0.15], 1, 2],
    [2.93, 2.59, 0.76, 1.54, 0, 1, 2],
    // windows through the cut walls
    [0, 2.46, 0.9, 1.58, 0, 0, 0], [4.01, 4.98, 0.81, 0.63, 0, 0, 0], [4.01, 2.64, 0.81, 1.53, 0, 0, 0], [4.01, 0.43, 0.81, 1.08, 0, 0, 0],
    [13.38, 4.98, 0.22, 0.63, 0, 0, 0], [13.38, 2.16, 0.22, 2.0, 0, 0, 0],
  ],
  door: [[7.97, 2.16, 0.86, 1.97, ['s', 0.2], 2], [9.41, 2.16, 0.88, 1.97, ['s', 0.2], 2], [1.49, 2.16, 0.9, 1.97, ['s', 0.2], 2], [13.83, 2.16, 0.9, 1.97, ['s', 0.2], 2]],
  steps: [[4.01, -0.5, 0.15, 0.15, 3, -1]],
  tex: [...brick, ...treads],
  levels: [[-0.5, '−0.50'], [0, '±0.00'], [2.16, '+2.15'], [6.42, '+6.40'], [7.46, '+7.40']],
};
