# Implementation Roadmap

> **Build status — 2026-08-01**
>
> | Area | State |
> |---|---|
> | Phase 0 — Foundation, theme, currencies, RBAC, numbering | ✅ done |
> | **Price Lists module** — all four sections built | ✅ **done** — Crystals: 90 colours × 7 sizes matrix. Textile / Packaging / Furniture: section-driven fields + quantity-break pricing |
> | Phase 1 — Catalog, suppliers, customers, supplier products | ✅ done (product images pending) |
> | **Phase 2 — Price list import** | ✅ **done** — reader, matcher, diff review, commit, full undo |
> | Phase 3 — Purchasing | ⚠️ orders list + carton/piece conversion; PO builder & supplier payments pending |
> | Phase 4 — Shipments & containers | ✅ list + costing page (kanban board pending) |
> | **Phase 5 — Landed cost engine** | ✅ **done, tested to the cent** |
> | Phase 6 — Inventory, stock ledger, goods receipt | ✅ ledger + receiving (adjustments/transfers pending) |
> | Phase 7 — Sales & invoicing | ⚠️ invoices + COGS snapshot; order builder, PDF, returns pending |
> | Phase 8 — Finance & expenses | ⚠️ expenses incl. shipment linking; cash ledger & P&L pending |
> | Phase 9 — Dashboard & analytics | ✅ attention band, 8 KPI tiles, container table, 3 charts |
> | Phase 10 — Reports | ✅ 10 reports + streamed CSV export |
> | Phase 12 — Notifications | ✅ 7 alert rules, daily command, de-duplicated, in-panel bell |
> | Phase 11 — AI assistant | ✅ built — 6 read-only tools, cost tools hidden from roles without `view_cost`; needs `ANTHROPIC_API_KEY` to run |
> | Phase 13 — Deployment hardening | ✅ **done** — `docs/08-DEPLOYMENT.md`, real `.env.example`, backup config + schedule, PostgreSQL portability fixes |
>
> | **PDF invoices** | ✅ **done** — bilingual EN/AR via headless Chromium |
> | **Sales-order builder** | ✅ **done** — tier pricing, live availability, reservations, credit gate |
> | **PO builder** | ✅ **done** — carton↔piece conversion, MOQ warnings, price-drift check, incoming stock |
>
> | **Payment allocation** | ✅ **done** — one payment across many invoices, auto-allocate oldest first, credit remainder |
>
> | **Textile / Packaging / Furniture** | ✅ **done** — one screen, columns declared per section in `price_list_sections.attribute_schema`; a fifth section is a row, not a page |
>
> **All 13 phases are built.** Remaining known gaps are listed as ⚠️ above —
> product images, kanban shipment board, stock adjustments/transfers, sales
> returns, and the cash-ledger/P&L screens.
>
> 201 tests · 577 assertions · Pint clean · 23 screens verified in a real browser.
>
> **Before go-live:** run the suite against PostgreSQL on the server. The whole
> project has been developed on SQLite, and §3 of `docs/08-DEPLOYMENT.md`
> explains what that does and does not prove.
>
> **PDF setup note:** rendering needs a Chrome/Chromium binary. It is auto-detected
> in the usual locations; on a server set `CHROME_PATH` in `.env`. `puppeteer` is a
> devDependency installed with `PUPPETEER_SKIP_DOWNLOAD=true`, since the machine
> already has a browser.


13 phases. Each is independently shippable and leaves the system in a working state.
Sequencing follows data dependency, not module numbering — you cannot cost a container
before you can create one.

**Critical path:** `0 → 1 → 3 → 4 → 5 → 6` — that chain is the reason this ERP exists.
Everything else attaches to it.

