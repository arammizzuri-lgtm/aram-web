# UI / UX Design System & Screen Specifications

> **Phase 1 — foundation, 2026-08-03.** The token layer was audited against WCAG
> and rebuilt. Read this section before adding a screen; the rest of Part A is
> the original specification and still stands.
>
> ### Every colour is now measured
>
> `php tools/contrast.php` checks all forty token/surface pairs in both themes
> and exits non-zero on a failure. **Run it after touching a colour.** It found
> nine failures on first use, the worst of them in the theme actually in use:
>
> | Token | Was | Now | Why it mattered |
> |---|---|---|---|
> | `--erp-critical` (dark) | 3.72:1 | 4.62:1 | Inherited from the light theme. This is the colour the overdue figure and every negative profit is printed in. |
> | `--erp-text-muted` | 3.38:1 / 3.70:1 | 4.63:1 / 4.57:1 | Every hint and column label in the system. |
> | `--erp-warning` | 1.83:1 | see below | Invisible as a figure on white. |
> | `--erp-serious`, `--erp-good` | 2.64 / 3.35 | 4.55 / 4.74 | Used as text. |
> | `--erp-series-3/4/5` (light) | 2.17–2.82 | 3.07–3.13 | Chart series below the non-text floor. |
>
> The dark block now **restates all four statuses in full**, even where the value
> is unchanged. A status block that inherits half its colours from the other
> theme is exactly how the first failure happened.
>
> ### Status colours come in pairs
>
> A colour needs **3:1** to work as a shape and **4.5:1** to work as text, and no
> single yellow clears both. So each status is two tokens:
>
> ```
> --erp-warning        the fill  — bars, dots, chart segments
> --erp-warning-text   the text  — same hue, walked down until it can be read
> ```
>
> Use `.erp-good` / `.erp-warning` / `.erp-serious` / `.erp-critical` as classes
> for text; they resolve to the `-text` variant. Only lightness moves between a
> pair — the palette's identity is its hues, and an accessible palette that no
> longer looks like itself has traded one problem for another.
>
> ### Scales, so screens stop choosing
>
> Type is six named steps — `display`, `figure`, `title`, `label`, `hint`, body —
> named for the job rather than the size. Spacing is three: `--erp-pad` inside a
> card, `--erp-gap` between cards, `--erp-gap-tight` within a group.
>
> ### Build the page out of the components
>
> Nine views were hand-writing `rounded-xl border` with the colours inlined —
> Filament's own `.fi-section` rule, restated nine times and free to drift nine
> ways. Use these instead:
>
> | | |
> |---|---|
> | `<x-erp.card title hint flush>` | Any panel. `flush` when the content draws its own edges, such as a table. |
> | `<x-erp.figure label value hint lead tone>` | A figure with its label and meaning. **One `lead` per screen** — if everything is emphasised, nothing is. |
> | `<x-erp.empty title>` | Nothing-here-yet. A blank panel reads as a failure to load. |
> | `.erp-figures` | A hairline-separated row of figures that wraps rather than scrolls. |
>
> ### Two things that will bite
>
> **A new Blade view needs `npm run build`.** Tailwind compiles only the classes
> it can find at build time; ship a view without rebuilding and it arrives with
> its colours and no layout.
>
> **Colour is for the figure that asks to be acted on.** A customer owing you
> money is ordinary business. Painting every open account red spends the alarm on
> nothing, and the one number that matters stops being visible.

---

# PART A — The Design System

The target is Linear/Stripe/Notion: quiet surfaces, one accent, generous spacing, sharp
typography, motion that confirms rather than decorates. Everything below is expressed as
design tokens, implemented in `resources/css/filament/admin/theme.css` and consumed by
Filament's CSS-variable theming.

## A1. Colour

### Neutrals — the 95% of the interface

Cool-neutral grey. Nothing in the UI is pure black; nothing is pure white except card
surfaces in light mode.

