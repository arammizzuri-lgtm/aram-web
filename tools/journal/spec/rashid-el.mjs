// Rashid Agha's house, the courtyard elevation (Mustafa & Ali 2024, fig. 4, C1 elevation a), redrawn.
// Levels as the source gives them; lengths scaled from them (87 px to the metre in the 3428 px figure).
export default {
  fs: 30,
  // the court paving, cut
  cut: [[-0.9, -0.4, 14.1, 0.4]],
  edge: [
    // the outline: the main range (left) and the plain east range (right), both to +7.48
    [0, 0, 0, 7.48, 12.33, 7.48, 12.33, 0], [7.64, 1.93, 7.64, 7.48],
    // the cornice of the main range, and the parapet of the east range
    [0, 6.55, 7.64, 6.55], [0, 6.95, 7.64, 6.95], [7.64, 7.15, 12.33, 7.15],
    // the tarma upstairs: piers with capitals carrying round arches
    { rect: [0.29, 4.23, 0.48, 1.17] }, { rect: [2.47, 4.23, 0.48, 1.17] }, { rect: [4.6, 2.36, 0.4, 3.04] },
    [0.2, 5.4, 0.86, 5.4], [2.38, 5.4, 3.04, 5.4], [4.52, 5.4, 5.08, 5.4],
    { arch: [0.77, 5.4, 1.7, 0.98, 'r'] }, { arch: [2.95, 5.4, 1.65, 0.96, 'r'] },
    // the stair block in front, with its arched way through
    [0, 4.23, 2.99, 4.23, 2.99, 0], { arch: [0.44, 0, 1.92, 2.0, ['s', 0.95]] },
    // the pilaster, and the tall arch of the iwan bay around the door
    [5.1, 1.93, 5.1, 6.55], [5.75, 1.93, 5.75, 6.55], { arch: [5.83, 1.93, 1.76, 4.48, 'r'] },
    { rect: [6.09, 1.93, 1.23, 0.39] },
    // the parapet wall along the front of the tarma, and the east range's base
    [2.99, 1.93, 12.33, 1.93],
  ],
  win: [
    [1.3, 5.23, 0.7, 0.8, 0, 0, 0], [3.74, 5.23, 0.69, 0.8, 0, 0, 0],   // the upper rooms' small windows, behind the arches
    [3.66, 2.76, 0.88, 1.84, 'r', 1, 2],                            // tall coloured-glass window of the main range
    [3.43, 0.98, 0.74, 0.69, 0, 2, 1],
    [1.15, 1.03, 1.03, 0.92, 0, 3, 1],                              // seen through the arch
    [8.79, 1.93, 0.87, 1.4, 'r', 1, 1], [10.0, 1.93, 0.86, 1.4, 'r', 1, 1],
    [9.14, 4.71, 0.4, 0.4, 'r', 0, 0], [10.34, 4.71, 0.46, 0.4, 'r', 0, 0],
  ],
  door: [[6.24, 2.32, 0.94, 2.16, 'r', 2]],
  grille: [[4.6, 0.98, 2.01, 0.88], [7.16, 0.98, 1.69, 0.88], [7.99, 6.21, 1.73, 0.57], [10.18, 6.21, 1.73, 0.57]],
  // the east range's brick patterns: hourglasses under the screens, lozenges between the niches; the cornice courses
  tex: [
    [8.1, 6.15, 9.0, 5.35, 8.1, 5.35, 9.0, 6.15, 8.1, 6.15], [9.4, 6.15, 10.3, 5.35, 9.4, 5.35, 10.3, 6.15, 9.4, 6.15], [10.7, 6.15, 11.6, 5.35, 10.7, 5.35, 11.6, 6.15, 10.7, 6.15],
    [7.99, 4.94, 8.27, 5.14, 8.56, 4.94, 8.27, 4.74, 7.99, 4.94], [9.66, 4.94, 9.95, 5.14, 10.23, 4.94, 9.95, 4.74, 9.66, 4.94], [11.38, 4.94, 11.66, 5.14, 11.95, 4.94, 11.66, 4.74, 11.38, 4.94],
    [1.1, 3.25, 1.35, 3.45, 1.6, 3.25, 1.35, 3.05, 1.1, 3.25],
    [0, 6.7, 7.64, 6.7], [0, 6.83, 7.64, 6.83], [0, 7.1, 7.64, 7.1], [0, 7.25, 7.64, 7.25], [0, 7.38, 7.64, 7.38],
  ],
  steps: [[0.6, 0, 0.3, 0.19, 4, 1]],
  levels: [[0, '±0.00'], [0.95, '+0.95'], [2.1, '+2.10'], [4.25, '+4.25'], [6.98, '+6.98'], [7.48, '+7.48']],
};
