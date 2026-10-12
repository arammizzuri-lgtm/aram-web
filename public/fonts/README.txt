Kurdish fonts
=============

speda.ttf    The main face for all Kurdish text on the site (--font-ku).
sirwan.ttf   The decorative face of the gold Kurdish signature on the
             English homepage.

Optional: Xoshnûs
-----------------
To add your licensed Xoshnûs font, put the file in THIS folder named exactly:

    kurdish.woff2      (preferred — smallest/fastest)
  or
    kurdish.ttf        (also works)

The site declares it automatically once the file is here (see
resources/views/partials/kurdish-font.blade.php); with no file, nothing is
requested. It sits after Speda in --font-ku, so it covers any characters
Speda lacks. To make Xoshnûs the main Kurdish face instead, move
'XoshnusKurdish' before 'Speda' in --font-ku in public/style.css and
public/project.css.
