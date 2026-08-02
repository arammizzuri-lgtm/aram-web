# System Architecture

---

## 1. Technology stack

### 1.1 Chosen stack

| Layer | Technology | Why this and not the alternative |
|---|---|---|
| **Runtime** | PHP 8.4 | Already installed (`C:\Users\Aram\php`). Typed enums, readonly classes, property hooks — good for a document-heavy domain. |
| **Framework** | Laravel 13 | Already scaffolded. Best-in-class for transactional business apps: migrations, queues, events, policies, scheduling all first-party. |
| **Admin UI** | Filament 4 | Already installed. Delivers tables, filters, bulk actions, form builder, RBAC integration, global search, notifications — the 70% of an ERP that is expensive and boring to hand-build. Design effort concentrates on a custom theme plus bespoke pages where it matters. |
| **Interactivity** | Livewire 3 (via Filament) + Alpine.js | Server-driven. No API/state duplication, no separate front-end build pipeline to maintain. |
| **Styling** | Tailwind CSS 4 + custom Filament theme | Required for the visual quality target. Needs Node — see §5.1. |
| **Database** | **PostgreSQL 16** | Chosen over MySQL for: exact `numeric` arithmetic (critical for money), native `jsonb` with indexing (product attributes, import mappings), generated columns, `CTE`/window functions for aging and running balances, and partial indexes. The schema stays MySQL-8-compatible if you ever need to move. |
| **Cache / queue / session** | Redis 7 | Landed-cost runs, imports, PDF generation and report builds all go to queues. |
| **Queue supervision** | Laravel Horizon | Visibility into failed imports and costing jobs. |
| **Search** | Laravel Scout + Meilisearch | Module 15 asks for *instant* global search across products, SKUs, barcodes, invoices, containers. Filament's built-in search is per-resource and DB-bound; Meilisearch gives sub-50ms typo-tolerant cross-entity results. Postgres FTS is the fallback if you'd rather not run another service. |
| **File storage** | S3-compatible (Cloudflare R2 / Backblaze B2) | Product images, supplier price lists, B/Ls, customs docs. Cheap, off-server, CDN-able. Local disk in dev. |
| **PDF** | `spatie/laravel-pdf` (Browsershot → headless Chromium) | **Important for your market:** DomPDF and mPDF render Arabic and Kurdish poorly — broken letter joining and RTL layout. Chromium renders them perfectly, and lets invoice templates be plain Blade + Tailwind. |
| **Excel / CSV** | `maatwebsite/excel` + `league/csv` | Chunked, queueable reading for large supplier price lists. `openspout` swapped in if a file ever exceeds memory. |
| **Media** | `spatie/laravel-medialibrary` | Multiple product images with conversions, plus document attachments on any model. |
| **Permissions** | `spatie/laravel-permission` ✅ installed | Roles + granular per-resource permissions. |
| **Audit** | `spatie/laravel-activitylog` ✅ installed | Who changed what, when, with before/after values. |
| **Backups** | `spatie/laravel-backup` | Nightly encrypted DB + files to off-site storage, with failure alerts. |
| **API** | Laravel Sanctum | Token auth, ready for the future mobile app / customer portal. Not built in v1, but the boundary exists. |
| **AI** | Claude API behind an `AiProvider` interface | Thin wrapper over the Messages API using Laravel's HTTP client, rather than a third-party SDK — fewer moving parts, no dependency churn. Tool-calling agent with read-only, permission-scoped data tools. |
| **Testing** | PHPUnit 12 | Already configured. Feature tests for every money-touching path. |
| **Code style** | Laravel Pint ✅ installed | |

### 1.2 Deliberately rejected

