# Business Analysis & Recommended Improvements

---

## Part 1 — Your workflow, modelled

### 1.1 The cash-conversion cycle

This is the spine of the business. Money leaves months before it comes back.

```
Day 0     Day 5      Day 35        Day 65      Day 95     Day 100    Day 160
  │         │           │             │           │          │          │
  ▼         ▼           ▼             ▼           ▼          ▼          ▼
Price    PO sent    Deposit       Balance      Container   Customs    Customer
list     to         paid (30%)    paid (70%)   sails       cleared,   pays
arrives  supplier                              → in        stock in   invoice
                                               transit
  └──────────────── CASH OUT ─────────────────────────┘   └── SELL ──┘  CASH IN

                    ~100 days of capital tied up before a single sale
```

**Implication for the system:** the most valuable number on the dashboard is not revenue.
It is **cash position + committed outflows + goods-in-transit value**. A profitable
importer can still go broke by over-committing to containers. The dashboard is designed
around this.

### 1.2 The document chain

Every physical event has a document. The system's job is to make each document create the
next one with as little typing as possible.

```
                    ┌─────────────────┐
                    │  PRICE LIST     │  Excel from supplier (any format)
                    │  (Excel/CSV)    │
                    └────────┬────────┘
                             │ import → match → approve
                             ▼
                    ┌─────────────────┐
                    │ SUPPLIER PRODUCT│  supplier SKU ↔ your SKU, price, MOQ
                    │   + price history│
                    └────────┬────────┘
                             │ select items
                             ▼
                    ┌─────────────────┐      ┌──────────────────┐
                    │ PURCHASE ORDER  │─────▶│ SUPPLIER BILL    │  (their proforma)
                    │  (USD / CNY)    │      │  + PAYMENTS      │  deposit 30%
                    └────────┬────────┘      └──────────────────┘  balance 70%
                             │ goods ready
                             ▼
                    ┌─────────────────────────────────────┐
                    │           SHIPMENT                  │  container, B/L, ETD/ETA
                    │  many POs → one container           │
                    │  one PO → many containers           │
                    └────────┬────────────────────────────┘
                             │
                 ┌───────────┴────────────┐
                 ▼                        ▼
        ┌─────────────────┐     ┌──────────────────┐
        │ SHIPMENT COSTS  │     │ EXPENSES         │  freight, duty, clearance,
        │ (per shipment)  │◀────│ (linked)         │  port, inland trucking
        └────────┬────────┘     └──────────────────┘
                 │
                 ▼
        ┌────────────────────────────────────┐
        │      LANDED COST RUN               │  ◀── THE CORE ENGINE
        │  allocate every cost to every unit │
        │  by value / weight / CBM / qty     │
        └────────┬───────────────────────────┘
                 │ true unit cost
                 ▼
        ┌─────────────────┐
        │ GOODS RECEIPT   │  stock in, valued at landed cost
        └────────┬────────┘
                 ▼
        ┌─────────────────┐
        │   INVENTORY     │  on hand / reserved / incoming / damaged
        └────────┬────────┘
                 │
                 ▼
   ┌─────────────────────────────────────────────────┐
   │ QUOTATION → SALES ORDER → DELIVERY → INVOICE     │
   │                              │                   │
   │                              ▼                   │
   │                       PAYMENTS (partial, credit) │
   └─────────────────────────────────────────────────┘
                 │
                 ▼
        ┌─────────────────┐
        │ TRUE PROFIT     │  invoice total − landed COGS − allocated opex
        └─────────────────┘
```

### 1.3 The five roles and what they actually do all day

| Role | Lives in | Needs above all |
|---|---|---|
| **Owner (you)** | Dashboard, Landed Cost, Analytics, AI | Cash position, real margins, what to reorder |
| **Manager** | Purchasing, Shipments, Suppliers | Container status, supplier prices, PO progress |
| **Sales** | Customers, Products, Sales Orders | Instant "what's the price and do we have it?" |
| **Warehouse** | Goods Receipt, Inventory, Delivery | Fast receiving, accurate counts, pick lists |
| **Accountant** | Invoices, Payments, Expenses, Reports | Receivables, payables, expense capture, exports |

---

## Part 2 — Fifteen recommended improvements

These come from analysing the workflow above. Each is a design decision I am making
unless you tell me otherwise. **#1, #2, #3 and #6 are the ones that matter most.**

---

### ① Allocate shipment costs by the *correct basis* — never by a flat average

