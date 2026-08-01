# User Flows

Each flow lists the actor, the trigger, every step, what the system does behind the scenes,
and the click/keystroke budget. Where a flow could be slow, the fast path is called out.

---

## F1 — Price list arrives from a supplier

**Actor:** Manager · **Trigger:** WeChat message with an Excel attachment · **Budget: ~6 clicks**

| # | User | System |
|---|---|---|
| 1 | Catalog › Price Lists › **Import** | |
| 2 | Drag the `.xlsx` in, pick supplier "Ningbo Lighting" | Detects a saved `import_profile` for this supplier → **skips mapping entirely** |
| 3 | *(first time only)* Click the header row, confirm auto-mapped columns, `Save as profile` | Persists `column_map` for next time |
| 4 | | Queued job parses sheet → `price_list_import_rows`; matches on `supplier_sku` → `barcode` → `sku` → fuzzy name; computes old vs new |
| 5 | Review the diff: `42 new · 118 ↑ · 9 ↓ · 1,035 same · 3 suspicious` | Suspicious = |Δ| > 50%, flagged `critical` |
| 6 | Uncheck the 3 suspicious rows, click **Commit** | Transaction: upserts `supplier_products`, writes `supplier_product_prices` history rows, creates 42 draft products, updates `price_list_imports` counters |
| 7 | | Notification: "1,204 rows committed. **[Undo]**" — undo stays live |

**Failure paths:** unreadable file → error with the offending row numbers, nothing written.
Ambiguous match → inline product picker in step 5. Duplicate supplier SKU in the sheet →
flagged, last-wins is explicit not silent.

---

## F2 — Placing a purchase order

**Actor:** Manager · **Trigger:** stock running low, or a customer commitment · **Budget: ~10 keystrokes/item**

1. `G` `P`… or **⌘K → "new purchase order"**.
2. Pick supplier. Currency, incoterm, payment terms, deposit % all default from the
   supplier record.
3. Search the item panel by your SKU *or* their supplier SKU. `⏎` adds it.
4. Type quantity **in cartons**. System computes pieces via `pack_size` and shows both.
   Right rail shows MOQ, last purchase price with % change, current stock, incoming.
5. `⌘S` saves as draft; **Confirm** transitions to `confirmed`.

**System:** allocates `PO-2026-####` under a row lock; freezes today's exchange rate onto
the document; increments `stock_levels.incoming_quantity`; creates a deposit payment
schedule from `deposit_percent`; logs to `activity_log`.

**Guard-rails:** below-MOQ lines warn. A supplier over their credit terms warns. Confirming
is blocked if any line lacks a price.

---

## F3 — Deposit and balance payments

**Actor:** Accountant · **Budget: 4 clicks**

1. Purchasing › Supplier Bills → the PO's proforma appears with deposit due.
2. **Record payment** → amount, date, bank account, method.
3. System converts at the **rate on the payment date**, stores `base_amount`, computes
   `fx_gain_loss` against the rate frozen on the PO, writes a `cash_transactions` row and
   decrements the account balance.
4. PO timeline updates; supplier outstanding balance recalculates.

---

## F4 — Building a shipment

**Actor:** Manager · **Trigger:** supplier says goods are ready

1. Logistics › Shipments › **New**.
2. Enter container number, B/L, forwarder, method, ports, ETD/ETA.
3. **Add items from POs** → a picker lists all confirmed, unshipped PO lines across every
   supplier. Tick lines, adjust quantities for **partial** shipments.
4. System snapshots each item's weight and CBM from the product record, and totals
   `total_weight_kg` / `total_volume_cbm` — these become the allocation denominators.
5. Save → status `booked`, `shipment_events` timeline starts.

**Why the snapshot matters:** if a product's carton dimensions are corrected next year, the
historical shipment's allocation must not change. The snapshot guarantees that.

---

## F5 — Costing a container ⭐ the critical flow

**Actor:** Owner or Manager · **Trigger:** costs start arriving

### Stage 1 — Estimate (before arrival)
1. Open the shipment → **Landed Cost** tab.
2. Add each expected cost: type, amount, currency, and confirm the **allocation basis**
   (defaulted correctly per type — volume for freight, value for insurance, per-HS for duty).
3. Leave `estimated ✓` ticked. **Calculate** → run v1, `landed_cost_status = estimated`.
4. Products now show an estimated landed cost, badged everywhere it appears.

