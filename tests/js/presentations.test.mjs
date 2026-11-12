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

test('the words a page prints itself follow its language; what was typed does not change', () => {
    const ku = m.normalize({ lang: 'ku' }), ar = m.normalize({ lang: 'ar' });
    assert.equal(m.normalize({ lang: 'fr' }).lang, 'en');
    assert.equal(m.word(m.normalize({}), 'scale'), 'Scale');
    assert.equal(m.word(ku, 'scale'), 'پێوەر');
    assert.equal(m.word(ar, 'contents'), 'المحتويات');
    assert.equal(m.sectionName('Floor Plans', 'ku'), 'نەخشەی نهۆمەکان');
    assert.equal(m.sectionName('Floor Plans', 'en'), 'Floor Plans');
    assert.equal(m.sectionName('My own section', 'ku'), 'My own section');
    assert.equal(m.fmtDate('2026-11-12', 'ku'), '١٢ تشرینی دووەم ٢٠٢٦');
    assert.equal(m.fmtDate('2026-01-05', 'ar'), '٥ كانون الثاني ٢٠٢٦');
    assert.equal(m.fmtDate('2026-11-12'), '12 November 2026');
    assert.equal(m.fmtDate('soon', 'ku'), 'soon');
});

test('a page given another layout keeps its pictures, words and added elements', () => {
    const page = m.newPage('two-up', { title: 'Views', opts: { textPos: 'side' }, size: { w: 1, h: 1 },
        free: [{ id: 'f1', t: 'text', text: 'Note' }], ov: { title: { sz: 1.4 }, 'free:f1': { f: [0, 0, 0.2, 0.1] } } });
    page.images[0].asset = 'render'; page.images[1].asset = 'plan'; page.images[1].page = 2;
    const p = m.relayout(page, 'image-full', (h) => h === 'plan');
    assert.equal(p.layout, 'image-full');
    assert.equal(p.title, 'Views');
    assert.deepEqual(p.images.map((s) => s.asset), ['render']);
    assert.deepEqual(Object.keys(p.ov), ['free:f1']);
    assert.deepEqual(p.opts, {});
    assert.equal(p.size, undefined);
    assert.equal(p.free.length, 1);
    const g = m.relayout(page, 'grid', (h) => h === 'plan');
    assert.deepEqual(g.images.map((s) => s.asset), ['render', 'plan', null, null]);
    assert.equal(g.images[1].fit, 'fit');   // a PDF drawing is never cropped
    assert.equal(g.images[1].page, 2);
    assert.equal(page.layout, 'two-up');    // the page itself is left as it was
});

test('the next revision follows the last', () => {
    assert.equal(m.nextRevision(''), 'A');
    assert.equal(m.nextRevision(undefined), 'A');
    assert.equal(m.nextRevision('A'), 'B');
    assert.equal(m.nextRevision(' c '), 'd');
    assert.equal(m.nextRevision('9'), '10');
    assert.equal(m.nextRevision('Z'), '');
    assert.equal(m.nextRevision('P1'), '');
});

test('a presentation made from a website project starts with its pictures in pages', () => {
    const doc = m.normalize({ lang: 'ku', theme: 'editorial', seed: { cover: 'c', images: ['a', 'c', 'b', 'd', 'e', 7], body: 'The brief' } });
    const pages = m.starterPages(doc);
    assert.deepEqual(pages.map((p) => p.layout), ['cover', 'overview', 'image-full', 'image-full', 'closing']);
    assert.equal(pages[0].images[0].asset, 'c');
    assert.equal(pages[1].title, m.word(doc, 'overview'));
    assert.equal(pages[1].body, 'The brief');
    assert.deepEqual(pages[1].images.map((s) => s.asset), ['a', 'b']);
    assert.deepEqual(pages.slice(2, 4).map((p) => p.images[0].asset), ['d', 'e']);
    assert.deepEqual(m.starterPages(m.normalize({})).map((p) => p.layout), ['cover', 'overview', 'closing']);
});

test('the drawing index lists the drawing pages with their numbers, names and pages', async () => {
    const { layoutPage } = await import('@pm/layout');
    const assets = new Map([['pdf1', { hash: 'pdf1', kind: 'pdf', name: 'GF_plan-final.pdf', meta: { pages: [{ w: 2384, h: 1684 }] } }]]);
    const sheet = m.newPage('sheet', { drawing: { number: 'A-102' } });
    sheet.images[0].asset = 'pdf1'; sheet.images[0].page = 1;
    const doc = m.normalize({ lang: 'en', pages: [m.newPage('drawing-index'), m.newPage('drawing', { title: 'Ground Floor Plan', drawing: { number: 'A-101' } }), sheet, m.newPage('image-full')] });
    const lay = layoutPage(doc, 0, { brand: {}, assets, edit: false, logoRatio: () => 1 });
    const cell = (key) => lay.items.filter((it) => it.key === key).flatMap((it) => it.lines.flatMap((l) => l.runs.map((r) => r.text))).join('').trim();
    assert.match(cell('list:h0'), /^drawing no/i);
    assert.deepEqual([cell('list:0:0'), cell('list:0:1'), cell('list:0:3')], ['A-101', 'Ground Floor Plan', '2']);
    assert.deepEqual([cell('list:1:0'), cell('list:1:1'), cell('list:1:3')], ['A-102', 'GF plan final', '3']);
    assert.equal(cell('list:2:0'), '');   // the image page is not a drawing
    assert.ok(lay.frames.list);
});

test('pictures of the pages are packed in a zip that any unzipper reads', async () => {
    const x = await import('@pm/export');
    assert.equal(x.crc32(new TextEncoder().encode('The quick brown fox jumps over the lazy dog')), 0x414FA339);
    const files = [{ name: 'Villa — 01.jpg', data: new Uint8Array([1, 2, 3]) }, { name: 'b.jpg', data: new Uint8Array(0) }];
    const zip = new Uint8Array(await x.zipStore(files).arrayBuffer());
    const v = new DataView(zip.buffer), name = new TextEncoder().encode(files[0].name);
    assert.equal(v.getUint32(0, true), 0x04034b50);
    assert.deepEqual([...zip.slice(30 + name.length, 30 + name.length + 3)], [1, 2, 3]);   // stored as it is
    const end = zip.length - 22;
    assert.equal(v.getUint32(end, true), 0x06054b50);
    assert.equal(v.getUint16(end + 10, true), 2);
    const dir = v.getUint32(end + 16, true);
    assert.equal(v.getUint32(dir, true), 0x02014b50);
    assert.equal(v.getUint32(dir + 16, true), x.crc32(files[0].data));
    assert.equal(dir + v.getUint32(end + 12, true), end);
});