| Rejected | Reason |
|---|---|
| Custom React/Inertia SPA | 3–4× the build time for the same features. Filament + a serious custom theme reaches the quality bar far sooner. |
| Full double-entry GL in v1 | Slows daily operations for no benefit at this stage. Designed in, deferred. (See recommendation ⑮.) |
| Integer minor-unit money storage | IQD has no practical minor unit and rates carry 8 decimals. `numeric(19,4)` + explicit currency + a `Money` value object is clearer and avoids a class of conversion bugs. |
| Multi-tenancy in v1 | `company_id` is present on core tables so it can be switched on later, but v1 runs single-company. |
| Event sourcing | Overkill. The `stock_movements` ledger + `activity_log` give the auditability that matters. |

---

## 2. Application layering

Filament is the *delivery mechanism*, not the application. All business rules live in
plain PHP classes that can be called from a Filament action, a console command, a queued
job, an API controller or a test — identically.

```
┌──────────────────────────────────────────────────────────────────────┐
│  DELIVERY                                                            │
│  Filament Resources · Pages · Widgets · Actions                      │
│  Console Commands · Queued Jobs · (future) API Controllers           │
│  ── thin. Validation + wiring only. No business rules. ──            │
└───────────────────────────┬──────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────────────┐
│  APPLICATION — Actions                                               │
│  One class = one use case, invokable, transactional.                 │
│  ConfirmPurchaseOrder · ReceiveShipment · CalculateLandedCost ·      │
│  CommitPriceListImport · PostInvoice · AllocatePayment ·             │
│  ReserveStock · RevalueShipment · RecordExpense                      │
└───────────────────────────┬──────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────────────┐
│  DOMAIN — Services · Value Objects · Enums · Events                  │
│  LandedCostCalculator · CostAllocator · StockLedger ·                │
│  CurrencyConverter · DocumentNumberGenerator · PriceListMatcher      │
│  Money · Quantity · ExchangeRate · AllocationBasis                   │
└───────────────────────────┬──────────────────────────────────────────┘
                            ▼
┌──────────────────────────────────────────────────────────────────────┐
│  PERSISTENCE — Eloquent Models · Query Builders · Migrations         │
│  Models hold relationships, casts, scopes. No orchestration.         │
└──────────────────────────────────────────────────────────────────────┘
```

**Rules enforced throughout:**

1. A Filament page never contains arithmetic on money or stock. It calls an Action.
2. Every Action that touches money or stock runs inside a DB transaction and emits a
   domain event.
3. Stock is *never* mutated directly. `StockLedger` writes a `stock_movements` row and
   derives `stock_levels`. One write path, always auditable.
4. Money is never a `float`. `Money` wraps `numeric(19,4)` with bcmath arithmetic.
5. Currency conversion always goes through `CurrencyConverter`, which requires an explicit
   date. There is no "current rate" shortcut.

---

## 3. Folder structure

Conventional Laravel layout — Filament's auto-discovery and Laravel's tooling both work
without configuration — with domain layers added underneath.

