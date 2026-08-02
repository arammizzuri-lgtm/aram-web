# Import & Wholesale ERP — System Overview

> **Status:** Design phase complete. Implementation begins at Phase 0.
> **Last updated:** 2026-08-01

---

## 1. What this system is

A vertical ERP for a **China-import → local-wholesale** business. It is built around one
central question that generic ERPs answer badly:

> *"What did this item **actually** cost me, landed in my warehouse, and what did I
> **actually** make when I sold it?"*

Everything else — purchasing, shipping, inventory, sales, finance — exists to feed or
consume that number.

### What it is not

- Not a generic inventory app. Stock is a *consequence* of shipments arriving.
- Not a bookkeeping app. Accounting is a *reporting view* over operational documents.
- Not a retail POS. The customer is a shop, buying on credit, in cartons.

---

## 2. Confirmed decisions

| Decision | Choice | Rationale |
|---|---|---|
| **Front end** | Filament 4, heavily themed | Already scaffolded. Tables, filters, bulk actions, RBAC, global search are production-grade out of the box — roughly 70% of ERP surface area. Design budget goes into a custom theme + hand-built Dashboard, Landed-Cost workbench and Analytics pages. |
| **Backend** | Laravel 13 / PHP 8.4 | Already scaffolded, mature, excellent for document-heavy business logic. |
| **Market** | Iraq / Kurdistan | Dual-currency reality (buy USD/CNY, sell IQD + USD). Customs duty at border. No broad VAT — tax is configurable per document, defaults off. |
| **Currencies** | CNY, USD, IQD (extensible) | Every monetary column stores `amount` + `currency` + `exchange_rate` + `base_amount`. Base currency = **USD** (recommended — see §4). |
| **Deployment** | Cloud VPS, multi-user | PostgreSQL 16, Redis, queue workers, nightly off-site backups. Staff log in from anywhere. |
| **AI** | Claude API, behind a provider interface | Natural-language querying, auto-generated insights, reorder suggestions. Key added when ready; nothing else blocks on it. |
| **Costing method** | Moving weighted average + per-shipment cost layers | See [04-LANDED-COST.md](04-LANDED-COST.md). |
| **Accounting depth** | AR / AP / cash ledger + inventory valuation in v1; double-entry GL designed but deferred | Delivers every number in the Finance module without forcing the owner to think in debits and credits. |

---

## 3. What already exists in this repo

A clean Laravel 13 + Filament 4 skeleton with ~25 migrations and matching Eloquent
models. It is a good foundation and is **kept**, not discarded.

**Already present and reusable:**

| Area | Tables |
|---|---|
| System | `users` (+branch, phone, is_active), `branches`, `settings`, `permission_tables`, `activity_log` |
| Catalog | `product_categories`, `units`, `products` |
| Inventory | `warehouses`, `stock_levels`, `stock_movements` |
| Purchasing | `suppliers`, `purchase_orders(+items)`, `goods_receipts(+items)`, `supplier_bills`, `supplier_payments` |
| Sales | `customers`, `quotations(+items)`, `sales_orders(+items)`, `delivery_notes(+items)`, `invoices(+items)`, `payments` |

Good patterns already in place, which the rest of the system will follow:

- `HasLineItems` trait — per-line tax on the discounted amount, summarised on the document.
  Correct for mixed tax rates; kept and extended.
- `HasDocumentNumber` trait — document numbering.
- `CalculatesLineTotal` trait — line maths in one place.
- `stock_movements` uses a polymorphic `reference` + `balance_after`. This is the right
  shape for an audit-grade stock ledger and becomes the backbone of inventory valuation.
- Enums for every status field.

**Completely missing — this is the actual project:**

- ❌ **No UI whatsoever.** Zero Filament resources exist. The panel shows a blank dashboard.
- ❌ Shipments / containers / freight forwarders
- ❌ Landed cost engine — the single most important feature
- ❌ Multi-currency (no currency column exists anywhere)
- ❌ Supplier↔product catalogue mapping (supplier SKUs, MOQ, lead times, price history)
- ❌ Price-list import (Excel/CSV)
- ❌ Expenses, cash & bank accounts, payment allocations
- ❌ Stock reservations, adjustments, transfers, damaged stock
- ❌ Returns / credit notes
- ❌ Dashboard, analytics, reports, PDF invoices
- ❌ Notifications, AI, backups