| Token | Light | Dark | Use |
|---|---|---|---|
| `--bg-page` | `#f7f7f8` | `#0d0d0f` | App background |
| `--bg-surface` | `#ffffff` | `#17171a` | Cards, tables, modals |
| `--bg-surface-2` | `#fafafa` | `#1e1e22` | Nested panels, table headers |
| `--bg-hover` | `rgba(0,0,0,.035)` | `rgba(255,255,255,.05)` | Row / control hover |
| `--bg-sunken` | `#f0f0f2` | `#08080a` | Wells, code, empty states |
| `--border` | `rgba(9,9,11,.09)` | `rgba(255,255,255,.10)` | Hairlines — **always 1px** |
| `--border-strong` | `rgba(9,9,11,.16)` | `rgba(255,255,255,.18)` | Focused inputs, dividers |
| `--text-primary` | `#0b0b0c` | `#f5f5f6` | Headings, values |
| `--text-secondary` | `#52525b` | `#a1a1aa` | Body, labels |
| `--text-muted` | `#8b8b94` | `#71717a` | Axis ticks, captions, placeholders |

### Accent

**Indigo**, replacing Filament's default amber (which reads generic and fights the data
colours). One accent only — used for primary action, active navigation and focus rings.
Nothing else in the UI is indigo.

| Token | Light | Dark |
|---|---|---|
| `--accent` | `#4f46e5` | `#6366f1` |
| `--accent-hover` | `#4338ca` | `#818cf8` |
| `--accent-subtle` | `#eef2ff` | `rgba(99,102,241,.14)` |
| `--focus-ring` | `#4f46e5` @ 2px offset 2px | `#818cf8` |

### Status — reserved, never reused as a series colour

| Role | Hex | Meaning in this ERP |
|---|---|---|
| good | `#0ca30c` | Paid · Cleared · Delivered · In stock · Final costing |
| warning | `#fab219` | Due soon · Low stock · Estimated costing · Partially paid |
| serious | `#ec835a` | In transit · Awaiting customs · Pending approval |
| critical | `#d03b3b` | Overdue · Out of stock · Credit limit breached · Failed import |

On the light surface, `warning` and `serious` fall below 3:1. **They are always shipped
with an icon + text label** — colour never carries the meaning alone.

### Data-visualisation palette — validated, do not improvise

Categorical hues in **fixed slot order** (a 4th series is always slot 4, regardless of how
many series are on screen — colour follows the entity, never its rank):

| Slot | Hue | Light | Dark |
|---|---|---|---|
| 1 | blue | `#2a78d6` | `#3987e5` |
| 2 | orange | `#eb6834` | `#d95926` |
| 3 | aqua | `#1baf7a` | `#199e70` |
| 4 | yellow | `#eda100` | `#c98500` |
| 5 | magenta | `#e87ba4` | `#d55181` |
| 6 | green | `#008300` | `#008300` |
| 7 | violet | `#4a3aa7` | `#9085e9` |
| 8 | red | `#e34948` | `#e66767` |

Validated against this system's own surfaces (`#ffffff` / `#17171a`):

```
LIGHT  lightness band PASS · chroma PASS · CVD adjacent ΔE 9.1 PASS
       normal-vision ΔE 19.6 PASS · contrast WARN (aqua 2.82, yellow 2.17, magenta 2.69)
DARK   all six checks PASS · CVD adjacent ΔE 8.4 · contrast all ≥ 3:1
```

Binding consequences:

- **The light-mode contrast WARN is not dismissable.** Any chart using aqua, yellow or
  magenta on the light surface ships **visible direct labels or a table view**.
- **Scatter / bubble / choropleth / small-multiple charts cap at 3 series** (slots 1–3),
  which is the subset that clears the all-pairs gate in both modes. Beyond three, fold to
  "Other" or facet.
- Bar, line, area and stacked charts use the adjacent-pair gate and may run to 8 slots.
- A 9th series never gets a generated colour — it becomes "Other".

**Sequential** (magnitude — heatmaps, inventory-value maps): single blue hue, light→dark,
steps `#cde2fb → #0d366b`. Ordinal ramps start no lighter than `#86b6ef` on light and no
darker than `#184f95` on dark.

**Diverging** (polarity — margin above/below target, price change up/down, stock variance):
blue ↔ red with a **grey** midpoint (`#f0efec` light / `#383835` dark). Never a hue at the
midpoint; never a rainbow.

### Chart chrome

| Role | Light | Dark |
|---|---|---|
| Gridline | `#e1e0d9` | `#2c2c2a` |
| Baseline / axis | `#c3c2b7` | `#383835` |
| Axis tick text | `#898781` | `#898781` |
| Delta ↑ good | `#006300` | `#0ca30c` |

## A2. Typography

