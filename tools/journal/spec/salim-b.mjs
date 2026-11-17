// Salim Qaqa's house, basement (Mustafa & Ali 2024, fig. 4): walls from the trace, the rest read off the source.
export default {
  upm: 54.7, fs: 40,
  cut: [[8.5, 0.3, 1.1, 0.6], [8.3, 18.7, 1.4, 1.5], [14.0, 11.5, 1.5, 1.2], [-1.2, 12.5, 1.0, 1.5]],
  open: [
    // the arcade across the north room: five arches
    { t: 'o', p: [2.18, 6.13], q: [3.67, 6.37], th: .83 },
    { at: [5.0, 6.6], t: 'o' }, { at: [6.6, 6.75], t: 'o' }, { at: [8.4, 7.0], t: 'o' }, { at: [10.0, 7.0], t: 'o' },
    // into the room under the court, and on to the rooms round it
    { at: [5.4, 8.97], t: 'o' }, { at: [8.2, 9.2], t: 'o' }, { at: [3.0, 12.5], t: 'o' }, { at: [10.0, 13.3], t: 'o' },
    { t: 'o', p: [10.1, 10.85], q: [11.55, 10.85], th: .65 },
    { at: [4.75, 15.4], t: 'w' },
    { t: 'w', p: [4.66, 13.0], q: [4.7, 13.8], th: .4 },
    { t: 'w', p: [4.85, 15.9], q: [4.9, 16.85], th: .45 },
    // the double door out to the lower lane
    { t: 'dd', p: [5.27, 17.7], q: [8.14, 17.26], th: .8, into: [6.7, 16.6] },
  ],
  stairs: [
    { p: [9.27, 11.5], dir: -90, w: .7, n: 8, t: .23, lbl: '@up' },
    { p: [11.42, 14.35], dir: -90, w: .75, n: 3, t: .17 },
  ],
  // the court's stair overhead, on the floor above
  over: [[4.06, 10.4, 5.6, 10.4, 5.6, 11.39, 4.06, 11.39, 4.06, 10.4], [4.45, 10.4, 4.45, 11.39], [4.83, 10.4, 4.83, 11.39], [5.21, 10.4, 5.21, 11.39]],
  split: [[6.25, 15.65, 7.95, 15.6], [11.0, 14.45, 11.9, 14.35]],
  rooms: [[7.0, 4.2, 'serdab'], [7.0, 7.95, 'dalan'], [7.2, 13.2, 'room'], [3.3, 15.2, 'room'], [11.85, 12.4, 'room'], [12.1, 15.25, 'store'], [6.9, 16.55, 'entry']],
  axes: { x: [4.66, 9.85], y: [6.3, 9.0] },
  entry: [6.6, 18.45, 0],
};
