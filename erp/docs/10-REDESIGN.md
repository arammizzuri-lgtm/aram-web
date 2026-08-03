# Redesign — the deal-centred system

> This supersedes `03-DATA-MODEL.md`, `04-LANDED-COST.md` and the inventory half
> of `02-ARCHITECTURE.md`. Those describe a business that holds stock. This one
> does not.

> **Build status — 2026-08-02.** Every module below is built.
> **334 tests · 1,057 assertions · Pint clean · 20 screens verified in a browser.**
>
> | Module | State |
> |---|---|
> | Deals — lines carrying cost and sell together | ✅ |
> | Deal lines picked from the price lists, both sides filled | ✅ |
> | Deal stage — moves itself as you buy and ship, settable by hand | ✅ |
> | Deal screen carries its quotations, purchases, shipping and invoices | ✅ |
> | Deleting anything — soft, restorable, on every screen | ✅ |
> | Recently deleted — one place to find and restore from | ✅ |
> | Customer accounts — statement, balance chart, ageing, credit carried forward | ✅ |
> | Quotations — photos + frozen approval snapshot | ✅ |
> | Purchases + supplier payments with real transfer cost | ✅ |
> | Consignments — 3 modes, freight split across deals | ✅ |
> | Customer invoices — goods + shipping, EN/Sorani RTL PDFs | ✅ |
> | Price lists — crystals matrix, textile, packaging, furniture | ✅ |
> | Selling prices per customer type — products | ✅ |
> | Selling prices per customer type — crystals, catalogue items | ⚠ tables and reader built, no entry screen yet |
> | Dashboard + 7 reports with CSV export | ✅ |
> | Two roles — owner sees all, assistant sees no cost | ✅ |
>
> **The one gap.** `crystal_sell_prices` and `catalogue_item_sell_prices` are
> read by the deal screen but can only be filled from the database — the crystal
> matrix and the catalogue screens each need a selling-price mode of their own.
> Until then, price those lines by markup or by hand, which is what §4 says you
> do for most of them anyway.
>
> **Before real use:** the suite has only ever run against SQLite. Run it against
> PostgreSQL on the server first — see §3 of `08-DEPLOYMENT.md`.

---

## 1. The one idea

The old system was built around a **warehouse**: goods arrive, sit, get costed,
get sold from stock. Everything — the ledger, the average costing, the four-pass
landed-cost engine — existed to answer *"what is sitting in my warehouse worth?"*

You never hold stock. You buy only what someone has already asked for. So that
question has no meaning for you, and every mechanism built to answer it is dead
weight.

The new centre is the **deal**: one customer's request, followed from *"they
asked"* to *"they paid"*.

```
                          ┌─────────────┐
        Customer  ───────▶│    DEAL     │◀─────── your profit lives here
        asks for          └──────┬──────┘
        products                 │
                    ┌────────────┼─────────────┬──────────────┐
                    ▼            ▼             ▼              ▼
              QUOTATION     PURCHASES    CONSIGNMENTS    INVOICES
              (photos,      (one per     (tracking       (goods,
               approval)     supplier)    numbers)        shipping)
                                 │             │              │
                                 ▼             ▼              ▼
                          supplier         freight       customer
                          payments          split         payments
```

Everything else in the system exists to serve that picture.

---

## 2. What a deal holds

A deal is one customer request. Underneath it:

| Part | How many | Why |
|---|---|---|
| **Lines** | many | What the customer wants. Each line carries **both** what it costs you and what you charge. |
| **Purchases** | one per supplier | You often buy from several suppliers for one customer. Each is a separate private purchase record with its own payments. |
| **Quotations** | one or more versions | Each with photos. Approval freezes a snapshot. |
| **Consignments** | many | One order can arrive under several tracking numbers. |
| **Customer invoices** | one or more | Goods now, shipping later. Neither ever changes after issue. |

### The line is the key design decision

The single biggest thing you asked for was **not typing the same thing twice**.
So a deal line carries both sides at once:

```
┌── what the customer wants ──┬── what it costs me ──┬── what I charge ──┐
│ Crystal P07 · 20mm · 500pcs │ Supplier A · ¥12.50  │  IQD 28,000       │
│                             │ = $1.74 ea           │  = $19.05 ea      │
│                             │                      │  profit $8,655 ▲  │
└─────────────────────────────┴──────────────────────┴───────────────────┘
                                        ▲                      ▲
                              only you see this        customer sees this
```

You enter the line once. From it the system derives:

- the **purchase invoice** for Supplier A (all lines where supplier = A)
- the **customer invoice** (all lines, sell side only)
- the **profit** (sell minus cost, per line and per deal)