`Inter` (variable), fallback `system-ui, -apple-system, "Segoe UI", sans-serif`.
For Arabic and Kurdish: `IBM Plex Sans Arabic`, which pairs with Inter's metrics.

| Token | Size / line-height | Weight | Tracking | Use |
|---|---|---|---|---|
| `display` | 34 / 40 | 600 | −0.02em | Dashboard hero figures |
| `h1` | 24 / 32 | 600 | −0.015em | Page titles |
| `h2` | 18 / 26 | 600 | −0.01em | Section headers, card titles |
| `h3` | 15 / 22 | 600 | 0 | Sub-sections |
| `body` | 14 / 21 | 400 | 0 | Default |
| `body-medium` | 14 / 21 | 500 | 0 | Table values, labels |
| `small` | 13 / 19 | 400 | 0 | Secondary/help text |
| `caption` | 12 / 16 | 500 | 0.01em | Axis ticks, metadata |
| `overline` | 11 / 14 | 600 | 0.06em, uppercase | Stat-tile labels, group headers |
| `mono` | 13 / 20 | 450 | 0 | SKU, container no., B/L — `JetBrains Mono` |

**Numeric rules — non-negotiable in an ERP:**
- Every money and quantity column uses `font-variant-numeric: tabular-nums` and is
  **right-aligned**. Decimals must line up down a column or the eye can't scan it.
- Hero figures on stat tiles use default proportional figures (they stand alone).
- Money renders as `$18,300.00` / `IQD 24,150,000` — currency code always visible when more
  than one currency can appear on a screen.
- Negative values: `−$1,240.00` (true minus, not hyphen), in `critical`, never in parentheses.

## A3. Spacing, radius, elevation

4px base scale: `4 · 8 · 12 · 16 · 20 · 24 · 32 · 40 · 48 · 64`.

| Token | Value |
|---|---|
| `--radius-sm` | 6px — badges, small inputs |
| `--radius-md` | 8px — buttons, inputs |
| `--radius-lg` | 12px — cards, panels |
| `--radius-xl` | 16px — modals, drawers |

**Elevation is borders, not shadows.** Cards get a 1px hairline and no shadow. Only
genuinely floating layers cast one:

```
--shadow-popover: 0 4px 12px rgba(9,9,11,.07), 0 1px 3px rgba(9,9,11,.05)
--shadow-modal:   0 16px 48px rgba(9,9,11,.14), 0 2px 8px rgba(9,9,11,.06)
```

This is the single biggest visual difference between "admin panel" and "premium product":
flat surfaces separated by hairlines, not a field of drop shadows.

**Density.** Table rows 44px default, 36px in a "compact" mode toggled per user and
remembered. Page gutters 32px desktop / 16px mobile. Max content width 1600px.

## A4. Motion

Fast, purposeful, and honest about what happened.

| Interaction | Duration | Easing |
|---|---|---|
| Hover / colour change | 120ms | `ease-out` |
| Dropdown, popover | 150ms | `cubic-bezier(.16,1,.3,1)` |
| Modal, drawer | 220ms | `cubic-bezier(.16,1,.3,1)` |
| Page transition | 180ms | `ease-out` |
| Number count-up (stat tiles) | 600ms | `ease-out`, once on load only |
| Toast in/out | 200ms / 150ms | `ease-out` / `ease-in` |
| Skeleton shimmer | 1400ms | `linear`, infinite |

Rules: nothing animates longer than 250ms except the deliberate stat count-up. No bounce,
no spring overshoot. Everything respects `prefers-reduced-motion: reduce` — transforms
become opacity-only, count-up is skipped.

## A5. Core components

**Stat tile** — the dashboard's atom.
```
┌──────────────────────────────────┐
│ NET PROFIT             (overline)│  11px 600 uppercase, muted
│                                  │
│ $48,320.00              (display)│  34px 600, tabular off
│ ↑ 12.4% vs last month            │  13px, good/critical + arrow icon
│ ▁▂▃▅▆▇                           │  optional 32px sparkline, accent, no axis
└──────────────────────────────────┘
```
1px hairline, 12px radius, 20px padding. Never more than 4 across on desktop.

**Status pill** — 11px 600 uppercase, 6px radius, 2/8px padding. Tinted background at 12%
of the status hue, text at full strength, plus a 6px dot. Icon + text always.

