# Import & Wholesale ERP

A vertical ERP for a **China-import → local-wholesale** business, built around one question
generic ERPs answer badly:

> *What did this item **actually** cost me, landed in my warehouse — and what did I
> **actually** make when I sold it?*

Purchasing, shipping, inventory, sales and finance all exist to feed or consume that number.

---

## Documentation

Read in order. [docs/00-OVERVIEW.md](docs/00-OVERVIEW.md) is the entry point.

| Doc | Contents |
|---|---|
| [00 — Overview](docs/00-OVERVIEW.md) | Decisions, current repo state, glossary |
| [01 — Business Analysis](docs/01-BUSINESS-ANALYSIS.md) | Workflow modelled end to end + 15 recommended improvements |
| [02 — Architecture](docs/02-ARCHITECTURE.md) | Stack, layering, folder structure, infrastructure, security |
| [03 — Data Model](docs/03-DATA-MODEL.md) | Every table and column, ERDs, integrity & indexing rules |
| [04 — Landed Cost](docs/04-LANDED-COST.md) | The costing engine, with a fully worked example |
| [05 — UI/UX](docs/05-UIUX.md) | Design system (validated palette) + a spec for every screen |
| [06 — User Flows](docs/06-USER-FLOWS.md) | Step-by-step flows per role, with permission matrix |
| [07 — Roadmap](docs/07-ROADMAP.md) | 13 phases, dependencies, acceptance criteria, live build status |
| [08 — Deployment](docs/08-DEPLOYMENT.md) | VPS build, PostgreSQL switch, backups, redeploy, hardening |

---

## Stack

Laravel 13 · PHP 8.4 · Filament 4 · Livewire 3 · Tailwind 4 · PostgreSQL 16 · Redis ·
Meilisearch · Horizon

---

## Local setup

Requires PHP 8.4+ and Node 20+.

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build

composer dev      # serve + queue + logs + vite
```

Panel: `http://localhost:8000/admin`

```bash
composer test     # test suite
vendor/bin/pint   # code style
```

---

## Conventions

- Business rules live in `app/Actions` and `app/Services`, never in Filament pages.
- Money is never a `float` — `App\Support\Money` over `numeric(19,4)` with an explicit currency.
- Currency conversion always takes an explicit date. There is no "current rate" shortcut.
- Stock is only ever written through `StockLedger`, which appends to `stock_movements`.
- Posted documents are immutable; corrections are new documents.
