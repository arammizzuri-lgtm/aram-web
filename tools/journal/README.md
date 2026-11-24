# Journal drawings

The scripts that make the plans, sections and elevations on the journal's essay plates
(`resources/journal/*-drawings.json`), and the link preview images. Not deployed to the web
(only `public/` is served); run them locally with Node, from anywhere.

| Folder | What is in it |
| --- | --- |
| `spec/<key>.mjs` | a drawing written by hand: walls, openings, rooms, stairs (plans) or cuts and lines seen beyond (sections). Each file's header says what it is drawn from. |
| `anno/<key>.json` | what `build.mjs` reads for a plan: made from the spec by `hand.mjs`, or (for the earliest Citadel plans) written directly |
| `traces/<key>.json` | the plan's traced outline, which `build.mjs` straightens. The plans drawn wholly by hand (`q-g`, `q-f`) have a plain rectangle here. |
| `osm/<key>.json` | a site plan's buildings, from OpenStreetMap (ODbL: the plate and the sources say "© OpenStreetMap contributors") |

The scanned source plans are not kept: their licences do not allow copies. `build.mjs --pic`
(the check picture over the source) needs one, named in a `traces/<key>.job.json`.

## Change a drawing

```sh
node tools/journal/hand.mjs q-g     # plan: spec -> anno   (skip for a plan with no spec)
node tools/journal/build.mjs q-g    # plan: anno + trace -> out/q-g.json
node tools/journal/sec.mjs q-long   # section or elevation: spec -> out/q-long.json
node tools/journal/site.mjs q-site  # site plan: osm (add --fetch to ask OpenStreetMap again) -> out/q-site.json
node tools/journal/gen.mjs          # out/*.json -> resources/journal/ (q-* to qaysari, k-* to kurdistan, s-* to cooling, the rest to citadel)
```

`gen.mjs` writes every drawing in `out/`, so build them all first on a fresh checkout:

```sh
cd tools/journal && for k in $(ls anno | sed 's/.json//'); do
  if grep -q '"sec":true' anno/$k.json; then node sec.mjs $k
  elif grep -q '"map":true' anno/$k.json; then node site.mjs $k
  else [ -f spec/$k.mjs ] && node hand.mjs $k; node build.mjs $k; fi
done && node gen.mjs
```

Check: `git diff --stat resources/journal` should show only the drawings you meant to change.
(Room areas, unrounded, can move by a few hundredths of a m² between machines; the m² shown do not.)

## A new essay's link preview and its print

With the site running on port 8000 (`php artisan serve --port=8000`):

```sh
node tools/journal/og.mjs qaysari-bazaar                 # public/images/journal/qaysari-bazaar-{en,ku,ar}.jpg
node tools/journal/plates-pdf.mjs ar plates.pdf qaysari-bazaar   # the plates as the print button prints them
```

`build.mjs`, `og.mjs` and `plates-pdf.mjs` use Playwright (a dev dependency) with Chrome installed.