**Money cell** — right-aligned, tabular, primary ink. Secondary currency (if shown) on a
second line in `--text-muted` at 12px.

**Product cell** — 32px thumbnail · name (body-medium) over SKU (mono, 12px, muted).

**Empty state** — line icon, one-sentence explanation, one primary action. Never a bare
"No records".

**Command palette (⌘K)** — the fastest path to anything. See §B15.

## A6. Layout shell

```
┌──────────────────────────────────────────────────────────────────────────┐
│ ┌────────────┐  ┌──────────────────────────────────────────────────────┐ │
│ │            │  │  ⌘K Search…            🌓  🔔 3   USD ▾   Aram ▾     │ │ 56px topbar
│ │  ACME      │  ├──────────────────────────────────────────────────────┤ │
│ │  IMPORTS   │  │                                                      │ │
│ │            │  │   Page title                        [ Primary CTA ]  │ │
│ │ ▸ Overview │  │   ────────────────────────────────────────────────   │ │
│ │   Catalog  │  │                                                      │ │
│ │   Purchase │  │   content                                            │ │
│ │   Logistics│  │                                                      │ │
│ │   Sales    │  │                                                      │ │
│ │   Inventory│  │                                                      │ │
│ │   Finance  │  │                                                      │ │
│ │   Reports  │  │                                                      │ │
│ │   Settings │  │                                                      │ │
│ │            │  │                                                      │ │
│ │ ─────────  │  │                                                      │ │
│ │ ⚡ AI Ask   │  │                                                      │ │
│ └────────────┘  └──────────────────────────────────────────────────────┘ │
│    248px                                                                  │
└──────────────────────────────────────────────────────────────────────────┘
```

Sidebar collapses to 64px icons (⌘\) and to an overlay drawer below 1024px. Navigation
groups map to Filament Clusters. The currency switcher in the topbar re-presents every
figure on screen in USD or IQD at today's rate — a display conversion, never a data change.

## A7. Responsive & accessibility

| Breakpoint | Behaviour |
|---|---|
| ≥1536px | 4-column dashboard grid, full tables |
| 1280–1535px | 4-column, tables drop low-priority columns |
| 1024–1279px | 3-column, sidebar icon-only |
| 768–1023px | 2-column, sidebar as drawer |
| <768px | Single column; tables become stacked cards; primary action becomes a FAB |

- Contrast: body text ≥ 4.5:1, large text and UI ≥ 3:1.
- Every interactive element is keyboard reachable with a visible 2px accent focus ring.
- Charts: legend present for ≥2 series; ≤4 series also direct-labelled; every chart has a
  **"View as table"** toggle; texture fill available under `forced-colors` and print.
- Full RTL support (`dir="rtl"`) for Arabic/Kurdish — layout mirrors, numbers do not.
- Icons that carry meaning always have a text label or `aria-label`.

---

# PART B — Screen Specifications

## B1. Dashboard — "The Morning Screen"

The one screen you open first. Structured around the cash-conversion cycle, not vanity
metrics.

