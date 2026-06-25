'use strict';
// Run once: node build-border.js
// Downloads province + district GeoJSON from geoBoundaries (OSM-based),
// filters to Kurdish territories, dissolves into one outer polygon,
// writes kurdistan.geojson

const fetch       = require('node-fetch');
const polygonClip = require('polygon-clipping');
const fs          = require('fs');

const COMMIT = '9469f09';
const GB = (iso3, level) =>
    `https://github.com/wmgeolab/geoBoundaries/raw/${COMMIT}/releaseData/gbOpen/${iso3}/ADM${level}/geoBoundaries-${iso3}-ADM${level}.geojson`;

// ── ADM1 — full provinces ───────────────────────────────────────────────────
const ADM1_SOURCES = [
    { url: GB('TUR', 1), country: 'Turkey' },
    { url: GB('IRN', 1), country: 'Iran' },
    { url: GB('IRQ', 1), country: 'Iraq' },
    { url: GB('SYR', 1), country: 'Syria' },
];

const KURDISH_ADM1_ISO = new Set([
    // Turkey — SE/S Kurdish provinces
    'TR-02', // Adıyaman
    'TR-04', // Ağrı
    'TR-12', // Bingöl
    'TR-13', // Bitlis
    'TR-21', // Diyarbakır
    'TR-23', // Elazığ
    'TR-24', // Erzincan
    'TR-25', // Erzurum
    'TR-27', // Gaziantep (NEW)
    'TR-30', // Hakkâri
    'TR-31', // Hatay / Iskenderun (NEW)
    'TR-36', // Kars
    'TR-44', // Malatya
    'TR-47', // Mardin
    'TR-49', // Muş
    'TR-56', // Siirt
    'TR-62', // Tunceli
    'TR-63', // Şanlıurfa
    'TR-65', // Van
    'TR-72', // Batman
    'TR-73', // Şırnak
    'TR-76', // Iğdır
    'TR-79', // Kilis (NEW)
    // Iran — Kurdish + adjacent provinces
    // NOTE: geoBoundaries codes differ from ISO 3166-2 for Iran — verified from actual data:
    'IR-02', // West Azerbaijan
    'IR-05', // Ilam
    'IR-10', // Khuzestan
    'IR-16', // Kurdistan Province
    'IR-17', // Kermanshah
    'IR-20', // Lorestan  (ISO IR-15 maps to Kerman in geoBoundaries — use IR-20)
    'IR-24', // Hamadan   (ISO IR-13 maps to Sistan-Baluchestan in geoBoundaries — use IR-24)
    // Iraq — Kurdistan Region + Kirkuk (ADM1 only; Nineveh/Diyala handled at ADM2)
    'IQ-AR', // Erbil
    'IQ-DA', // Dohuk
    'IQ-KI', // Kirkuk
    'IQ-SU', // Al-Sulaymaniyah
    // Syria — Al-Hasakeh ADM1; Kobani/Afrin handled at ADM2
    'SYR-HA',
]);

const KURDISH_ADM1_NAMES = [
    // Turkey
    /adiyaman/i, /ağrı/i, /agri/i, /bingöl/i, /bingol/i, /bitlis/i,
    /diyarbakır/i, /diyarbakir/i, /elazığ/i, /elazig/i, /erzincan/i,
    /erzurum/i, /gaziantep/i, /hakkari/i, /hatay/i, /iskenderun/i,
    /iğdır/i, /igdir/i, /kars/i, /kilis\b/i, /malatya/i,
    /mardin/i, /muş/i, /mus\b/i, /siirt/i, /şırnak/i, /sirnak/i,
    /tunceli/i, /şanlıurfa/i, /sanliurfa/i, /van\b/i, /batman/i,
    // Iran
    /west azerbaijan/i, /azerbaycan.i garbi/i,
    /kurdistan province/i, /kordestan/i, /kordestān/i,
    /kermanshah/i, /kermānshāh/i,
    /ilam/i, /īlām/i,
    /^khuzestan$/i, /^khūzestān$/i, /^xuzestan$/i,
    /^hamadan$/i, /^hamadān$/i,
    /^lorestan$/i, /^lorestān$/i, /^lurestan$/i,
    // Iraq ADM1
    /erbil/i, /arbil/i, /hewler/i,
    /duhok/i, /dahuk/i, /dihok/i,
    /sulaymaniyah/i, /suleimaniyah/i, /as sulaymaniyah/i, /halabja/i,
    /kirkuk/i, /ta.mim/i, /tamim/i,
    // Syria ADM1
    /al.hasakah/i, /hasaka/i,
];

function isKurdishADM1(f) {
    const iso  = f.properties.shapeISO  || '';
    const name = f.properties.shapeName || '';
    if (iso && KURDISH_ADM1_ISO.has(iso)) return true;
    return KURDISH_ADM1_NAMES.some(re => re.test(name));
}

// ── ADM2 — specific districts within Nineveh, Diyala, and Aleppo ───────────
const ADM2_SOURCES = [
    { url: GB('IRQ', 2), country: 'Iraq (districts)' },
    { url: GB('SYR', 2), country: 'Syria (districts)' },
];