---

## 4. Base-currency recommendation

**Recommendation: base currency = USD.**

Reasoning:

- Purchases, freight, insurance and customs valuation are all quoted or settled in USD.
  Landed cost is naturally a USD number.
- IQD is quoted against USD, not the reverse. Using IQD as base means every cost figure
  carries a conversion error that compounds through the costing engine.
- Reporting in IQD stays available — it is a *presentation* conversion at the edge, using
  the rate on the reporting date.

Sales in IQD store `amount_iqd`, `exchange_rate`, and `base_amount` (USD). Profit is
computed in USD; dashboards offer a USD/IQD toggle.

If you'd rather report primarily in IQD, that's a one-line settings change — the
architecture supports either. The recommendation is about *which currency the maths
happens in*, and USD is the correct answer for an importer.

---

## 5. Documentation map

| Doc | Contents |
|---|---|
| **00-OVERVIEW.md** | This file. Decisions, scope, glossary. |
| [01-BUSINESS-ANALYSIS.md](01-BUSINESS-ANALYSIS.md) | Your workflow modelled end to end, plus 15 concrete recommended improvements. |
| [02-ARCHITECTURE.md](02-ARCHITECTURE.md) | Tech stack with justifications, layering, folder structure, infrastructure, security. |
| [03-DATA-MODEL.md](03-DATA-MODEL.md) | Every table, every column, ERD diagrams, indexing and integrity rules. |
| [04-LANDED-COST.md](04-LANDED-COST.md) | The costing engine: allocation bases, estimated→final revaluation, worked example. |
| [05-UIUX.md](05-UIUX.md) | Design system (colour, type, spacing, motion) and a spec for every screen. |
| [06-USER-FLOWS.md](06-USER-FLOWS.md) | Step-by-step flows for each role, with keyboard-first interactions. |
| [07-ROADMAP.md](07-ROADMAP.md) | 13 phases, dependencies, deliverables and acceptance criteria. |

---

## 6. Glossary

Shared vocabulary — used consistently in code, UI and docs.

| Term | Meaning |
|---|---|
| **Landed cost** | True per-unit cost of an item in your warehouse: goods + freight + insurance + duty + clearance + inland transport + any other shipment charge, allocated to that item. |
| **Allocation basis** | The rule used to spread a shipment-level cost across items: by value, by weight, by volume (CBM), by quantity, or manually. Choosing correctly is what makes furniture and crystals both price accurately. |
| **Estimated vs Final landed cost** | Goods often arrive and sell before the clearance agent's final invoice. Costs start estimated, then get finalised, triggering a revaluation. |
| **Base currency** | The currency all maths happens in. USD here. |
| **Transaction currency** | The currency a document was actually issued in (CNY / USD / IQD). |
| **CBM** | Cubic metre — shipping volume. The billing unit for LCL sea freight. |
| **FCL / LCL** | Full Container Load / Less than Container Load. |
| **Incoterm** | Who pays for what leg of the journey (EXW, FOB, CIF, DDP). Determines which costs you will be billed for. |
| **HS code** | Harmonised System tariff code. Determines the customs duty rate for a product. |
| **Proforma invoice (PI)** | The supplier's quote/order confirmation. Basis for the deposit payment. |
| **B/L** | Bill of Lading — the sea-freight shipping document. |
| **Goods in transit** | Paid-for stock that has left the supplier but has not arrived. An asset, not an expense. |
| **On hand / Reserved / Available** | Physically present / committed to sales orders / on hand minus reserved. |
| **Incoming** | Quantity on confirmed POs or in-transit shipments, not yet received. |
| **Moving weighted average** | Costing method: each receipt blends its landed cost into the running average cost of that product. |
| **COGS** | Cost of Goods Sold — landed cost of items at the moment they were invoiced. |
| **Price tier** | A named customer price level (Wholesale, VIP, Retail). |
| **Aging** | Receivables/payables bucketed by how overdue they are (0-30 / 31-60 / 61-90 / 90+). |