```
Overview                                 [ Aug 2026 ▾ ]  [ USD ▾ ]  [ Export ]
──────────────────────────────────────────────────────────────────────────────

┌ ATTENTION ────────────────────────────────────────────────────────────────┐
│ ⚠ 2 shipments awaiting final costing (oldest 38 days)          [Review →] │
│ ⚠ 6 invoices overdue · $18,420                                 [Review →] │
│ ⚠ 11 products below reorder level                              [Review →] │
└───────────────────────────────────────────────────────────────────────────┘   only renders if non-empty

┌────────────────┐┌────────────────┐┌────────────────┐┌────────────────┐
│ REVENUE MTD    ││ GROSS PROFIT   ││ NET PROFIT     ││ CASH POSITION  │
│ $124,800       ││ $38,240        ││ $21,110        ││ $63,940        │
│ ↑ 12.4%  ▁▂▃▅▇ ││ 30.6% margin   ││ ↑ 4.1%   ▁▃▂▅▆ ││ ↓ 8.2%   ▇▆▅▃▂ │
└────────────────┘└────────────────┘└────────────────┘└────────────────┘

┌────────────────┐┌────────────────┐┌────────────────┐┌────────────────┐
│ INVENTORY VALUE││ GOODS IN TRANSIT││ RECEIVABLES   ││ PAYABLES       │
│ $286,400       ││ $94,200        ││ $71,830        ││ $52,400        │
│ 1,847 SKUs     ││ 3 containers   ││ $18,420 overdue││ $12,000 due 7d │
└────────────────┘└────────────────┘└────────────────┘└────────────────┘

┌─ Revenue vs Cost of Goods ───────────────┐┌─ Containers in Transit ────────┐
│  grouped bars, 12 months                 ││  ● SHP-2026-014  Ningbo→Umm Qasr│
│  slot1 revenue · slot2 COGS              ││    ETA 12 Aug · in transit  ▓▓▓░│
│  line overlay: margin % (same axis,      ││  ● SHP-2026-015  customs   ▓▓▓▓░│
│  indexed — never a second y-axis)        ││  ● SHP-2026-016  booked    ▓░░░░│
│  [View as table]                         ││                    [All shipments →]
└──────────────────────────────────────────┘└────────────────────────────────┘

┌─ Cash Flow (13-week forward) ────────────┐┌─ Top Products by Profit ───────┐
│  diverging bars: inflow ↑ blue           ││  horizontal bars, slot 1       │
│  outflow ↓ red, grey zero midpoint       ││  direct-labelled values        │
│  line: projected balance                 ││  top 8, then "Other"           │
└──────────────────────────────────────────┘└────────────────────────────────┘

┌─ Recent Sales ───────────────┐┌─ Recent Purchases ──┐┌─ Low Stock ────────┐
│ compact 5-row tables, click-through to the record                        │
└──────────────────────────────┘└─────────────────────┘└────────────────────┘
```

**Chart compliance notes** (from the validated palette):
- Revenue-vs-COGS uses slots 1 & 2, legend + direct labels on the final pair.
- The margin-% overlay is **indexed to the same axis**, not a second y-axis.
- Cash-flow uses the diverging pair with a grey midpoint.
- Every chart card has a `[View as table]` toggle in its header — this is also the
  contrast relief for light mode.
- Sparklines carry no axis, no labels, accent colour, 32px tall.

**Data source:** `kpi_daily` snapshot + today's live deltas, so the page paints instantly.

**Role variants:** Sales sees revenue/customers/stock but **no cost, margin or supplier
data**. Warehouse sees stock, shipments and receiving only. Accountant sees finance tiles
first.

## B2. Products

**List.** Table with thumbnail, name + SKU, category, supplier, stock badge
(`available / reserved / incoming`), landed cost, selling price, margin %.

- Margin % cell is a diverging chip: above target = good, below `min_selling_price` = critical.
- Filters row: category, brand, supplier, stock status, tags, price range, has-image.
- Views saved per user ("Low stock crystals", "New this container").
- Bulk: price update (%, fixed, tier), category reassign, tag, activate/deactivate, export.
- Toggle: table ⇄ gallery (image-first, for browsing with customers).

**Detail** — tabbed, header always visible:
```
┌───────────────────────────────────────────────────────────────────────┐
│ [img] Crystal Chandelier A-330            CRY-0042        ● Active    │
│       Crystals › Chandeliers · Supplier: Ningbo Lighting              │
│                                                                       │
│  LANDED COST      SELLING       MARGIN        AVAILABLE     INCOMING  │
│  $107.69          $155.00       30.5%         248           100       │
│  ⓘ estimated                                                          │
├───────────────────────────────────────────────────────────────────────┤
│ Overview │ Suppliers │ Stock │ Cost History │ Sales │ Images │ Docs    │
└───────────────────────────────────────────────────────────────────────┘
```
- **Suppliers tab** — every supplier who sells this, their SKU, price, MOQ, lead time, with
  the cheapest highlighted. Price-history sparkline per supplier.
- **Cost History tab** — landed cost over time as a step line, each step annotated with its
  shipment number. Hovering shows the full cost breakdown.
- **Stock tab** — by warehouse, plus the movement ledger.

## B3. Price List Import — a 5-step wizard

The highest-risk screen in the system; designed so nothing is written until you've seen it.

```
 ①Upload ──── ②Map ──── ③Match ──── ④Review ──── ⑤Commit
```

**① Upload** — drag-drop zone. Pick supplier. If a saved profile exists for that supplier,
steps ② and ③ are pre-filled and skipped.

