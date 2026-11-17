// Hashim Chalabi's house (Goriel, Molnar & Zoltan 2025, fig. 8b): a diagram of the spaces, not a wall survey,
// and without a scale; upm only sets the drawing's size. The solid is all the study did not map as a room, so
// it shows no doors or windows; where two spaces meet without a wall the opening is dashed.
export default {
  upm: 35, fs: 40, noScale: true, rot: 0,
  open: [{ at: [11.2, 7.0], t: 'o' }, { at: [5.4, 8.2], t: 'o' }],
  fix: [['line', 5.95, 13.0, 4.75, 15.6]],
  rooms: [[3.0, 13.4, 'entry', { no: '1' }], [13.6, 12.0, 'court', { no: '2' }], [6.3, 11.3, 'room', { no: '3' }], [8.2, 6.6, 'room', { no: '3' }],
    [11.5, 3.6, 'room', { no: '3' }], [21.9, 14.2, 'room', { no: '3' }], [22.2, 18.0, 'room', { no: '3' }], [17.3, 19.3, 'room', { no: '3' }],
    [15.5, 18.4, 'room', { no: '3' }], [13.8, 17.8, 'room', { no: '3' }], [10.7, 16.6, 'room', { no: '3' }]],
  entry: [1.4, 14.3, 100],
};