No re-entry anywhere. Picking a supplier on a line silently creates or attaches
that deal's Purchase record for that supplier — you never "create a purchase",
it appears because you said where the goods come from.

### Why not separate purchase lines from sale lines?

Because it doubles your typing for a case that rarely differs. Where it *does*
differ — you buy 4 pieces but sell "1 set" — you use two lines and link them.
The common case stays one line.

---

## 3. Money

### Home currency: USD

You buy in RMB, sell in IQD. Neither can measure the other, so everything
converts to **USD** for profit and reporting. Your documents still show RMB, USD
or IQD exactly as you and your customers expect — the conversion is only for
answering "did this make money?"

### Rates are typed per deal and then frozen

Two rates per deal, and only the ones actually needed:

- **RMB → USD** — needed when a purchase is in yuan
- **IQD → USD** — needed when a sale is in dinars

Both pre-fill from the last rate you used, so you adjust rather than type from
blank. Once the deal is saved they are **frozen onto it**.

> **Why freezing matters.** Without it, changing the rate would silently rewrite
> the profit on every deal you have ever done. A March deal that made $400 would
> show $310 in June with nothing changed but a setting. Your past would keep
> moving. Frozen rates mean history stays still.

Every money row stores four things: `amount`, `currency`, `exchange_rate`, and a
frozen `base_amount` in USD. The `Money` value object (bcmath, exact to four
decimal places) survives from the old system unchanged — it was always the
strongest part.

### The real cost of sending money

You told me the quoted rate isn't what you actually pay. So a supplier payment
records **two** numbers:

```
Supplier invoice        ¥50,000
Quoted rate                7.20  →  looks like $6,944
What it actually cost me           $7,100
                                   ───────
Hidden cost                          $156   ← counted against the deal
```

Without this, every deal overstates its profit by a small amount that never
looks like an error — just a permanent gap between the reports and your pocket.

---

## 4. Pricing

You use three methods and **mix them inside one deal**. All three are supported
per line, switchable:

| Method | You type | System fills |
|---|---|---|
| **Markup %** | cost + "25%" | selling price |
| **Manual** | selling price | profit and margin % shown live beside it |
| **From price list** | pick the product | both cost and selling price |

Plus a **deal-level lump** — an amount added to the whole order for your effort,
arranging, or whatever you decide.

### The honest bit about the lump

A lump belongs to the *deal*, not to any product in it. So when a report asks
"which product earned me most?", the lump has to be spread somehow to answer —
and any spread is a guess.

**The system will label per-product profit as approximate whenever a lump is
involved**, and show the lump separately. Deal-level profit stays exact. I would
rather show you an honest "≈" than a precise-looking number that isn't.

---

## 5. Price lists

Your existing price lists survive and get extended. They now hold **both sides**:

- **Cost** — per supplier. The same 20mm crystal costs differently from Supplier
  A than Supplier B. This is the existing structure, unchanged.
- **Selling price** — one per product, varying by **customer type**. Your
  customer doesn't care where you sourced it; a cheaper supplier means more
  profit, not a cheaper price.

New on every product: **contains battery** (yes/no).

### Custom products

Typed freely on the deal — description, specification, photos, price. Nothing is
saved to your catalogue unless you press **save for next time**. That keeps
one-off items from silting up your product list while still letting you promote
the ones that repeat.

---

## 6. Quotations, photos and approval

This is new, and it is the part that protects you in an argument.

A quotation is a **visual document**: each line can carry supplier photos. You
send it as a PDF in the customer's language.

When the customer approves, the system takes a **frozen snapshot** — which
models, which photos, which quantities, which prices, approved by whom, on what
date. That snapshot never changes, even if you later edit the deal.

> Without this, "you approved this model" is your word against theirs, and the
> evidence is somewhere in a WhatsApp thread. With it, you open the deal and
> point at the record.

Editing an approved deal creates a **new quotation version**. The old one stays,
marked superseded. You can always see what changed and when.

### Approval is a warning, not a wall

You said approval before buying is usual but not always. So creating a purchase
on an unapproved deal shows:

> ⚠ This customer hasn't approved yet. Continue anyway?

You can push straight through. The purchase is then flagged **at your own risk**,
and the dashboard shows the total value of goods you've bought that nobody has
committed to. That number is the one worth watching.

---

## 7. Shipping

### Three modes

| Mode | Priced by | Note |
|---|---|---|
| Sea | CBM (space) | slowest, cheapest |
| Air without battery | weight (kg) | |
| Air with battery | weight (kg) | restricted, more expensive |