**② Map** — the sheet rendered as a real preview grid, header row picked by clicking it.
Each of your fields gets a column dropdown; confident guesses are pre-selected and marked
`auto`. Live preview of 5 parsed rows updates as you map. `[Save as profile]`.

**③ Match** — matching runs on `supplier_sku` → `barcode` → your `sku` → fuzzy name.
Summary: `1,204 matched · 42 new · 7 ambiguous`. Ambiguous rows get an inline picker.

**④ Review — the safety net.** The whole point of the feature:
```
┌────────────────────────────────────────────────────────────────────────┐
│  42 NEW   ·   118 PRICE ↑ (avg +6.2%)   ·   9 PRICE ↓   ·   1,035 SAME │
│  ⚠ 3 changes over 50% — check these                                    │
│  [ All ] [ New ] [ Increases ] [ Decreases ] [ Suspicious ] [Unchanged]│
├────────────────────────────────────────────────────────────────────────┤
│ ☑ │ Supplier SKU │ Product        │ Old     │ New     │ Δ      │ Action│
│ ☑ │ A-330        │ Crystal Chand. │ $82.00  │ $85.00  │ +3.7%  │ update│
│ ☑ │ B-114        │ Sofa Set       │ $198.00 │ $220.00 │ +11.1% │ update│
│ ⚠ │ C-902        │ Fabric Roll    │ $18.00  │ $41.00  │+127.8% │ review│
│ ☑ │ D-551        │ — new product —│    —    │ $12.40  │  new   │ create│
└────────────────────────────────────────────────────────────────────────┘
```
Rows are individually checkable. Increases in `serious`, decreases in `good`, >50% swings
flagged `critical` with an icon.

**⑤ Commit** — queued job with live progress. On completion: a summary card and an
**`[Undo this import]`** button that stays available (price history makes it reversible).

## B4. Suppliers

**List:** name (+ Chinese name), country/city, products count, total purchased, outstanding
balance, avg lead time, rating, last order.

**Detail:**
```
┌──────────────────────────────────────────────────────────────────────┐
│ Ningbo Lighting Co. 宁波照明有限公司            ★★★★☆   ● Active     │
│ Ningbo, China · FOB Ningbo · USD · 30 days                           │
│                                                                      │
│ TOTAL PURCHASED  OUTSTANDING   ORDERS   AVG LEAD   PROFIT GENERATED  │
│ $412,800         $28,400       47       42 days    $138,200          │
├──────────────────────────────────────────────────────────────────────┤
│ Overview │ Products │ Orders │ Shipments │ Payments │ Docs │ Contacts │
└──────────────────────────────────────────────────────────────────────┘
```
- **Contacts** show WhatsApp and **WeChat ID** as copy buttons — the fields you actually use.
- **Overview** carries: purchases by month (bars), price-change trend (line), category mix
  (horizontal bars, ≤8 then Other), on-time-delivery rate.
- **Products tab** lists their SKUs with your SKU alongside and price history sparklines.

## B5. Customers

Mirror of suppliers, plus the credit apparatus:

```
│ OUTSTANDING   CREDIT LIMIT   AVAILABLE   AVG DAYS TO PAY   TOTAL REVENUE │
│ $12,400       $20,000        $7,600      34                $186,300      │
│               ▓▓▓▓▓▓▓▓▓▓▓▓░░░░░░░  62% used                              │
```
Credit bar turns `warning` at 80%, `critical` at 100%. Aging table (0-30/31-60/61-90/90+)
with click-through to the invoices in each bucket. `[Send statement]` generates a PDF.

## B6. Purchase Orders

**Builder** — split view, keyboard-first:
```
┌─ Supplier: Ningbo Lighting ─────────┬─ Add items ────────────────────┐
│ Currency USD · Rate 1.0000          │ 🔍 search SKU / supplier SKU… │
│ Incoterm FOB · Deposit 30%          │ ┌────────────────────────────┐ │
│                                     │ │ CRY-0042  A-330    $85.00  │ │
│ # SKU      Qty(ctn) Pcs  Price  Tot │ │ CRY-0043  A-331    $92.00  │ │
│ 1 CRY-0042    5     100  85.00 8500 │ │ ⏎ to add · ↑↓ to move      │ │
│ 2 FUR-0117    2      20 220.00 4400 │ └────────────────────────────┘ │
│                                     │                                │
│              Subtotal    $12,900.00 │ ⓘ MOQ 50 · lead time 35 days  │
│              Discount      −$400.00 │ ⓘ last bought $82.00 (−3.5%)  │
│              Total       $12,500.00 │ ⓘ 248 in stock · 0 incoming   │
│              Deposit 30%  $3,750.00 │                                │
└─────────────────────────────────────┴────────────────────────────────┘
```
Quantities entered in **cartons**, pieces computed and shown — recommendation ⑤ made
visible. Right rail surfaces MOQ, last price, and current stock at the moment of decision.

