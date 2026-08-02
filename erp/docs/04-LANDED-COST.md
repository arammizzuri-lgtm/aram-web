# The Landed Cost Engine

> The single most important component in this system. Everything downstream — margins,
> pricing, profit reports, reorder decisions — is only as truthful as this calculation.

---

## 1. The problem, concretely

A container carries crystal chandeliers, sofas and fabric rolls. Sea freight is billed by
the volume the container consumes. Insurance is billed on declared value. Customs duty is
legally set per HS code. These are three completely different distributions.

Most ERPs (and every spreadsheet) spread all shipment costs by **value**, because it's the
easy one. §4 shows exactly what that costs you: in the worked example below, the naive
method under-costs a sofa set by **$88.20 per unit**, enough that selling at "cost + 20%"
loses money on every sale while the system reports a healthy profit.

---

## 2. Allocation bases

| Basis | Formula for line *i* | Use for |
|---|---|---|
| `volume` | `cost × CBM_i / ΣCBM` | Sea freight, port charges, inland trucking, demurrage |
| `weight` | `cost × kg_i / Σkg` | Air freight, courier |
| `value` | `cost × value_i / Σvalue` | Insurance, clearance fee, bank charges |
| `quantity` | `cost × qty_i / Σqty` | Per-unit handling, labelling |
| `per_line_hs` | `CIF_i × duty_rate_i` | **Customs duty — not an allocation at all, a per-line calculation** |
| `manual` | operator-entered amounts | Inspection of one product, sample charges |
| `none` | excluded | Costs that shouldn't touch product cost |

Each `shipment_cost_type` carries a default basis; every individual cost line can override it.

---

## 3. Calculation order matters

Customs duty is levied on **CIF value** (goods + freight + insurance), not on goods value.
So freight and insurance must be allocated *before* duty can be computed. The engine runs
in dependency passes:

```
PASS 1  Pre-CIF costs        freight, insurance          → per-line CIF established
   ▼
PASS 2  Duty                 CIF_i × duty_rate_i         → per-line, HS-driven
   ▼
PASS 3  Value-based fees     clearance, bank charges
   ▼
PASS 4  Post-arrival costs   port, inland transport, demurrage
   ▼
        landed_unit_cost = (goods_i + Σ all allocations_i) / quantity_i
```

`LandedCostCalculator` orchestrates the passes; one `CostAllocator` strategy class per
basis does the maths. Adding a new basis is a new strategy, not a change to the engine.

---

## 4. Worked example — SHP-2026-0014

**Container:** 40HQ, sea FCL, Ningbo → Umm Qasr. Base currency USD.

### 4.1 Items

| # | Product | Qty | Unit cost | Goods value | kg/unit | Total kg | CBM/unit | Total CBM | HS duty |
|---|---|---:|---:|---:|---:|---:|---:|---:|---:|
| 1 | Crystal Chandelier `CRY-0042` | 100 | $85.00 | $8,500 | 12 | 1,200 | 0.08 | 8.00 | 15% |
| 2 | Sofa Set `FUR-0117` | 20 | $220.00 | $4,400 | 45 | 900 | 1.60 | 32.00 | 20% |
| 3 | Fabric Roll `FAB-0233` | 300 | $18.00 | $5,400 | 8 | 2,400 | 0.06 | 18.00 | 10% |
| | **Total** | **420** | | **$18,300** | | **4,500** | | **58.00** | |

### 4.2 Costs

| Cost | Amount | Basis |
|---|---:|---|
| Sea freight (40HQ) | $3,200.00 | volume |
| Insurance (1% of value) | $183.00 | value |
| Customs duty | *calculated* | per_line_hs |
| Clearance agent | $450.00 | value |
| Bank charges | $95.00 | value |
| Port charges | $380.00 | volume |
| Inland transport | $600.00 | volume |

### 4.3 Pass 1 — freight (volume) and insurance (value)

Freight share = `CBM_i / 58.00`:

