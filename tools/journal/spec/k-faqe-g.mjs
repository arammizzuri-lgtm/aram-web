// Faqe Shally's house, Sulaimani, ground floor (Abdulla & Qaradaghi 2025, fig. 14: the original layout before the house
// became a museum, as the authors reconstructed it, CC BY 4.0). The figure is at 1:100 with its widths dimensioned
// (3.10, 3.10, 3.30, 3.10, 3.20, 20.00 m), so this is to scale; read off it at 55 px a metre. Plan y runs south-east,
// to the court; fs as Salim Qaqa's house (salim-g), so the two share a scale on one plate.
export default {
  upm: 100, rot: 0, fs: 40, north: -45, wallH: 700,
  walls: [
    // the range: back wall with the stores' and the stair's windows, front wall to the court
    [[0, 0.345], [20, 0.345], 0.69, [[5.83, 6.65, 'w'], [8.3, 11.56, 'w'], [13.21, 14.02, 'w']], [0, 0]],
    [[0, 8.345], [20, 8.345], 0.71, [[1.03, 2.16, 'w'], [2.64, 3.7, 'w'], [5.04, 7.03, 'o'], [7.66, 9.6, 'o'], [10.22, 12.21, 'o'], [12.83, 14.8, 'o'], [16.21, 17.26, 'w'], [17.79, 18.88, 'w']], [0, 0]],
    [[0.4, 0], [0.4, 8.7], 0.8, [], [0, 0]],
    [[19.575, 0], [19.575, 8.7], 0.85, [], [0, 0]],
    // the service room's wall, its door to the rewaq
    [[4.27, 0.69], [4.27, 7.99], 0.78, [[5.16, 6.16, 'dd', 0, 1]], [0, 0]],
    [[8.015, 0.69], [8.015, 5.72], 0.49, [], [0, 0]],
    [[4.66, 5.495], [8.26, 5.495], 0.45, [[0.45, 1.45, 'dd', 0, -1], [1.84, 2.89, 'w']], [0, 0]],
    [[11.795, 0.69], [11.795, 5.72], 0.47, [], [0, 0]],
    [[11.56, 5.495], [15.16, 5.495], 0.45, [[0.61, 1.66, 'w'], [2.3, 3.11, 'dd', 0, -1]], [0, 0]],
    // the kitchen's wall, its door to the rewaq
    [[15.55, 0.69], [15.55, 7.99], 0.78, [[5.07, 5.98, 'dd', 0, -1]], [0, 0]],
    // the court's walls, the gate in the south-west one
    [[0.255, 8.7], [0.255, 24.69], 0.51, [[8.75, 10.56, 'dd', 0, -1]], [0, 0]],
    [[19.745, 8.7], [19.745, 24.69], 0.51, [], [0, 0]],
    [[0, 24.465], [20, 24.465], 0.45, [], [0, 0]],
  ],
  // the stair turns at its head and lands upstairs on the iwan
  stairs: [{ p: [8.85, 2.72], dir: -90, w: 1.1, n: 6, t: 0.28, lbl: '@up' }, { p: [10.95, 0.95], dir: 90, w: 1.1, n: 6, t: 0.28 }],
  fix: [['tree', 3.88, 21.7, 2.0], ['tree', 7.77, 22.97, 0.8], ['tree', 15.92, 11.2, 0.8]],
  rooms: [
    [9.9, 6.95, 'rewaq', { no: '3' }], [6.2, 3.0, 'store', { no: '4' }], [13.6, 3.0, 'store', { no: '4' }],
    [2.34, 4.3, 'room', { no: '4' }], [17.53, 4.3, 'kitchen', { no: '4' }], [10.0, 16.6, 'court', { no: '1' }],
  ],
  court: [10.0, 16.6],
  entry: [0.62, 18.35, 90],
  axes: { x: [0.4, 4.27, 8.015, 11.795, 15.55, 19.575], y: [0.345, 8.345, 24.465] },
};