### Stage 2 — Actuals (as invoices arrive)
5. Replace each estimate with the real figure; untick `estimated`. Each material change
   creates a new run version. Previous versions stay queryable.

### Stage 3 — Finalise
6. **Finalise & apply**. The confirmation dialog shows precisely what will happen:
```
   Finalising will post:
   ├─ Inventory adjustment   +$148.63   (units still on hand)
   └─ COGS correction        +$222.95   (units already sold, 3 invoices affected)
   Original invoices are not modified; their recorded profit is restated.
```
7. On confirm: `cost_revaluations` rows written, `stock_movements` revaluation entries
   posted, `products.average_cost` and `stock_levels` updated,
   `landed_cost_status = final`, the dashboard alert clears.

**Blocked if:** any cost line is still flagged estimated. **Nagged if:** a shipment sits
`estimated` more than 30 days — it goes red on the dashboard.

---

## F6 — Receiving a container

**Actor:** Warehouse · **Trigger:** truck at the door · **Budget: 1 scan per line**

1. Inventory › Goods Receipt → pick the arrived shipment. Every expected line is
   pre-loaded with its expected quantity.
2. Scan barcodes or `⏎` down the list confirming quantities. Short/over/damaged
   discrepancies are entered per line with a reason.
3. **Post receipt.**

**System (one transaction):** writes `stock_movements` of type `purchase_receipt` valued at
the current landed unit cost, stamped with `shipment_id`; recomputes each product's moving
weighted average; increments `stock_levels.quantity` and decrements `incoming_quantity`;
routes damaged units to the damaged bucket; updates PO `received_quantity` and closes the
PO if complete; advances the shipment to `delivered`; notifies anyone waiting on those SKUs.

---

## F7 — Selling to a shop

**Actor:** Sales · **Budget: ~8 keystrokes/item**

1. **⌘K** → customer name → their profile, or Sales › New Order.
2. Prices auto-fill from the customer's **price tier**; the rail shows what they paid last
   time and how many are **available** (on hand minus reserved — never raw on-hand).
3. Add lines. If a line drops below `min_selling_price`, a warning appears; below cost is
   blocked for the Sales role.
4. **Confirm order** →
   - Credit check: if `outstanding + order total > credit_limit`, a blocking banner appears
     and the order can only proceed with Manager approval (recorded in
     `credit_approved_by`).
   - Stock is **reserved** (`stock_reservations`), so nobody else can sell the same boxes.
5. Warehouse picks → **Delivery Note** → stock movement `sale` posted at current average
   cost, reservation consumed.
6. **Create invoice** from the delivery note (one click, all lines carried over).
   `invoice_items.unit_cost_base` snapshots COGS at this moment — this is what makes
   historical margin reporting truthful even after later revaluations.
7. **Post** → immutable. PDF generated by Chromium, bilingual, ready to send or print.

---

## F8 — Getting paid

**Actor:** Accountant · **Trigger:** shop pays $5,000 against four invoices

1. Sales › Payments › **Record payment** → customer, amount, date, method, account.
2. The allocation panel lists every open invoice oldest-first, with a
   **[Auto-allocate]** button that fills them in order.
3. Adjust manually if the customer specified which invoices.
4. Save.

**System:** writes `payment_allocations` rows; updates each `invoices.amount_paid` and
status (`partially_paid` → `paid`); any remainder sits in `unallocated_amount` as customer
credit, usable on the next invoice; writes `cash_transactions`; recomputes the customer's
outstanding balance and aging buckets.

**Invariant enforced:** `Σ allocations ≤ payment.amount`, and per invoice
`Σ allocations ≤ invoice.total`. Over-allocation is impossible, not merely discouraged.

---

## F9 — Recording an expense

**Actor:** Anyone with permission · **Budget: 3 fields**

1. **⌘K** → "new expense".
2. Category, amount, date. Drag the receipt photo in.
3. *If it belongs to a container:* set **Link to shipment**. The expense immediately appears
   as a `shipment_cost` on that shipment's landed-cost tab and flags the run as needing
   recalculation.
4. Save.

This link is what stops logistics costs leaking into general overhead and quietly
under-stating your true product cost.

---

## F10 — A customer returns goods

**Actor:** Sales / Warehouse

1. Open the invoice → **Create return**. Pick lines and quantities.
2. Mark each line's condition: `good` → back to sellable stock at the **original landed
   cost**; `damaged` → damaged bucket, written off.