| # | CBM share | Freight |
|---|---:|---:|
| 1 | 13.7931% | $441.3793 |
| 2 | 55.1724% | $1,765.5173 |
| 3 | 31.0345% | $993.1034 |
| | | **$3,200.0000** |

> Note line 2: the sofas are 11% of goods value but consume **55% of the container**. A
> value-based split would charge them $769 instead of $1,766.

Insurance is exactly 1% of line value: **$85.00 / $44.00 / $54.00**.

**CIF per line:**

| # | Goods | + Freight | + Insurance | = CIF |
|---|---:|---:|---:|---:|
| 1 | $8,500.00 | $441.3793 | $85.00 | **$9,026.3793** |
| 2 | $4,400.00 | $1,765.5173 | $44.00 | **$6,209.5173** |
| 3 | $5,400.00 | $993.1034 | $54.00 | **$6,447.1034** |

### 4.4 Pass 2 — customs duty (per HS code, on CIF)

| # | CIF | Rate | Duty |
|---|---:|---:|---:|
| 1 | $9,026.3793 | 15% | $1,353.9569 |
| 2 | $6,209.5173 | 20% | $1,241.9035 |
| 3 | $6,447.1034 | 10% | $644.7103 |
| | | | **$3,240.5707** |

### 4.5 Passes 3 & 4 — value fees, then post-arrival volume costs

Value shares: 46.4481% / 24.0437% / 29.5082%

| # | Clearance | Bank | Port (vol) | Inland (vol) |
|---|---:|---:|---:|---:|
| 1 | $209.0164 | $44.1257 | $52.4138 | $82.7586 |
| 2 | $108.1967 | $22.8415 | $209.6552 | $331.0345 |
| 3 | $132.7869 | $28.0328 | $117.9310 | $186.2069 |
| | $450.0000 | $95.0000 | $380.0000 | $600.0000 |

### 4.6 Result

| # | Product | Goods | Allocated costs | Total landed | **Landed unit cost** | Uplift |
|---|---|---:|---:|---:|---:|---:|
| 1 | Crystal Chandelier | $8,500.00 | $2,268.6507 | $10,768.6507 | **$107.6865** | +26.7% |
| 2 | Sofa Set | $4,400.00 | $3,723.1487 | $8,123.1487 | **$406.1574** | +84.6% |
| 3 | Fabric Roll | $5,400.00 | $2,156.7713 | $7,556.7713 | **$25.1892** | +39.9% |
| | **Total** | **$18,300.00** | **$8,148.5707** | **$26,448.5707** | | +44.5% |

Reconciliation: `Σ allocated = $8,148.5707` = the sum of every shipment cost. ✔

### 4.7 What the naive method would have told you

Spreading all $8,148.5707 by value:

| Product | **Correct** | Naive (all-by-value) | Error |
|---|---:|---:|---:|
| Crystal Chandelier | $107.69 | $122.85 | **+$15.16 over-costed (+14%)** |
| Sofa Set | $406.16 | $317.96 | **−$88.20 under-costed (−22%)** |
| Fabric Roll | $25.19 | $26.01 | +$0.82 |

**The sofa is the lesson.** Priced at "cost + 20%" using the naive figure:

```
Naive cost      $317.96
Selling price   $381.55      ← what you'd charge
Actual cost     $406.16
─────────────────────────
Real result     −$24.61 per sofa LOSS
System reports  +$63.59 per sofa PROFIT
```

You would sell 20 sofas, believe you made $1,272, and actually lose $492 — and because the
crystals are simultaneously over-costed, you'd turn down chandelier orders that were
genuinely profitable.

---

## 5. Estimated → Actual → Final

### 5.1 Lifecycle

| Stage | Trigger | Effect |
|---|---|---|
| **`none`** | Shipment created | No costing yet |
| **`estimated`** | Costs entered with `is_estimated = true` | Run v1. Stock can be received and priced. Dashboard shows an "estimated" badge everywhere the cost appears. |
| **`actual`** | Real invoices replace estimates one by one | New run version each time the totals move materially |
| **`final`** | Operator locks the shipment | Final run. **Revaluation** posted. Shipment closed for costing. |