**The problem.** A container holds crystal chandeliers (heavy, dense, expensive) and
flat-pack furniture (light, huge, cheap). If you divide $6,000 of sea freight evenly
across all units, or even by value, the furniture is massively under-costed and the
crystals massively over-costed. You will think furniture is profitable when it isn't.

**The fix.** Every shipment cost carries an **allocation basis**:

| Cost type | Correct basis | Why |
|---|---|---|
| Sea freight (LCL) | **Volume (CBM)** | Carriers bill by CBM. Furniture eats the container. |
| Sea freight (FCL) | **Volume (CBM)** | Fixed container price, but space consumed is the fair share. |
| Air freight | **Weight (chargeable)** | Airlines bill by kg / volumetric kg. |
| Insurance | **Value** | Premium is a % of declared value. |
| Customs duty | **Per-line HS rate × customs value** | Duty rate is legally per HS code — *not* an allocation at all. |
| Clearance agent fee | **Value** (or per-line flat) | Usually a flat fee per declaration. |
| Port / demurrage | **Volume** | Space and time in the yard. |
| Inland trucking | **Volume** | Truck space. |
| Bank transfer fees | **Value** | Proportional to the money moved. |
| Inspection | **Manual** or **Quantity** | Often applies to one product only. |

The system defaults each cost type to the right basis and lets you override per cost line.
Full worked example in [04-LANDED-COST.md](04-LANDED-COST.md).

> This single decision is the difference between an ERP that tells you the truth and one
> that quietly lies to you.

---

### ② Landed cost is **estimated first, final later** — with revaluation

**The problem.** The container arrives Monday. You start selling Tuesday. The clearance
agent's real invoice arrives three weeks later and it's $800 more than estimated. Those
sales are already recorded at the wrong cost.

**The fix.** Three-stage lifecycle:

1. **Estimated** — at booking, using budgeted rates. Enough to receive stock and quote prices.
2. **Actual** — as each real invoice arrives, replace the estimate. System shows the delta.
3. **Final** — you lock the shipment. A **revaluation** runs:
   - Units still in stock → inventory value adjusted.
   - Units already sold → a COGS adjustment entry is posted, and affected invoices' recorded
     profit is corrected. The original invoice is *never* edited.

The dashboard flags `Shipments awaiting final costing` so nothing is forgotten.

---

### ③ Separate **your product** from **the supplier's item**

**The problem.** You buy "水晶吊灯 A-330" from Supplier A and the identical item as
"CRY-CHAND-A330" from Supplier B, at different prices and MOQs. Your SKU is `CRY-0042`.
If price lists import against your SKU, nothing ever matches.

**The fix.** A `supplier_products` join carrying: supplier SKU, the supplier's own product
name (**including the Chinese name** — you need it to talk to them on WeChat), their
currency, current price, MOQ, lead time, carton pack size, and whether they're the
preferred source. Price lists import and match against *this*, not against your catalogue.

Bonus you get for free: **supplier price comparison** for the same product, and
"who should I reorder this from?"

---

### ④ Price-list import must be a reviewable, reversible pipeline — not a one-shot upload

**The problem.** Every Chinese supplier's Excel is different: headers on row 4, merged
cells, prices in a column called "USD/PC", Chinese column names, three sheets. A naive
importer will corrupt your catalogue.

**The fix.** Five stages, each with an undo:

```
UPLOAD → MAP COLUMNS → MATCH → PREVIEW DIFF → COMMIT
         (saved per      (SKU/    (colour-coded:   (atomic,
          supplier as     barcode/  new / price ↑ /  reversible,
          a profile)      name)     price ↓ / same)  price history written)
```

- **Mapping profiles** are saved per supplier — the second import of the same file format
  is one click.
- The **diff preview** is the safety net: you see "42 new, 118 price increases (avg +6.2%),
  3 suspicious (>50% change)" before anything is written.
- Every committed change writes a `supplier_product_price_history` row, so you can chart
  a supplier's price drift over years.

---

### ⑤ Buy in cartons, sell in pieces — model unit conversion explicitly

**The problem.** You order 200 cartons; each carton has 24 pieces; you sell pieces. If the
system only knows one unit, every quantity is ambiguous and the landed cost per piece
is wrong by a factor of 24.

**The fix.** Each product carries a **base unit** (the unit stock is held and sold in) and
a **purchase unit** with a `pack_size` factor. POs are entered in cartons and stored in
base units. Carton dimensions and weight also live here — they feed the CBM/weight
allocation in ①.