```
P0 Foundation
 ├─ P1 Catalog & Suppliers ──┬─ P2 Price List Import
 │                           └─ P3 Purchasing ── P4 Shipments ── P5 LANDED COST ── P6 Inventory
 │                                                                                    │
 ├─ P7 Sales & Invoicing ─────────────────────────────────────────────────────────────┤
 ├─ P8 Finance & Expenses ────────────────────────────────────────────────────────────┤
 ├─ P9 Dashboard & Analytics ◀────────────────────────────────────────────────────────┤
 ├─ P10 Reports ◀─────────────────────────────────────────────────────────────────────┘
 ├─ P11 AI
 ├─ P12 Notifications, Search, Polish
 └─ P13 Hardening & Deployment
```

---

## Phase 0 — Foundation

**Goal:** a themed, secure, multi-currency shell that everything else plugs into.

- ✅ Node.js 24 installed (done during design)
- Install packages: medialibrary, backup, laravel-pdf, maatwebsite/excel, league/csv,
  scout+meilisearch, horizon, sanctum
- **Custom Filament theme** — the full token system from [05-UIUX.md](05-UIUX.md) Part A:
  neutrals, indigo accent, Inter + IBM Plex Sans Arabic, hairline elevation, 44px density,
  motion timings, dark/light
- App shell: sidebar clusters, topbar (⌘K, theme toggle, currency switcher, notifications)
- `companies`, `currencies`, `exchange_rates`, `number_sequences`, `attachments`
- `Money` value object, `CurrencyConverter`, `DocumentNumberGenerator`
- Roles & permissions seeded (5 roles, full permission matrix from F14)
- Settings pages: Company, Currencies & Rates, Numbering, Appearance
- Users resource + invitation flow
- Test harness: factories, a `MoneyAssertions` helper, CI-ready `composer test`

**Done when:** you can log in, switch theme and currency, invite a user with a role, and
`PO-2026-0001` allocates correctly under concurrent requests.

---

## Phase 1 — Catalog & Suppliers

**Goal:** the product and supplier master data that everything references.

- Extend `products` with all columns from [03-DATA-MODEL.md](03-DATA-MODEL.md) §3
  (multilingual names incl. `name_zh`, attributes JSONB, weight/CBM, HS code, pack size,
  average cost, price fields)
- `brands`, `product_groups`, `price_tiers`, `product_prices`, `tags`
- Extend `suppliers`; add `supplier_contacts` (WhatsApp, WeChat)
- **`supplier_products`** + `supplier_product_prices` (recommendation ③)
- Filament resources: Products (table + gallery view), Categories (tree), Brands, Units,
  Suppliers, Price Tiers
- Product detail page with all tabs; multi-image upload with drag-drop reordering
- Bulk actions: price update, category reassign, tag, export

**Done when:** a product can be created, imaged, priced per tier, and linked to two
suppliers with different SKUs and prices — and the cheapest source is visible.

---

## Phase 2 — Price List Import

**Goal:** recommendation ④ — the reviewable, reversible pipeline.

- `import_profiles`, `price_list_imports`, `price_list_import_rows`
- `SheetReader` (xlsx/xls/csv, chunked), `ColumnMapper`, `ProductMatcher`
  (supplier_sku → barcode → sku → fuzzy), `DiffBuilder`
- 5-step wizard page (§B3): Upload → Map → Match → Review → Commit
- Saved profiles per supplier; second import of the same format is one click
- Queued parsing with live progress
- Commit transaction + **full undo**
- Price history recording and per-supplier price-drift chart

**Done when:** a real, messy supplier spreadsheet imports correctly, the diff preview
catches a deliberately corrupted row, and undo restores prior state exactly.

---

## Phase 3 — Purchasing

- Extend `purchase_orders` / `_items` (currency, incoterm, deposit, pack sizes, shipped and
  received quantities); extend status enum
