// House 19/2 (nomination file fig. 2.50, from the 2011 master plan). The figure has no scale, so no
// dimensions or areas: upm only sets the drawing's size. The watermark "19/2" hid part of the walls.
export default {
  upm: 60, fs: 45, noScale: true,
  // the partition and the east room's front under the watermark
  add: [[[5.45, 10.0], [6.4, 10.1], [6.3, 12.3], [5.35, 12.3]], [7.9, 10.5, 0.55, 0.75], [[9.1, 10.6], [9.95, 10.9], [9.65, 11.6], [9.1, 11.35]]],
  open: [
    { t: 'dd', p: [6.5, 0.8], q: [7.65, 1.85], th: .4, into: [6.7, 1.8] },
    { t: 'w', p: [5.2, 0.5], q: [6.35, 0.75], th: .35 },
    // the north room: two windows and a door to the court
    { at: [8.3, 3.2], t: 'w' }, { t: 'w', p: [8.25, 4.1], q: [8.05, 4.8], th: .3 },
    { t: 'd', p: [7.8, 5.65], q: [8.85, 5.8], th: .3, hinge: [7.8, 5.65], into: [8.6, 5.0] },
    { t: 'w', p: [9.6, 5.85], q: [10.6, 6.05], th: .3 },
    // the two rooms of the main wing, each with its own door
    { t: 'd', p: [3.5, 9.75], q: [4.55, 9.8], th: .4, hinge: [4.55, 9.8], into: [4.3, 10.6] },
    { t: 'w', p: [4.85, 9.85], q: [5.5, 10.1], th: .35 },
    { t: 'd', p: [6.9, 10.6], q: [7.9, 10.7], th: .7, hinge: [7.9, 10.85], into: [7.4, 11.8] },
    { t: 'w', p: [8.8, 9.45], q: [8.6, 10.55], th: .45 },
    // the small room behind the stair
    { t: 'd', p: [9.15, 8.15], q: [10.55, 8.35], th: .3, hinge: [10.55, 8.35], into: [10.0, 9.4] },
    { at: [1.9, 7.9], t: 'w' }, { at: [9.0, 7.0], t: 'n' },
  ],
  stairs: [{ p: [9.0, 6.6], dir: 10, w: 1.1, n: 6, t: .32, lbl: '@up' }],
  over: [[3.9, 6.65, 9.0, 8.0], [3.9, 6.95, 9.0, 8.3]],
  split: [[8.95, 5.8, 9.1, 8.1]],
  rooms: [[3.9, 13.2, 'room', { no: '1' }], [8.0, 14.3, 'room', { no: '1' }], [5.6, 4.8, 'court', { no: '2' }], [10.0, 4.2, 'room', { no: '5' }], [10.3, 9.8, 'room', { no: '5' }]],
  court: [5.6, 4.8], tags: [[7.9, 0.9, '3'], [10.4, 7.5, '4']],
  entry: [7.05, 1.3, 215],
};