**Detail:** timeline down the right (`Draft → Sent → Confirmed → In production → Shipped →
Received`), payments panel with deposit/balance progress, linked shipments, attachments.

## B7. Shipments — the container board

**Board view (default)** — kanban by status, because this is genuinely a pipeline:
```
 PLANNING      BOOKED       IN TRANSIT     CUSTOMS      CLEARED     DELIVERED
┌──────────┐ ┌──────────┐ ┌────────────┐ ┌──────────┐ ┌─────────┐ ┌─────────┐
│SHP-016   │ │SHP-015   │ │SHP-014     │ │SHP-013   │ │SHP-012  │ │SHP-011  │
│2 POs     │ │MSKU12345 │ │TCLU887766  │ │HLXU4455  │ │         │ │         │
│$8,200    │ │40HQ      │ │40HQ 58 CBM │ │20ft      │ │         │ │         │
│          │ │ETD 20 Aug│ │ETA 12 Aug  │ │since 3d  │ │         │ │         │
│          │ │          │ │ ⚠ est. cost│ │          │ │         │ │✓ final  │
└──────────┘ └──────────┘ └────────────┘ └──────────┘ └─────────┘ └─────────┘
```
Cards show container number (mono), CBM, ETA with a countdown, and a costing badge
(`estimated` warning / `final` good). Dragging between columns is a status transition with
a confirm step.

**Detail — three panels:**
1. **Header** — container, B/L, route, method, ETD/ATD/ETA/ATA, live status pill.
2. **Contents** — items grouped by source PO, with qty / weight / CBM / value, and the
   totals that feed the allocation denominators.
3. **Costs** — every `shipment_cost`, its basis, amount and `estimated`/`actual` flag, with
   a running "cost uplift %" figure.
4. **Timeline** — vertical, from `shipment_events`, with document attachments inline.

## B8. Landed Cost Workbench — the signature screen

A custom Filament page, not a resource. This is where the system earns its keep.

```
Landed Cost · SHP-2026-0014                    Run v2 (estimated)  [Recalculate]
──────────────────────────────────────────────────────────────────────────────
┌─ Costs ────────────────────────────────────┐┌─ Allocation preview ──────────┐
│ Sea freight (40HQ)   $3,200.00  volume  est││  Goods         $18,300.00     │
│ Insurance              $183.00  value      ││  + Costs        $8,148.57     │
│ Customs duty        calculated  per-HS     ││  ─────────────────────────    │
│ Clearance agent        $450.00  value   est││  = Landed      $26,448.57     │
│ Bank charges            $95.00  value      ││                               │
│ Port charges           $380.00  volume     ││  Uplift          +44.5%       │
│ Inland transport       $600.00  volume     ││  ▓▓▓▓▓▓▓▓▓░░░░░░░░            │
│ [+ Add cost]                               ││                               │
└────────────────────────────────────────────┘└───────────────────────────────┘

┌─ Per-item result ─────────────────────────────────────────────────────────┐
│ Product          Qty  Goods    Freight  Duty     Other   LANDED/UNIT   Δ  │
│ Crystal Chand.   100  $85.00   $4.41    $13.54   $3.86   $107.69    ▲2.1% │
│ Sofa Set          20  $220.00  $88.28   $62.10   $35.78  $406.16   ▲12.4% │
│ Fabric Roll      300  $18.00   $3.31    $2.15    $1.72    $25.19    ▼0.8% │
└───────────────────────────────────────────────────────────────────────────┘
                                  [ Save as estimate ]  [ Finalise & apply ]
```