Runs are **versioned**, never overwritten. Version 1 stays queryable forever, so you can
always answer "what did we think this cost in March?"

### 5.2 Revaluation, worked

Continuing the example: the clearance agent's real invoice arrives at **$1,250**, not the
estimated $450. Delta = **+$800**, basis `value`.

| # | Delta allocated | Δ unit cost | New unit cost |
|---|---:|---:|---:|
| 1 Crystal | $371.58 | +$3.7158 | $111.4023 |
| 2 Sofa | $192.35 | +$9.6175 | $415.7749 |
| 3 Fabric | $236.07 | +$0.7869 | $25.9761 |

By now, 60 chandeliers have been sold and 40 remain:

```
COGS adjustment      60 × $3.7158 = $222.95   → posted as a COGS correction;
                                                the original invoices are NOT edited,
                                                but their recorded gross profit is restated
Inventory adjustment 40 × $3.7158 = $148.63   → stock_movements revaluation row,
                                                stock_levels.average_cost updated
```

Both land in `cost_revaluations`, and a `stock_movements` row of type `revaluation` keeps
the ledger reconcilable.

### 5.3 Guard-rails

- The dashboard shows **"Shipments awaiting final costing"** with an age counter. Anything
  over 30 days goes red.
- A shipment cannot be finalised while any cost line is still flagged estimated.
- Margin reports display an "estimated cost" marker on any line whose shipment isn't final,
  so nobody quotes off provisional numbers without knowing.

---

## 6. Costing method: moving weighted average + shipment layers

**Method: moving weighted average, per product per warehouse.**

On each receipt:

```
new_avg = (qty_on_hand × current_avg + qty_received × landed_unit_cost)
          ────────────────────────────────────────────────────────────
                        qty_on_hand + qty_received
```

Why weighted average rather than FIFO:
- Wholesale importers sell mixed-container stock interchangeably; FIFO layers create
  bookkeeping churn with no decision value.
- It smooths FX and freight volatility instead of making March's margin look great and
  April's terrible for the same product.

**But** every `stock_movements` row also stores `shipment_id`. That gives per-container
traceability *without* FIFO's complexity, and enables the report generic ERPs can't
produce:

> **Container SHP-2026-0014** — landed $26,448.57 · sold-through 68% · revenue to date
> $22,140 · realised margin 31.4% · projected total margin 29.8%

Which is how you learn which suppliers, categories and container mixes actually make money.

---

## 7. Rounding discipline

- All intermediate arithmetic at **6 decimal places**.
- Line results rounded to **4 dp**.
- The residual (`cost − Σ rounded shares`, always < 0.0001 × line count) is assigned to the
  **largest line by basis value**, so `Σ allocations = cost` **exactly**.
- Enforced by an assertion in `CostAllocator`; a mismatch throws rather than silently
  leaving a gap.

In §4.3 this is visible: the raw shares round to $3,199.9999, and the $0.0001 residual goes
to line 2, giving $1,765.5173.

---

## 8. Implementation surface

```
app/Services/Costing/
├── LandedCostCalculator.php      # orchestrates the 4 passes, versions the run
├── CostAllocator.php             # contract
├── Allocators/
│   ├── ValueAllocator.php
│   ├── WeightAllocator.php
│   ├── VolumeAllocator.php
│   ├── QuantityAllocator.php
│   ├── HsDutyCalculator.php      # per-line, CIF × rate
│   └── ManualAllocator.php
├── CifCalculator.php
├── RevaluationService.php        # estimated → final, splits COGS vs inventory
└── ShipmentMarginReport.php

app/Actions/Costing/
├── CalculateLandedCost.php       # (Shipment, bool $final) → LandedCostRun
├── ApplyLandedCostRun.php        # writes product costs + stock valuation
├── FinaliseShipment.php          # locks, triggers revaluation
└── RevalueShipment.php
```

**Test fixtures:** §4 and §5.2 become the primary test cases. Every number in those tables
is asserted, including the residual rounding in §4.3. If the engine ever drifts, these
tests fail first.
