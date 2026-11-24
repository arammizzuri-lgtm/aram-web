// The sun in a court, in section: a diagram, not a survey. A court house of Amed's kind cut north to south (south on the
// left), its sizes ours but within what the sources give: a one-storey summer range on the south, its iwan open to the
// north and a serdab under it; a court 9 m across; a two-storey winter range on the north, walls 60 cm (Bekleyen &
// Dalkilic 2012: 50-60 cm stone, earth roofs 30-50 cm). The rays are the noon sun at Amed's latitude, 37.9 N: 90 - 37.9
// + 23.44 = 75.5 degrees on 21 June, 90 - 37.9 - 23.44 = 28.7 on 21 December. y up from the court, x from the south face.
const T75 = Math.tan(75.5 * Math.PI / 180), T29 = Math.tan(28.7 * Math.PI / 180), TOP = 9.5;
// a ray of the noon sun at angle tan t, drawn from the top of the drawing down to the point (x, y) where it stops
const ray = (t, x, y) => [+(x - (TOP - y) / t).toFixed(3), TOP, +x.toFixed(3), +y.toFixed(3)];
export default {
  fs: 30,
  cut: [
    // the ground, with the serdab and the opening of its stair as holes
    [-2.5, -2.9, 25.0, 2.9], [0.6, -2.6, 3.8, 2.2], [3.0, -0.4, 1.0, 0.4],
    // the summer range: its back wall, the iwan's roof and the parapets; the iwan has no front wall
    [[0, 0], [0.6, 0], [0.6, 4.5], [5.0, 4.5], [5.0, 5.5], [4.75, 5.5], [4.75, 4.9], [0.6, 4.9], [0.6, 5.5], [0, 5.5]],
    // the winter range, two storeys, the rooms as holes in it
    [[14.0, 0], [19.0, 0], [19.0, 8.4], [18.75, 8.4], [18.75, 7.8], [14.25, 7.8], [14.25, 8.4], [14.0, 8.4]],
    [14.6, 0, 3.8, 3.6], [14.6, 4.0, 3.8, 3.4],
  ],
  // the court's far side, beyond, as high as the summer range
  edge: [[5.0, 0, 5.0, 4.5], [5.0, 4.5, 14.0, 4.5]],
  win: [[14.0, 0.9, 0.6, 2.0, 0, 0, 0], [14.0, 4.9, 0.6, 2.0, 0, 0, 0], [18.4, 5.6, 0.6, 1.0, 0, 0, 0]],
  // down from the iwan to the serdab
  steps: [[1.2, -2.6, 0.27, 0.26, 9, 1]],
  over: [
    // 21 June: past the iwan's parapet (5.0, +5.50) to the court floor, and in at the winter room's window head
    // (14.0, +2.90), stopping on its sill within the wall
    ray(T75, 5.0 + 5.5 / T75, 0), ray(T75, 14.0 + 2.0 / T75, 0.9),
    // 21 December: past the same parapet to the foot of the winter range, and in at the same window head to the back wall
    ray(T29, 14.0, 5.5 - 9.0 * T29), ray(T29, 18.4, 2.9 - 4.4 * T29),
  ],
  rooms: [
    [2.7, 2.0, '@iwan'], [3.5, -2.1, '@serdab'], [9.5, 1.2, '@court'], [16.5, 1.6, '@room'], [16.5, 5.5, '@room'], [16.5, 8.05, '@roof'],
    // the two noons, by their angles, at the top of their rays
    [+(5.0 - (TOP - 5.5) / T75 - 0.75).toFixed(2), TOP - 0.35, '75.5°'], [+(5.0 - (TOP - 5.5) / T29 + 0.2).toFixed(2), TOP - 0.55, '28.7°'],
    [-0.5, 0.25, 'S'], [19.5, 0.25, 'N'],
  ],
  levels: [[-2.6, '−2.60'], [0, '±0.00'], [4.5, '+4.50'], [7.4, '+7.40']],
};
