// The presentation editor's logic that needs no browser. Run with:  node --test tests/js/
// (The pages and the PDF themselves are checked in a real browser.)
import test from 'node:test';
import assert from 'node:assert/strict';
import { register } from 'node:module';

// the editor's modules name each other "@pm/…" (an import map in the page); here that is the folder
register('data:text/javascript,' + encodeURIComponent(`
    const base = ${JSON.stringify(new URL('../../public/js/presentations/', import.meta.url).href)};
    export async function resolve(spec, context, next) {
        return spec.startsWith('@pm/') ? { url: base + spec.slice(4) + '.js', shortCircuit: true } : next(spec, context);
    }`));
const m = await import('@pm/model');
const t = await import('@pm/text');

test('linked fields fill in, and an empty one leaves no dangling separator', () => {
    const doc = m.normalize({ info: { project: 'Ankawa Villa', stage: '', date: '2026-11-12', plotArea: '1250', revision: 'B' } });
    const tok = m.tokens(doc, { studio: 'Aram Mizuri' }, { page: '3', pages: '12' });
    assert.equal(m.fillTokens('{project} · {stage}', tok), 'Ankawa Villa');
    assert.equal(m.fillTokens('{stage} · {project} · {client}', tok), 'Ankawa Villa');
    assert.equal(m.fillTokens('{studio} — {page} / {pages}', tok), 'Aram Mizuri — 3 / 12');
    assert.equal(m.fillTokens('{date}\n{plotArea}', tok), '12 November 2026\n1,250 m²');
    assert.equal(m.fillTokens('{unknown} stays', tok), '{unknown} stays');
    assert.equal(m.fileName(doc, 'x'), 'Ankawa Villa - 2026-11-12 - Rev B');
    assert.equal(m.fileName(m.normalize({ info: { project: 'A/B: "C"?' } }), 'x'), 'A B C');
});

test('a page range is read strictly', () => {
    assert.deepEqual(m.parseRange('1-3, 7', 10), [0, 1, 2, 6]);
    assert.deepEqual(m.parseRange('7,2,2', 10), [1, 6]);
    for (const bad of ['', '0', '5-2', '11', 'a', '1-', '1;2']) assert.equal(m.parseRange(bad, 10), null, bad);
});

test('a scale is printed only when it is true of the printed page', () => {
    const a1 = [841, 594];
    assert.equal(m.scaleLabel('1:100', 1, a1), '1:100');
    assert.equal(m.scaleLabel('1/50', 1.002, a1), '1:50');
    assert.equal(m.scaleLabel('1:100', 0.5, a1), '1:100 @ A1');          // reduced from a standard sheet
    assert.equal(m.scaleLabel('1:100', 0.5, [800, 600]), null);          // reduced from an odd sheet: nothing
    assert.equal(m.scaleLabel('about 1:100', 1, a1), null);              // not a scale
    assert.equal(m.scaleLabel('1:100', 1, null), null);                  // an image: no sheet size to trust
    assert.equal(m.isoName(594, 841), 'A1');
    assert.equal(m.isoName(420, 297), 'A3');
    assert.equal(m.isoName(600, 800), null);
});

test('a stored document is made whole, whatever came back from storage', () => {
    const doc = m.normalize({ theme: 'editorial', pages: [{ layout: 'cover', ov: [], opts: [], images: [] }, { layout: 'gone', title: 'kept' }, { layout: 'grid' }] });
    assert.equal(doc.theme.name, 'Editorial architecture');
    assert.equal(doc.size, 'A3');
    assert.deepEqual(doc.pages.map((p) => p.layout), ['cover', 'blank', 'grid']);
    assert.ok(!Array.isArray(doc.pages[0].ov) && !Array.isArray(doc.pages[0].opts));   // a map, even if PHP sent a list
    assert.equal(doc.pages[0].images.length, 1);
    assert.equal(doc.pages[1].title, 'kept');
    assert.equal(doc.pages[2].images.length, 4);
    assert.equal(m.resolveTheme('saved:7', [{ type: 'theme', id: 7, data: { name: 'Mine', colors: { bg: '#000000' } } }]).colors.text, '#141414');   // gaps filled
});

