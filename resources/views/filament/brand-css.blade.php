<style>
@import url('https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,500;0,600;0,700&display=swap');

/* ───────── Login page ───────── */
.fi-simple-layout {
    background:
        radial-gradient(135% 75% at 50% -20%, rgba(245, 197, 24, 0.14), transparent 55%),
        #080808 !important;
}
.fi-simple-page {
    border: none;
    box-shadow: 0 40px 90px -30px rgba(0, 0, 0, 0.85);
}
.fi-simple-header { text-align: center; border: none !important; }
.aram-eyebrow {
    display: block; font-size: 0.66rem; letter-spacing: 0.42em; text-transform: uppercase;
    color: #c9a000; font-weight: 600; margin-bottom: 0.85rem;
}
.aram-brand {
    display: block; font-family: 'Playfair Display', Georgia, serif;
    font-size: 2.25rem; line-height: 1.05; font-weight: 600; color: #f4f1ea;
}
.aram-brand::after {
    content: ''; display: block; width: 48px; height: 2px; margin: 1rem auto 0;
    background: linear-gradient(90deg, transparent, #f5c518, transparent);
}
.aram-sub { color: #8c8c8c; font-size: 0.9rem; letter-spacing: 0.02em; }

/* ───────── Global admin polish ───────── */
.fi-body, .fi-simple-layout { background-color: #0a0a0a; }
.fi-sidebar { background-color: #0d0d0d !important; }
.fi-sidebar .fi-logo {
    font-family: 'Playfair Display', Georgia, serif !important;
    font-weight: 600; color: #f4f1ea !important;
}

/* ───────── Dashboard greeting ───────── */
.aram-hero {
    display: flex; align-items: center; justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;
    padding: 1.6rem 1.85rem; border-radius: 1rem;
    background:
        radial-gradient(120% 180% at 0% 0%, rgba(245, 197, 24, 0.12), transparent 42%),
        linear-gradient(180deg, rgba(26, 26, 26, 0.9), rgba(13, 13, 13, 0.92));
    border: 1px solid rgba(245, 197, 24, 0.15);
}
.aram-hero__eyebrow { font-size: 0.72rem; letter-spacing: 0.18em; text-transform: uppercase; color: #c9a000; margin: 0 0 0.4rem; }
.aram-hero__title { font-family: 'Playfair Display', Georgia, serif; font-size: 2rem; line-height: 1.08; color: #f4f1ea; margin: 0; font-weight: 600; }
.aram-hero__sub { color: #9a9a9a; margin: 0.45rem 0 0; font-size: 0.92rem; }
.aram-hero__cta {
    display: inline-flex; align-items: center; gap: 0.45rem; white-space: nowrap;
    padding: 0.62rem 1.15rem; border-radius: 0.6rem; font-weight: 600; font-size: 0.88rem;
    color: #0a0a0a; background: #f5c518; text-decoration: none; transition: transform .15s ease, box-shadow .15s ease;
}
.aram-hero__cta:hover { transform: translateY(-1px); box-shadow: 0 10px 26px -8px rgba(245, 197, 24, 0.5); }

/* ───────── Recent-messages card ───────── */
.aram-card { background: rgba(20, 20, 20, 0.6); border: 1px solid rgba(255, 255, 255, 0.06); border-radius: 1rem; padding: 1.2rem 1.35rem; height: 100%; }
.aram-card__head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.3rem; }
.aram-card__title { font-size: 0.95rem; font-weight: 600; color: #ededed; margin: 0; }
.aram-card__link { font-size: 0.8rem; color: #c9a000; text-decoration: none; }
.aram-card__link:hover { color: #f5c518; }
.aram-msg { display: flex; align-items: flex-start; gap: 0.7rem; padding: 0.7rem 0.15rem; border-top: 1px solid rgba(255, 255, 255, 0.05); text-decoration: none; }
.aram-msg__dot { width: 8px; height: 8px; border-radius: 50%; background: #f5c518; margin-top: 0.45rem; flex: none; box-shadow: 0 0 8px rgba(245, 197, 24, 0.6); }
.aram-msg__dot.is-read { background: #444; box-shadow: none; }
.aram-msg__body { display: flex; flex-direction: column; min-width: 0; }
.aram-msg__name { color: #e7e7e7; font-weight: 500; font-size: 0.9rem; transition: color .15s; }
.aram-msg__meta { color: #888; font-size: 0.78rem; }
.aram-msg__empty { color: #888; font-size: 0.86rem; padding: 0.9rem 0 0.3rem; }

/* ───────── Insight tables (most viewed projects, traffic sources) ───────── */
.aram-card__note { font-size: 0.75rem; color: #777; }
.aram-card__foot { margin-top: 0.9rem; font-size: 0.74rem; line-height: 1.5; color: #777; }
.aram-card__foot code { color: #c9a000; font-size: 0.72rem; }
.aram-stat-table { width: 100%; border-collapse: collapse; margin-top: 0.6rem; font-size: 0.86rem; }
.aram-stat-table th { text-align: left; font-weight: 500; font-size: 0.72rem; letter-spacing: 0.04em; text-transform: uppercase; color: #777; padding: 0.35rem 0.4rem 0.55rem; }
.aram-stat-table td { padding: 0.6rem 0.4rem; border-top: 1px solid rgba(255, 255, 255, 0.05); vertical-align: top; color: #d8d8d8; }
.aram-stat-table .is-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; width: 1%; }
.aram-stat-table td.is-good { color: #4ecb71; font-weight: 600; }
.aram-stat-table__name { display: block; color: #e7e7e7; font-weight: 500; text-decoration: none; }
a.aram-stat-table__name:hover { color: #f5c518; }
.aram-stat-table__sub { display: block; margin-top: 0.25rem; font-size: 0.74rem; color: #777; }
.aram-bar { display: block; height: 4px; margin-top: 0.4rem; border-radius: 4px; background: rgba(255, 255, 255, 0.05); overflow: hidden; }
.aram-bar > span { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg, #c9a000, #f5c518); }
</style>