```
erp/
├── app/
│   ├── Actions/                      # use cases, grouped by context
│   │   ├── Catalog/                  #   ImportPriceList, MatchSupplierProducts…
│   │   ├── Purchasing/               #   ConfirmPurchaseOrder, RecordSupplierBill…
│   │   ├── Shipping/                 #   CreateShipment, MarkArrived, ClearCustoms…
│   │   ├── Costing/                  #   CalculateLandedCost, FinaliseShipment, Revalue…
│   │   ├── Inventory/                #   ReceiveGoods, ReserveStock, AdjustStock…
│   │   ├── Sales/                    #   ConfirmSalesOrder, PostInvoice, AllocatePayment…
│   │   └── Finance/                  #   RecordExpense, RecordPayment…
│   │
│   ├── Services/
│   │   ├── Costing/                  # LandedCostCalculator, CostAllocator (per basis)
│   │   ├── Inventory/                # StockLedger, ValuationService
│   │   ├── Currency/                 # CurrencyConverter, RateProvider
│   │   ├── Documents/                # DocumentNumberGenerator, PdfRenderer
│   │   ├── Import/                   # SheetReader, ColumnMapper, ProductMatcher, DiffBuilder
│   │   ├── Reporting/                # ReportBuilder + one class per report
│   │   └── Ai/                       # AiProvider contract, ClaudeProvider, Tools/*
│   │
│   ├── Support/
│   │   ├── Money.php                 # value object
│   │   ├── Quantity.php
│   │   └── Concerns/                 # HasDocumentNumber, HasLineItems, HasAttachments…
│   │
│   ├── Models/                       # Eloquent — flat, Laravel convention, Filament-friendly
│   ├── Enums/                        # every status + AllocationBasis, ShipmentStatus…
│   ├── Events/  Listeners/  Jobs/  Notifications/  Policies/  Observers/
│   │
│   └── Filament/
│       ├── Clusters/                 # navigation grouping (Purchasing, Logistics, Finance…)
│       ├── Resources/                # one folder per entity
│       ├── Pages/                    # Dashboard, LandedCostWorkbench, PriceListImport,
│       │                             # Analytics, AiAssistant, Reports
│       ├── Widgets/                  # KPI tiles, charts, tables
│       ├── Forms/Components/         # shared: MoneyInput, CurrencySelect, ProductPicker
│       ├── Tables/Columns/           # shared: MoneyColumn, StockBadge, StatusPill
│       └── Actions/                  # reusable row/bulk actions
│
├── database/
│   ├── migrations/                   # existing 25 kept; new ones layered on top
│   ├── factories/  seeders/
│
├── resources/
│   ├── css/
│   │   ├── app.css
│   │   └── filament/admin/theme.css  # ← the custom design system
│   ├── js/
│   └── views/
│       ├── filament/                 # custom page & widget blades
│       └── pdf/                      # invoice, PO, delivery note, statement templates
│
├── routes/  config/  tests/  storage/  public/
└── docs/                             # this documentation
```

---

## 4. Cross-cutting concerns

### 4.1 Money & currency

```php
// Every monetary column group
amount          numeric(19,4)   // in transaction currency
currency        char(3)         // CNY | USD | IQD
exchange_rate   numeric(19,8)   // to base, frozen at document date
base_amount     numeric(19,4)   // USD, computed once, never recalculated
```

`CurrencyConverter::convert(Money $m, string $to, CarbonInterface $on): Money` — the date
is mandatory. Rates are looked up from `exchange_rates` (nearest rate on or before the
date). No implicit "today".

### 4.2 Document numbering

`number_sequences` table keyed by `(document_type, year)`, with a configurable format
(`PO-{YYYY}-{####}` → `PO-2026-0147`). Allocation happens inside the document's
transaction with a row lock, so numbers are gapless and concurrency-safe.

### 4.3 Document state machine

```
DRAFT ──▶ CONFIRMED ──▶ POSTED ──▶ CLOSED
  │           │            │
  └──────────▶└───────────▶ CANCELLED
```

- `DRAFT` — freely editable.
- `CONFIRMED` — stock reserved / commitments created; limited edits.
- `POSTED` — immutable. Ledger entries written. Corrections only via credit note,
  adjustment or revaluation.
- Transitions are guarded by policies and produce activity-log entries.

### 4.4 Authorisation

Two layers:

1. **Roles** (`spatie/laravel-permission`): Owner, Manager, Sales, Warehouse, Accountant.
2. **Permissions**, granular per resource and per verb, plus field-level gates for
   sensitive data — Sales must not see cost prices or supplier margins. Filament policies
   + a `HidesCostData` trait on the relevant columns handle this.

### 4.5 Auditability

- `activity_log` records create/update/delete with before/after values on all business models.
- `stock_movements` is an append-only ledger with `balance_after` — inventory is always
  reconstructable.
- Posted documents are immutable; corrections are new documents.
- Landed cost runs are versioned; superseded runs are retained, not deleted.

### 4.6 Background work