test('sections and numbers follow the dividers', () => {
    const doc = m.normalize({ pages: [{ layout: 'cover' }, { layout: 'divider', title: 'Floor Plans' }, { layout: 'drawing' }, { layout: 'divider', title: 'Renders' }, { layout: 'image-full' }] });
    assert.deepEqual(m.structure(doc).map((s) => [s.no, s.section, s.dividerNo]), [[1, '', 0], [2, 'Floor Plans', 1], [3, 'Floor Plans', 0], [4, 'Renders', 2], [5, 'Renders', 0]]);
    const a3 = m.pageSize(doc, doc.pages[0]);
    assert.deepEqual([m.mm(a3.w), m.mm(a3.h)], [420, 297]);
    assert.deepEqual(Object.values(m.pageSize(doc, { orient: 'portrait' })).slice(0, 2).map(m.mm), [297, 420]);
    assert.deepEqual(m.pageSize(doc, { size: { w: 2384, h: 1684 } }), { w: 2384, h: 1684, own: true });
    assert.deepEqual(m.sourceSize({ kind: 'image', meta: { w: 4000, h: 3000, orientation: 6 } }), { w: 3000, h: 4000 });   // a turned photo
    assert.deepEqual(m.sourceSize({ kind: 'pdf', meta: { pages: [{ w: 10, h: 20 }, { w: 30, h: 40 }] } }, 2), { w: 30, h: 40 });
});

test('fonts fall to the nearest weight that is embedded', () => {
    assert.equal(m.fontKey('Jost', 300), 'jost-300');
    assert.equal(m.fontKey('Bebas Neue', 600), 'bebas-400');
    assert.equal(m.fontKey('Cormorant Garamond', 400, true), 'cormorant-400i');
    assert.equal(m.fontKey('Nothing', 400), 'inter-400');
});

test('text is split into runs of one direction, Kurdish in its own font', () => {
    const shape = (s) => t.runs(s, 'jost-300').map((r) => [r.text, r.key === 'speda-400' ? 'speda' : 'latin', r.rtl ? 'rtl' : 'ltr']);
    assert.deepEqual(shape('Ankawa Villa'), [['Ankawa Villa', 'latin', 'ltr']]);
    assert.deepEqual(shape('خانووی عەنکاوە'), [['خانووی عەنکاوە', 'speda', 'rtl']]);
    // their digits read left to right inside a right-to-left line; a space between two of the same side joins them
    assert.deepEqual(shape('ڕووبەر ٤٨٠ م'), [['ڕووبەر ', 'speda', 'rtl'], ['٤٨٠', 'speda', 'ltr'], [' م', 'speda', 'rtl']]);
    assert.deepEqual(shape('First Floor — نهۆمی'), [['First Floor — ', 'latin', 'ltr'], ['نهۆمی', 'speda', 'rtl']]);
    assert.equal(t.isRtl('نهۆمی — First'), true);
    assert.equal(t.isRtl('12 — نهۆمی'), false);   // starts with a Latin-side character
});

test('lines break at words within the width, and a right-to-left line is laid from the right', () => {
    // with no canvas every character measures 50 at size 100 — so widths here are exact
    const style = { key: 'inter-400', size: 10, lh: 1.5, ls: 0 };
    const lay = t.layoutText('aaaa bbbb cccc', style, 50);   // 4 letters = 20, "aaaa bbbb" = 45
    assert.deepEqual(lay.lines.map((l) => l.runs.map((r) => r.text).join('')), ['aaaa bbbb', 'cccc']);
    assert.equal(lay.h, 30);
    assert.equal(t.layoutText('one\ntwo', style, 500).lines.length, 2);
    const spaced = t.layoutText('ABCD', { ...style, ls: 0.1 }, 500);   // spacing between letters, not after the last
    assert.equal(spaced.w, 4 * 5 + 3 * 1);
    const mixed = t.layoutText('نهۆمی First', style, 500).lines[0];
    assert.equal(mixed.rtl, true);
    assert.deepEqual(mixed.runs.map((r) => r.text), ['First', 'نهۆمی ']);   // placed left to right: the Latin word sits on the left
    assert.equal(mixed.runs[0].x, 0);
});
