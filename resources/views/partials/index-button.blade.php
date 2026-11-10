{{-- The Index button, in the homepage's bar and every other page's: it
     opens the sheet index (partials/site-index, site-index.js). The mark is
     a sheet set seen from above — four sheets. --}}
<button class="sx-open{{ isset($class) ? ' '.$class : '' }}" type="button" data-index-open aria-haspopup="dialog" aria-controls="siteIndex" aria-expanded="false">
    <svg class="sx-open__icon" viewBox="0 0 12 12" aria-hidden="true"><rect x=".6" y=".6" width="4.4" height="4.4"/><rect x="7" y=".6" width="4.4" height="4.4"/><rect x=".6" y="7" width="4.4" height="4.4"/><rect x="7" y="7" width="4.4" height="4.4"/></svg>
    <span class="sx-open__label">{{ bival('nav_index') }}</span>
</button>