Products marked **contains battery** trigger a warning before you book a mode
that won't accept them — *before* you commit to a delivery date, not after the
booking is rejected.

### Consignments

You log what your forwarder gives you:

```
Tracking No.     16940
Mode             Sea
From warehouse   Guangzhou collection point
Boxes            1
Gross weight     18.50 kg
CBM              0.11
Status           awaiting → in transfer → arrived → delivered
Freight cost     (typed when the bill arrives)
```

**A deal can have several tracking numbers. A tracking number can carry several
deals.** Both directions happen, so the link is many-to-many.

### The freight split

When a consignment carries goods for **one** deal, the freight is simply that
deal's cost and no split UI appears at all.

When it carries **several**, you split it. The system suggests a starting number
based on the mode:

- **Sea** → split by CBM
- **Air** → split by weight

You accept the suggestion or type your own. This is closer to how you're
actually billed than splitting by goods value, which would over-charge small
expensive crystals and under-charge bulky cheap fabric.

The forwarder's rates are not stored — you told me they just send a bill, so you
type the amount. Weight and CBM are recorded anyway, because they are what makes
the split honest.

### Chinese collection points

Your forwarder's warehouses are **addresses**, not storage. Each holds a name,
city, address in English and Chinese, and a contact — so you can hand a supplier
the exact delivery details. No stock, no quantities, nothing to reconcile.

---

## 8. Two invoices, and they never change

### Internal purchase invoice — private

One per supplier per deal. Never visible to a customer, never printed on
anything they see. Holds:

supplier · purchase prices · currency (RMB/USD) · amounts paid · additional
purchasing costs · your real total cost

### Customer sales invoice — shared

One or more per deal: **goods** now, **shipping** later once you know the cost.
Holds selling prices, currency (IQD/USD), totals, and the tracking numbers so
the customer can follow their goods.

### Why invoices are snapshots

An invoice copies its lines at the moment you issue it. Editing the deal
afterwards does not change an invoice already sent.

> If invoices were live views of the deal, a customer holding a printed invoice
> and you looking at the screen would see different numbers. That is how
> arguments start. A document you have handed someone must never silently change.

Corrections are made by cancelling and re-issuing, which leaves a visible trail.

---

## 9. Customer money

Every customer has an **account page**: one screen with the balance, a statement
of everything that has passed between you, how overdue the rest is, and the
deals and invoices behind it.

### The account reads like a bank statement

The system's own arithmetic is a receivable — invoiced less received, so a
customer who owes you is a *positive* number. On the account page the sign is
turned over, because that is the screen you look at with the customer in front
of you:

```
        deposits    +   what they paid you
        spending    −   what you invoiced them
        withdrawals −   what you refunded
        ─────────────────────────────────────
        balance         below zero: they owe you
                        above zero: you hold their money
```

Both are the same fact, and they cannot drift: the account balance is
`outstandingBalance()` negated, not a second calculation.

**Matching moves nothing.** The balance is what came in against what went out,
whether or not anybody has said which payment settles which invoice. Matching
decides *which* invoice is settled — which is what makes the ageing meaningful.

### Leftover credit carries itself forward

A payment that clears three invoices and leaves $4.77 does not ask you what to
do with it. The remainder stays as the customer's credit and goes onto their
next invoice the moment one is raised, oldest credit first. An advance paid
before the deal existed behaves the same way.

Four dollars is not worth a decision, and asking for one every time is how it
ends up forgotten on an account for a year.

### Matching is reversible

`unallocate()` had been written since the beginning and nothing ever called it,
so money matched to the wrong invoice stayed there — and because an invoice with
money against it cannot be cancelled, one wrong click could wedge an account.
**Unmatch** is on every payment now. Nothing is lost by it: the payment stays
exactly as recorded, the balance does not move, and the money goes back to being
credit.

---

Money lands on the **customer's account** first, then gets matched to invoices.

```
Payment received: $2,000 from Ali
        ↓
Lands on Ali's account immediately
        ↓
System suggests: "Apply to INV-0012 ($1,400) and INV-0015 ($600)?"
        ↓
You accept with one click, or adjust, or leave it as credit
```

This makes all five of your situations ordinary rather than special:

| Situation | How it works |
|---|---|
| Pays after approving | Payment arrives, matched to the goods invoice |
| Advance before the order exists | Sits on the account as credit until there is something to match |
| Pays goods now, shipping later | Two invoices, matched separately |
| Still owes you | Account balance is positive in your favour |
| You owe them | Account balance is negative — a credit you hold |

Nothing is ever stuck waiting for bookkeeping. The payment is safe the moment
you record it; matching can happen whenever.

### Supplier money mirrors it