---

### ⑥ Track cost in three currencies, at the rate on the day

**The problem.** A single global "USD rate" setting is wrong the moment the rate moves.
Your payables to a Chinese supplier were incurred at one rate and settled at another —
that difference is real money (FX gain/loss).

**The fix.** Every monetary row stores four things:

```
amount            1,240.00
currency          CNY
exchange_rate     0.1402          ← the rate on this document's date
base_amount       173.85          ← USD, computed and frozen
```

Rates live in an `exchange_rates` table keyed by date. Historic documents never change
when today's rate moves. FX differences on settlement are surfaced as a finance line.

---

### ⑦ Goods in transit is an **asset**, shown separately

Money you've paid for stock that's on a ship is not an expense and not yet inventory. It
gets its own line in inventory valuation and its own dashboard tile. Without this, your
balance appears to vanish for 60 days every time you order.

---

### ⑧ Reservations, so Sales can't sell the same box twice

`Available = On hand − Reserved`. Confirming a sales order reserves stock; delivery
converts the reservation into a movement; cancelling releases it. Sales sees *available*,
never raw on-hand.

---

### ⑨ Payment allocation across multiple invoices

Local shops pay $5,000 against four outstanding invoices. A `payment_allocations` table
splits one payment across many invoices (and supports over-payment held as credit).
Modelling payments as one-to-one with invoices — which the current schema does — breaks
on the first real customer.

---

### ⑩ Customer credit limits with soft blocking

Wholesale on credit is where importers lose money. Each customer gets a credit limit and
payment terms. When a sales order would breach the limit, the system warns Sales and
requires Manager approval to proceed. Aging buckets (0-30/31-60/61-90/90+) on every
customer profile.

---

### ⑪ Document state machines with immutable posted documents

`Draft → Confirmed → Posted → (Closed | Cancelled)`. Once posted, a document is
read-only; corrections happen via credit notes, adjustments and revaluations. This is
what makes the audit trail trustworthy and is a prerequisite for any accountant taking
the numbers seriously.

---

### ⑫ Variants without a variant explosion

Crystals and fabrics come in colours and sizes. Full variant matrices add heavy complexity
to every screen. **Recommendation:** each sellable item is its own product record, linked
by an optional `product_group_id` and described by a flexible `attributes` JSON column
(`{"color":"Gold","size":"80cm"}`). You get grouped browsing, attribute filtering and
per-variant costing, without a variant engine. If you later need a true matrix, the group
is already there to build on.

---

### ⑬ HS codes on products, duty rates driven by them

Customs duty is legally determined per HS code. Putting `hs_code` and `duty_rate` on the
product (with a category default) means duty is *calculated* per line rather than
allocated as a lump — which is both more accurate and defensible if customs queries it.

---

### ⑭ Per-shipment margin analysis

Because every unit remembers which shipment it arrived on, you get a report generic ERPs
can't produce: **"Container SHP-2026-014 cost me $48,200 all-in and has generated $61,400
in sales so far, 62% sold through, projected margin 27%."** This is how you learn which
suppliers and product mixes actually work.

---

### ⑮ Skip full double-entry accounting in v1 — but design for it

You are not an accountant, and forcing debits/credits into daily operations slows
everyone down for no benefit. **Recommendation:** v1 implements AR, AP, cash/bank ledger,
inventory valuation and COGS directly — which produces every figure in your Finance
module (revenue, COGS, gross profit, opex, net profit, cash flow, receivables, payables).
A `journal_entries` / `journal_lines` structure is designed in [03-DATA-MODEL.md](03-DATA-MODEL.md)
and can be switched on in a later phase if an external accountant needs formal ledgers,
without reworking anything.

---

## Part 3 — Risks I want to flag now

| Risk | Mitigation built into the design |
|---|---|
| Garbage supplier data corrupts the catalogue | Staged import with diff preview + full undo |
| Landed cost finalised late → wrong margins for weeks | Estimated/final lifecycle + dashboard alert + revaluation |
| Over-committing cash to containers | Cash-forward dashboard: committed outflows vs expected inflows |
| Customer credit blowouts | Credit limits, aging, approval gate |
| One person leaves with all the supplier knowledge | Supplier contacts, WeChat IDs, Chinese product names, documents and price history all in the system |
| Data loss on a single VPS | Nightly encrypted off-site backups + activity log |
| Rate changes silently rewriting history | Frozen `base_amount` on every document |