- PO builder page (§B6) — split view, carton↔piece conversion, MOQ/last-price/stock rail
- Actions: `ConfirmPurchaseOrder`, `SendPurchaseOrder`, `ClosePurchaseOrder`
- PO PDF (bilingual)
- Supplier bills + payments with FX gain/loss; `supplier_payment_allocations`
- Deposit/balance schedule and progress
- Supplier profile analytics (spend by month, price trend, on-time rate)

**Done when:** a PO can be built, confirmed, part-paid in CNY, and the supplier's
outstanding balance and incoming stock are both correct.

---

## Phase 4 — Shipments & Logistics

- `freight_forwarders`, `shipments`, `shipment_items`, `shipment_cost_types`,
  `shipment_costs`, `shipment_events`
- Shipment board (kanban by status) + detail page with contents / costs / timeline
- "Add items from POs" picker supporting **partial** quantities and many POs per container
- Weight/CBM snapshotting at add-time
- Document attachments (B/L, packing list, customs declaration)
- Status transitions with event logging; ETA countdowns

**Done when:** two POs from different suppliers can be loaded into one container with
partial quantities, and the totals that feed allocation are correct.

---

## Phase 5 — Landed Cost Engine ⭐

**The centrepiece.** Full spec in [04-LANDED-COST.md](04-LANDED-COST.md).

- `landed_cost_runs`, `landed_cost_lines`, `landed_cost_allocations`, `cost_revaluations`
- `LandedCostCalculator` with the 4-pass dependency order
- One `CostAllocator` strategy per basis + `HsDutyCalculator` (CIF × rate)
- Rounding discipline with residual assignment, assertion-enforced
- Run versioning; estimated → actual → final lifecycle
- `RevaluationService` splitting COGS correction from inventory adjustment
- **Landed Cost Workbench** page (§B8) with live recalculation and hover-to-explain maths
- Finalise confirmation showing exact revaluation impact
- **Test suite using §4 and §5.2 of the spec as fixtures — every number asserted**

**Done when:** the worked example reproduces to the cent, including the $0.0001 residual,
and finalising after partial sales posts the correct COGS and inventory adjustments.

---

## Phase 6 — Inventory

- Extend `stock_levels` (reserved/incoming/damaged/average_cost/value) and
  `stock_movements` (total cost, value balance, `shipment_id`, revaluation flag)
- `StockLedger` — the single write path; `ValuationService`
- `stock_reservations`, `stock_adjustments`, `stock_transfers`
- Goods receipt against a shipment, valued at landed cost, with discrepancy handling
- Moving weighted average recalculation on receipt
- Inventory list, movement ledger, valuation report, goods-in-transit block
- Scheduled consistency check: `stock_levels` vs the movement ledger

**Done when:** receiving container SHP-014 sets each product's average cost correctly, and
the ledger reconciles to `stock_levels` exactly.

---

## Phase 7 — Sales & Invoicing

- Extend customers (credit limit, tier, aging), `customer_contacts`
- Extend quotations / sales orders / delivery notes / invoices with currency and COGS snapshot
- `payment_allocations` (recommendation ⑨), `sales_returns`, credit notes
- Order builder (§B10) with tier pricing, availability, credit check + approval gate
- Reservation on confirm, consumption on delivery
- Invoice posting with COGS snapshot → true per-invoice margin
- **PDF invoice** via Chromium: bilingual EN + AR/KU, logo, stamp, QR, bank details
- Customer statements; aging report

**Done when:** one $5,000 payment allocates across four invoices, a return restocks at the
original landed cost, and an Arabic invoice PDF renders with correct letter joining.

---

## Phase 8 — Finance & Expenses

- `expense_categories` (seeded to module 12), `expenses`, `bank_accounts`, `cash_transactions`
- Two-click expense entry with receipt drag-drop
- **Link-to-shipment** routing expenses into landed cost (closes the F9 loop)
- Cash ledger with running balances; account transfers
- Finance overview: P&L, expense breakdown, receivables/payables aging
- `journal_entries` / `journal_lines` schema created but not posted to (recommendation ⑮)