3. Post → `stock_movements` type `return_in`, a **credit note** is generated and linked to
   the original invoice, and the customer's balance reduces. The original invoice is never
   edited.

---

## F11 — The morning check (Owner)

**Budget: 1 page, 0 clicks**

Open the dashboard:
1. **Attention band** first — overdue invoices, shipments awaiting final costing, low stock.
2. Cash position and the 13-week forward view — is there enough to cover committed
   container payments?
3. Revenue vs COGS and margin trend — is profitability holding?
4. Container board — what lands this week.
5. **AI daily brief** — 3–5 generated observations, e.g. "Fabric margins fell 4.2 points
   this month; supplier prices rose 11% in the June import."

Anything needing action is one click from the tile that flagged it.

---

## F12 — Asking a question in plain language

**Actor:** Owner · **`⌘J`**

> "Which products from Ningbo Lighting have the highest margin?"

1. The assistant selects tools — `find_products(supplier: …)`, `get_product_margins(…)` —
   each a parameterised, permission-scoped query. **No SQL is generated.**
2. The answer renders as a real sorted table plus a bar chart, obeying the chart rules in
   [05-UIUX.md](05-UIUX.md).
3. Every answer shows which tools ran, and links to the underlying records.
4. Follow-ups keep context: "now only the ones with stock under 50".

**Permission scoping:** a Sales user asking the same question gets prices but no cost or
margin data — the tool layer filters, not the prompt.

---

## F13 — Month end (Accountant)

1. Reports › Monthly Performance → pick the month.
2. Review P&L: revenue → COGS → gross profit → operating expenses → net profit.
3. Reconcile: bank balances vs `cash_transactions`; receivables aging; payables aging.
4. Check the **estimated-costing** list — any shipment still provisional distorts the
   month's COGS and should be finalised first.
5. Export PDF + Excel. Optionally schedule it to run automatically next month.

---

## F14 — Onboarding a new user

**Actor:** Owner

1. Settings › Users › **Invite** → name, email, role, branch.
2. Role grants a permission set; individual permissions can be toggled from there.
3. Invitation email → the user sets their own password on first login.
4. Everything they do from then on is in `activity_log`.

**Role defaults:**

| | Owner | Manager | Sales | Warehouse | Accountant |
|---|:---:|:---:|:---:|:---:|:---:|
| Dashboard (full) | ✔ | ✔ | limited | limited | finance |
| See cost & margin | ✔ | ✔ | ✖ | ✖ | ✔ |
| Products | ✔ | ✔ | read | read | read |
| Price list import | ✔ | ✔ | ✖ | ✖ | ✖ |
| Suppliers | ✔ | ✔ | ✖ | read | read |
| Purchase orders | ✔ | ✔ | ✖ | read | read |
| Shipments | ✔ | ✔ | read | ✔ | read |
| **Landed cost** | ✔ | ✔ | ✖ | ✖ | read |
| Goods receipt | ✔ | ✔ | ✖ | ✔ | ✖ |
| Customers | ✔ | ✔ | ✔ | ✖ | read |
| Sales orders | ✔ | ✔ | ✔ | read | read |
| Invoices | ✔ | ✔ | create | ✖ | ✔ |
| Payments | ✔ | ✔ | ✖ | ✖ | ✔ |
| Expenses | ✔ | ✔ | ✖ | ✖ | ✔ |
| Credit approval | ✔ | ✔ | ✖ | ✖ | ✖ |
| Reports | ✔ | ✔ | own | ✖ | ✔ |
| Settings / Users | ✔ | ✖ | ✖ | ✖ | ✖ |

---

## F15 — Handling a notification

Bell in the topbar, grouped by type, with a filter. Each notification carries a direct
action:

| Notification | Action offered |
|---|---|
| Low stock: 11 products | → filtered product list → **Create PO** |
| Shipment SHP-014 arrived | → **Start goods receipt** |
| Invoice INV-0231 overdue 14 days | → **Send reminder** / **Record payment** |
| Supplier payment due in 3 days | → **Record payment** |
| Price change >20% on import | → **Review import** |
| Credit limit exceeded | → **Approve** / **Reject** |
| Shipment estimated >30 days | → **Finalise landed cost** |

Rules are configurable per role in Settings › Notifications, across in-app, email and
(future) WhatsApp channels.
