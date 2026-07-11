Kurdish display font (Xoshnûs)
==============================

To use your licensed Xoshnûs Kurdish font across the whole Kurdish version
of the site, put the font file in THIS folder named exactly:

    kurdish.woff2      (preferred — smallest/fastest)
  or
    kurdish.ttf        (also works)

That's it. The site is already wired to pick it up automatically
(see the @font-face rule in public/style.css → font-family "XoshnusKurdish",
used via --font-ku). Until a file is here, Vazirmatn is used as the fallback.

Tip: if you only have a .ttf, you can convert it to .woff2 for speed, but
it is not required — .ttf works.
