// Salim Qaqa's house, ground floor (Mustafa & Ali 2024, fig. 4): walls from the trace, the rest read off the source.
// The notches in the thick north, west and east walls are niches (they stop short of the far face), so they stay as traced.
export default {
  upm: 56.5, fs: 40,
  cut: [[8.6, 0.55, 1.2, 0.75], [7.9, 18.5, 1.2, 1.5], [13.75, 11.6, 0.8, 1.0]],
  open: [
    // between the two north rooms: a door by the north wall, a window lower down
    { t: 'd', p: [7.33, 2.72], q: [7.19, 3.9], th: .6, hinge: [7.33, 2.72], into: [7.8, 3.3] },
    { at: [7.1, 4.7], t: 'w' },
    // the north-west room's front
    { at: [2.8, 5.9], t: 'w' }, { at: [4.4, 6.3], t: 'w' },
    // the north-east room's front
    { t: 'w', p: [8.6, 6.98], q: [9.2, 7.06], th: .8 },
    { t: 'w', p: [10.05, 7.15], q: [10.9, 7.28], th: .8 },
    // the west iwan's windows to the court
    { t: 'w', p: [4.42, 12.55], q: [4.42, 13.36], th: .42 },
    { t: 'w', p: [4.45, 14.45], q: [4.5, 15.05], th: .42 },
    { t: 'w', p: [4.5, 15.4], q: [4.5, 16.32], th: .45 },
    // the east iwan's windows to the court
    { t: 'w', p: [9.64, 11.6], q: [9.64, 12.9], th: .5 },
    { t: 'w', p: [9.64, 13.45], q: [9.64, 14.25], th: .5 },
    // the south front: two windows and the street door
    { at: [2.3, 17.5], t: 'w' }, { at: [3.7, 17.4], t: 'w' },
    { t: 'd', p: [5.72, 17.06], q: [6.43, 16.99], th: .85, hinge: [5.72, 17.06], into: [6.05, 16.3] },
  ],
  stairs: [
    { p: [1.3, 10.9], dir: -90, w: .8, n: 5, t: .26, lbl: '@up' },
    { p: [5.32, 9.95], dir: 180, w: .95, n: 5, t: .25, lbl: '@up' },
    { p: [9.0, 9.74], dir: 90, w: .72, n: 6, t: .27, lbl: '@dn' },
  ],
  // the tarma's beam from post to post, and its edge down the west side
  over: [[4.73, 9.14, 9.4, 9.76], [4.67, 9.14, 4.55, 11.48], [5.86, 14.96, 7.3, 14.96]],
  split: [[4.6, 9.0, 9.5, 9.78], [4.67, 9.0, 4.5, 11.6], [5.2, 6.6, 6.4, 7.0], [6.95, 7.0, 8.2, 6.92], [9.8, 11.2, 11.3, 11.2], [9.65, 9.85, 9.65, 10.75], [1.7, 11.6, 4.3, 11.6], [5.75, 14.96, 7.45, 14.96]],
  rooms: [[4.3, 4.2, 'room', { no: '2' }], [9.8, 5.0, 'room', { no: '2' }], [6.0, 7.9, 'tarma'], [7.1, 12.6, 'court', { no: '1' }], [3.1, 14.3, 'iwan'], [11.2, 14.0, 'iwan'], [5.95, 16.05, 'dalan']],
  court: [7.1, 12.6],
  axes: { x: [4.45, 7.2, 9.64], y: [9.2, 17.0] },
  entry: [6.07, 18.05, 0],
};