- Changing any cost or basis **recalculates the table live** — this is the core interaction.
- Hovering a per-item cell opens the allocation maths for that cell ("$88.28 = $3,200 ×
  32.00 CBM / 58.00 CBM ÷ 20 units").
- The Δ column compares to the product's current average cost, coloured diverging.
- `[Finalise & apply]` opens a confirmation showing the revaluation impact: how much COGS
  correction and how much inventory adjustment will post, and to which products.
- A basis dropdown sits on every cost row, defaulted from the cost type — with a one-line
  explanation on hover ("Volume: split by CBM. Correct for sea freight.").

## B9. Inventory

**Stock list:** product, warehouse, on hand, reserved, available, incoming, damaged, avg
cost, total value. A stacked horizontal bar per row visualises available/reserved/incoming
using slots 1–3 with a 2px surface gap between segments.

**Movements ledger:** date, product, type pill, qty in/out, running balance, unit cost,
value, reference link, user. Filterable by shipment — "show me everything from container 14".

**Valuation report:** by category / warehouse / supplier, with goods-in-transit as its own
clearly-separated block.

## B10. Sales Orders & Invoices

**Order builder** mirrors the PO builder, with sales-side intelligence in the right rail:
customer's last price for this item, their tier price, available stock, and a **credit
warning banner** if the order breaches the limit (blocking Sales, requiring Manager approval).

**Invoice detail:** document preview on the left, actions on the right (Send, Record
payment, Download PDF, Duplicate, Credit note). Payment panel shows allocations against
this invoice and a progress bar to fully-paid.

**PDF invoice template:** company logo and stamp, bilingual EN + AR/KU column headers,
tabular figures, totals block, bank details, terms, QR code linking to the online copy.
Rendered by headless Chromium so Arabic/Kurdish shape and join correctly.

## B11. Finance & Expenses

**Expense entry is deliberately two clicks:** category, amount, date, done. Attach a photo
of the receipt by drag-drop. A `Link to shipment` field routes the expense directly into
landed cost — the loop that keeps costing honest.

**Finance overview:** P&L summary (revenue → COGS → gross → opex → net) as a
waterfall-style stacked bar; expense breakdown by category (horizontal bars, top 8 then
Other); cash accounts with balances; receivables and payables aging side by side.

## B12. Reports

Left rail of report types, right pane of parameters, then a rendered result with
`[PDF] [Excel] [CSV]` and `[Schedule]` (email it monthly). Every report is a saved,
shareable configuration.

## B13. Analytics

Full-page charts, each obeying Part A's rules: one axis, fixed slot colours, legend for ≥2
series, table toggle on every chart. Global date-range and dimension filters sit in **one
row above the charts** and apply to all of them.

## B14. AI Assistant

Full-height chat panel, openable as a drawer from anywhere (`⌘J`).

- Suggested prompts on first open, drawn from your actual data
  ("Which products are selling slowly?", "How much did I spend on cargo this year?").
- Answers render as **real components** — a chart, a table, a stat tile — not just prose,
  each with a "open the underlying records" link so nothing is a black box.
- Every answer shows which tools it called, so numbers are traceable.
- A `Daily brief` card on the dashboard: 3–5 generated observations, dismissible/pinnable.

## B15. Global search (⌘K)

Instant, cross-entity, grouped by type with icons. Matches on product name, SKU, barcode,
supplier, customer, invoice number, container number, B/L, PO number. Recent items when
empty. Arrow keys + Enter; `⌘K` then a prefix (`>` commands, `#` products, `@` customers)
narrows the scope. Also runs actions: "new invoice", "toggle dark mode".

## B16. Settings

Grouped panels: Company, Currencies & Rates, Taxes, Warehouses, Users & Roles, Document
Numbering, Invoice Templates, Notifications, Import Profiles, Backup & Restore, Appearance,
Language. Each panel is a single form with auto-save and an inline "Saved" confirmation —
no Save button hunting.

## B17. Keyboard shortcuts

| Key | Action |
|---|---|
| `⌘K` / `Ctrl K` | Global search & commands |
| `⌘J` | AI assistant |
| `⌘\` | Collapse sidebar |
| `G` then `D/P/S/C/I` | Go to Dashboard / Products / Shipments / Customers / Invoices |
| `N` | New record on the current resource |
| `/` | Focus the table filter |
| `⌘S` | Save current form |
| `⌘Enter` | Save & close |
| `E` | Edit focused row |
| `X` | Select focused row (then `⇧X` for a range) |
| `?` | Shortcut cheatsheet |
| `Esc` | Close modal / clear selection |