const KURDISH_ADM2_NAMES = [
    // ── Nineveh (Mosul) — Kurdish/Yazidi districts north of Mosul city ──────
    /sinjar/i,                                // Sinjar (Yazidi homeland, W Nineveh)
    /tal.?afar/i, /telafar/i, /tel.?afar/i,  // Tal Afar (NW Nineveh)
    /tilkaif/i, /telkef/i, /tal.?kayf/i,     // Tilkaif (N of Mosul, E bank Tigris)
    /sheikhan/i, /shaikhan/i, /shikhan/i,    // Sheikhan (Yazidi, NE Nineveh)
    /bashiqa/i, /ba.?shiqa/i,                // Bashiqa (NE of Mosul)
    /hamdaniyah/i, /al.hamdaniyah/i,         // Hamdaniyah / Bakhdida (Nineveh plains)
    /akre/i, /aqrah/i, /\baqra\b/i,          // Akre/Aqrah (N Nineveh, Kurdish)
    /ain.?sifni/i, /ayn.?sifni/i,            // Ain Sifni (Yazidi, near Sheikhan)
    /zummar/i,                                // Zummar (NW Nineveh near Syria border)
    // Al-Ba'aj and Al-Hatra removed — contain Khunaifis, Ayn al-jahesh and non-Kurdish villages
    // ── Saladin — Kurdish/Turkmen districts ─────────────────────────────────
    /tooz.?khurmato/i, /tuz.?khurmat/i,     // Tooz Khurmato / Tuz Khurmatu (incl. Amerli town)
    // ── Diyala — Kurdish subdistricts along Iranian border ──────────────────
    /khanaqin/i, /khanakin/i, /khanaqen/i,   // Khanaqin (NE Diyala, Kurdish)
    /kifri/i,                                 // Kifri (N Diyala)
    /jalawla/i, /jalula/i, /jala.?la/i,      // Jalawla (N Diyala)
    /mandali/i,                               // Mandali (E Diyala)
    /kani.?masi/i, /kanimas/i,               // Kani Masi (if appears at ADM2)
    /baba.?guyi/i, /bawanur/i, /goy.?sinjaq/i, // Baba Guyi / Goy Sanjaq area
    /muqdadiyah/i,                            // Muqdadiyah district (NE Diyala edge)
    // ── Syria — Kobani and Afrin cantons in Aleppo governorate ──────────────
    /kobani/i, /kobane/i,
    /ain.?al.?arab/i, /ayn.?al.?arab/i,     // Kobani's Arabic name
    /afrin/i, /a.?frin/i, /afreen/i,        // Afrin canton
];

function isKurdishADM2(f) {
    const name = f.properties.shapeName || '';
    return KURDISH_ADM2_NAMES.some(re => re.test(name));
}

// ── Main ────────────────────────────────────────────────────────────────────
(async () => {
    const chosen = [];

    // ADM1 — full provinces
    for (const { url, country } of ADM1_SOURCES) {
        console.log(`\nFetching ADM1 — ${country}…`);
        const resp = await fetch(url, {
            headers: { 'User-Agent': 'kurdistan-border-builder/1.0' },
            timeout: 120000,
        });
        if (!resp.ok) throw new Error(`${country} HTTP ${resp.status}`);
        const data = await resp.json();
        const hits = data.features.filter(isKurdishADM1);
        console.log(`  ${hits.length}/${data.features.length} provinces selected`);
        hits.forEach(f => console.log(`    [${f.properties.shapeISO || '??'}] ${f.properties.shapeName}`));
        chosen.push(...hits);
    }

    // ADM2 — specific districts
    for (const { url, country } of ADM2_SOURCES) {
        console.log(`\nFetching ADM2 — ${country}…`);
        let resp;
        try {
            resp = await fetch(url, {
                headers: { 'User-Agent': 'kurdistan-border-builder/1.0' },
                timeout: 120000,
            });
        } catch (e) {
            console.warn(`  SKIPPED (${e.message})`);
            continue;
        }
        if (!resp.ok) {
            console.warn(`  SKIPPED — HTTP ${resp.status}`);
            continue;
        }
        const data = await resp.json();
        const hits = data.features.filter(isKurdishADM2);
        console.log(`  ${hits.length}/${data.features.length} districts selected`);
        hits.forEach(f => console.log(`    [${f.properties.shapeISO || '??'}] ${f.properties.shapeName}`));
        chosen.push(...hits);
    }

    if (chosen.length === 0) throw new Error('No features matched');

    console.log(`\nDissolving ${chosen.length} features into outer boundary…`);
    const allRings = chosen.flatMap(f =>
        f.geometry.type === 'Polygon'
            ? [f.geometry.coordinates]
            : f.geometry.coordinates
    );

    const unified = polygonClip.union(...allRings);

    // Strip all inner rings (holes) — any land completely enclosed by Kurdistan
    // territory is considered part of Kurdistan and should be filled in.
    const noHoles = unified.map(poly => [poly[0]]);

    // Re-union after hole removal: polygons that previously touched only through
    // a hole boundary may now be fully adjacent and can be merged.
    const merged = noHoles.length > 1 ? polygonClip.union(...noHoles) : noHoles;

    const result  = {
        type: 'Feature',
        geometry: {
            type:        merged.length === 1 ? 'Polygon' : 'MultiPolygon',
            coordinates: merged.length === 1 ? merged[0] : merged,
        },
        properties: { name: 'Greater Kurdistan' },
    };

    fs.writeFileSync('kurdistan.geojson', JSON.stringify(result));
    const kb = Math.round(fs.statSync('kurdistan.geojson').size / 1024);
    console.log(`\n✓  Wrote kurdistan.geojson  (${kb} KB,  ${merged.length} polygon(s) after hole-fill)`);
})().catch(err => { console.error('\nFAILED:', err.message); process.exit(1); });