| Job | Queue | Why async |
|---|---|---|
| Price-list parse & match | `imports` | Large files, slow matching |
| Landed cost calculation | `costing` | Touches every line + revaluation |
| PDF generation (invoice, statement) | `documents` | Chromium spin-up |
| Report / export builds | `reports` | Heavy aggregation |
| Nightly KPI snapshot | `scheduled` | Pre-computes dashboard numbers |
| AI insight generation | `ai` | External API latency |
| Backups | `scheduled` | |

Dashboards read from a nightly `kpi_daily` snapshot table for instant load, with live
figures for today layered on top.

---

## 5. Infrastructure

### 5.1 Local development (this machine)

Currently installed: PHP 8.4.23, `composer.phar` at `C:\Users\Aram\php\composer.phar`,
Git. **Node.js is not installed** and is required to build the custom Filament theme —
Phase 0 installs it via `winget install OpenJS.NodeJS.LTS`.

Local runs on SQLite initially for speed, with a Postgres-compatible schema so there is no
rewrite when moving to the VPS.

### 5.2 Production VPS

```
                    ┌──────────────────────────┐
                    │  Caddy / Nginx (TLS)     │
                    └───────────┬──────────────┘
                                ▼
                    ┌──────────────────────────┐
                    │  PHP 8.4-FPM · Laravel   │
                    └───┬──────────┬───────────┘
                        │          │
          ┌─────────────┘          └──────────────┐
          ▼                                       ▼
┌──────────────────┐  ┌──────────────┐  ┌──────────────────┐
│  PostgreSQL 16   │  │  Redis 7     │  │  Meilisearch     │
│  (daily basebkp) │  │  cache/queue │  │  global search   │
└──────────────────┘  └──────┬───────┘  └──────────────────┘
                             ▼
                    ┌──────────────────────────┐
                    │  Horizon workers         │
                    │  imports · costing ·     │
                    │  documents · reports     │
                    └──────────────────────────┘
                             │
                             ▼
                    ┌──────────────────────────┐
                    │  Object storage (R2/B2)  │
                    │  images · docs · backups │
                    └──────────────────────────┘
```

Sizing for this business: 4 vCPU / 8 GB RAM comfortably handles a handful of concurrent
users and tens of thousands of SKUs.

### 5.3 Security

| Control | Implementation |
|---|---|
| Authentication | Filament login, bcrypt (12 rounds), optional 2FA in a later phase |
| Sessions | Database-backed, encrypted cookies, `AuthenticateSession` for single-session invalidation |
| Authorisation | Policies on every model; permissions checked in navigation, table and form layers |
| Transport | TLS enforced, HSTS |
| Secrets | `.env` only, never committed; `.env` is already gitignored |
| Uploads | MIME + extension allow-list, size caps, stored outside webroot, served through signed URLs |
| SQL injection | Eloquent/query-builder bindings only; the AI layer uses a **fixed tool surface**, never free-form SQL |
| Audit | `activity_log` + immutable posted documents |
| Backups | Nightly encrypted DB+files off-site, monitored, restore tested each phase |
| Rate limiting | Login throttling, API throttling |

### 5.4 The AI layer's data access

The AI assistant is given a **fixed set of typed tools** (`get_sales_summary`,
`find_products`, `get_customer_balance`, `get_shipment_costs`, …), each of which runs a
parameterised, permission-scoped query. It never generates SQL and never sees data the
requesting user isn't allowed to see. Detailed design lands in Phase 11; the current Claude
API reference will be consulted at implementation time rather than baked into this document.

---

## 6. Conventions

- **Migrations** are additive. Existing tables are extended via `ALTER`, never rewritten.
- **Models** are thin: relations, casts, scopes, accessors. No orchestration.
- **Enums** are backed string enums implementing `HasLabel`/`HasColor` for Filament.
- **Naming**: tables `snake_case` plural; documents carry `number` (human) + `id` (system).
- **Tests**: every Action that moves money or stock has a feature test. The landed-cost
  engine gets a dedicated suite with the worked examples from [04-LANDED-COST.md](04-LANDED-COST.md)
  as fixtures.
- **Comments** explain *why*, never *what*.
