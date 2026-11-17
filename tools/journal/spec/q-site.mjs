// The Qaysari's site: the Citadel and the bazaar at its foot, from OpenStreetMap (ODbL: © OpenStreetMap contributors).
// node site.mjs q-site [--fetch]. Everything within r metres of the centre; ids are OSM ways.
export default {
  center: [36.1903, 44.0094], r: 360, fs: 1300,
  fetch: [36.1860, 44.0050, 36.1940, 44.0140],   // the box asked of Overpass (south, west, north, east)
  citadel: 34133277,                             // its wall: the mound, tinted
  hatch: 121530528,                              // the Qaysari, as OSM bounds it: hatched, its buildings drawn in full
  over: [557998948],                             // Shar Park, dashed
  labels: [
    [36.1916, 44.0093, '@citadel', 'p-room'],
    [36.18865, 44.0088, '@qaysari', 'p-room'],
    [36.1887, 44.0107, '@park', 'p-note'],
    [36.18962, 44.00991, '@gate', 'p-note'],
  ],
};