Purchases can be paid in **instalments** (deposit then balance) or **in full**.
Each payment records the supplier amount *and* what the transfer really cost you.

---

## 9b. Deleting, and taking it back

Everything can be deleted, on every screen, and **nothing that is deleted is
gone**. That combination is the whole design: what makes a delete button
frightening is that it is final, and once it is not, you can tidy without
thinking twice.

Before this there were three screens that could delete anything at all. A
tracking number typed wrong, a supplier created twice, a payment recorded that
never arrived — all permanent. The only way to blunt a wrong payment was to edit
the amount, which rewrites history rather than correcting it.

### The dialog tells you what it costs

"Are you sure?" is not a question — it asks you to confirm something you have
not been told. So the confirmation names what hangs off *this* record: how many
deals, how much has been paid, and for a payment, whose balance moves and by how
much. Where there is a gentler move it offers that instead — **deactivate** a
supplier you still have history with, **cancel** an invoice the customer is
holding a copy of.

None of it is a wall. It is the difference between a decision and a reflex.

### Three ways back

| | |
|---|---|
| **Undo**, in the notification | For the second of regret, before you have moved |
| **Deleted records** filter, on each screen | For when you know where you left it |
| **Settings › Recently deleted** | Everything, newest first, when you do not |

### Erasing for good

Separate, owner-only, and offered only where nothing points at the record —
which is also where the foreign keys would refuse. A button that exists and then
fails is worse than one that is absent.

### One consequence worth knowing

A deleted record gives up its code, so "SUP-A" can be used again immediately.
The price is that restoring the original then fails, because something else
holds its code. The restore says so plainly rather than showing a database
error.

---

## 10. Who sees what

Two roles only, down from five:

| | Owner (you) | Assistant |
|---|---|---|
| Create and edit deals | ✅ | ✅ |
| Customer prices and invoices | ✅ | ✅ |
| Quotations, photos, approvals | ✅ | ✅ |
| Consignments and tracking | ✅ | ✅ |
| Record customer payments | ✅ | ✅ |
| **Supplier costs** | ✅ | ❌ |
| **Profit and margins** | ✅ | ❌ |
| **Purchase invoices** | ✅ | ❌ |
| **Supplier payments** | ✅ | ❌ |

The hiding must be **total**: not just the deal screen, but every report, every
CSV export, every PDF, and the AI assistant's answers. A cost that leaks through
one export defeats the entire arrangement.

---

## 11. Documents and language

- **Screens**: English.
- **Customer documents**: English **or Sorani Kurdish**, set per customer, so
  printing never requires a decision.
- Sorani is Arabic script and reads **right-to-left**, so those documents are a
  mirrored layout, not a translation. This is why rendering goes through a real
  browser — it is what lays out bidirectional text correctly.
- Products keep names in English, Kurdish and **Chinese** — the Chinese name is
  what you send your supplier so there is no confusion about what you ordered.

---

## 12. Screens

| Screen | What it is for |
|---|---|
| **Dashboard** | Deals needing action · money owed to you · money you owe suppliers · consignments in transit · **value bought without approval** |
| **Deals** | The list. Filter by status, customer, what is waiting on you. |
| **Deal** | The main working screen. Lines, quotation, purchases, consignments, invoices, payments, profit — all in one place. |
| **Quotations** | Build with photos, send, record approval. |
| **Consignments** | Log tracking numbers, split freight, watch status. |
| **Customers** | Account balance, deal history, documents, language setting. |
| **Suppliers** | Balance, purchase history, payments. |
| **Price lists** | Crystals matrix · textile · packaging · furniture — now with selling prices. |
| **Payments** | Money in from customers, money out to suppliers. |
| **Reports** | Profit by deal, customer, product · receivables · payables · shipping costs. |
| **Settings** | Customer types, collection points, company profile, users. |

---

## 13. What gets deleted

| Removed | Why |
|---|---|
| Warehouses, stock levels | You hold no stock |
| Stock movements ledger | Nothing moves in or out of stock |
| Moving weighted average cost | Cost is per deal, known exactly, never blended |
| **Landed cost engine** (4-pass allocation) | Built to spread container costs across stock. Replaced by per-deal costs plus an occasional two-way freight split |
| Stock reservations | Nothing to reserve |
| Goods receipt | Goods go to the customer, not to you |
| Reorder levels, low-stock alerts | Meaningless without stock |
| 3 of 5 user roles | Two people use this |

The valuable half survives: the money engine, multi-currency with frozen rates,
suppliers and customers, the price lists you specified, browser-rendered PDFs,
safe document numbering, payment matching, and the change history.