**Done when:** a customs expense recorded against a shipment appears on its landed-cost tab
and flags the run for recalculation.

---

## Phase 9 — Dashboard & Analytics

- `kpi_daily` snapshot table + nightly job
- Dashboard (§B1): attention band, 8 stat tiles, 4 chart cards, 3 recent tables
- Role-specific dashboard variants (Sales sees no cost data)
- Analytics page with global filter row
- **All charts built to the validated palette rules**: fixed slot order, single axis,
  legend ≥2 series, direct labels ≤4 series, `[View as table]` on every chart, ≤3 series on
  scatter/bubble forms, diverging pair with grey midpoint for polarity
- Per-shipment margin report (recommendation ⑭)

**Done when:** the dashboard paints in under 500ms from the snapshot, and every chart
passes the accessibility checklist.

---

## Phase 10 — Reports & Exports

- `ReportBuilder` + one class per report: Sales, Purchases, Inventory, Suppliers,
  Customers, Expenses, Profit, Cash Flow, Best Sellers, Slow Movers, Shipment Costs,
  Monthly, Yearly
- PDF / Excel / CSV export for every report
- Saved report configurations; scheduled email delivery

---

## Phase 11 — AI Assistant

- `AiProvider` contract + `ClaudeProvider` (Messages API via Laravel HTTP client)
- **Fixed, typed, permission-scoped tool surface** — never generated SQL
- Chat drawer (`⌘J`) rendering answers as charts/tables/tiles with tool-call transparency
- `ai_conversations`, `ai_messages`, `ai_insights`; token and cost tracking
- Nightly insight generation → dashboard daily brief
- Reorder suggestions from sales velocity + lead time + incoming stock

*The current Claude API reference is consulted at the start of this phase rather than
baked into the design docs, so model IDs and parameters are correct at build time.*

---

## Phase 12 — Notifications, Search & Polish

- `notification_rules` + all 7 triggers from module 16, with direct actions (F15)
- Meilisearch indexing; ⌘K global search across products, SKUs, barcodes, customers,
  suppliers, invoices, containers, B/Ls, POs — plus command actions
- Full keyboard shortcut map (§B17) and cheatsheet
- Bulk edit/import/export everywhere; auto-save on long forms
- Empty states, skeletons, error states throughout
- Mobile responsive pass; RTL pass for Arabic/Kurdish
- Localisation: EN / AR / KU

---

## Phase 13 — Hardening & Deployment

- VPS provisioning: PostgreSQL 16, Redis, Meilisearch, Horizon, Caddy + TLS
- Migration from SQLite dev data to Postgres
- `spatie/laravel-backup`: nightly encrypted off-site backups, monitored, **restore tested**
- Rate limiting, security headers, upload validation audit
- Performance: query profiling, eager-loading audit, index verification under real volume
- Load test with realistic data (10k products, 500 shipments, 50k movements)
- Seed script for demo/training data
- Operations runbook + user guide

---

## What is deliberately not in v1

Architected for, not built — each is additive, none requires rework:

| Deferred | Enabled by |
|---|---|
| Multiple companies | `company_id` already on core tables |
| Multiple warehouses in daily use | Schema is fully multi-warehouse; UI defaults to one |
| Double-entry GL | Tables designed; Actions already emit domain events for listeners |
| Barcode scanning / QR | Barcode field + receipt flow are scan-ready |
| Mobile app | Sanctum + the Action layer form the API surface |
| Customer / supplier portals | Filament multi-panel |
| Payment gateways, OCR invoice scanning, AI forecasting | Clean integration points at Payment, Attachment and AiProvider |

---

## Sequencing note

Phases 7 and 8 (Sales, Finance) don't depend on 4–6 and can be brought forward if you need
to invoice before the first container lands. The costing chain is what makes the numbers
*true*, but the sales chain is what makes the system *usable day one*. Say the word and
I'll reorder.
