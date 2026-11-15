# FurniCraft — Phase 7: Inventory & Reports

Status: **complete**. Builds on [Phase 6](PHASE-6.md).

Until now `products.stock_quantity` was a number that moved with nothing to explain it — a sale, a return, somebody editing the field, a CSV import. When the shelf and the screen disagree, and eventually they always do, there was no way to find out where it went wrong. This phase is mostly about fixing that, and then reporting on what the fixed numbers say.

## What was built

| Module | Where |
|---|---|
| Stock ledger — every change, and why | `app/Services/StockLedger.php`, `stock_movements` |
| Inventory screens — stock on hand, one product's history, the whole ledger | `Admin/InventoryController.php`, `admin/inventory/` |
| Counts, write-offs and corrections | `StockLedger@countTo`, the adjustment panel |
| Low-stock levels, per product or shop-wide | `Product@scopeLowStock`, `low_stock_threshold` |
| Suppliers | `Admin/SupplierController.php`, `suppliers` |
| Purchase orders and receiving | `app/Services/PurchaseService.php`, `purchase_orders` |
| Customers, from the shop's side | `Admin/CustomerController.php` |
| Sales, product, customer and GST reports with CSV export | `app/Services/ReportService.php`, `Admin/ReportController.php` |
| Opening balances for stock that predates the ledger | `inventory:open-balances` |

Tables added: `stock_movements`, `suppliers`, `purchase_orders`, `purchase_order_items`. Columns added to `products`: `low_stock_threshold`, `cost_price`.

## Exit criteria

- [x] **Nothing writes `stock_quantity` except the ledger.** Sales, returns and deliveries all go through one door.
- [x] **Stock cannot go negative**, and a refused movement leaves the count exactly as it was.
- [x] **No adjustment without a reason.** The reason is the record somebody reads later.
- [x] **Deliveries arrive in parts** and the order says so, without ever accepting more than was ordered.
- [x] **Cancelled and returned orders are not sales** in any report.
- [x] **GST is reported by invoice date**, because that is when it is owed.
- [x] **Everything is tested** — 601 feature tests pass (544 before this phase, 57 new).

## The ledger

Every movement is a signed quantity with a reason, and the balance afterwards is stored alongside it, so the ledger reads like a bank statement — you can point at a row and say the count was this, without re-adding everything above it.

```
Opening balance                                 +14    14   System
Stock count adjustment                           -3    11   FurniCraft Admin
  "Monthly count — three missing from the floor"
Received from supplier  PO/2611/00001            +6    17   FurniCraft Admin
```

| Kind | What it means |
|---|---|
| `opening` | What was on the shelf when tracking began |
| `sale` | An order was paid for |
| `return` | Goods came back and were fit to sell |
| `purchase` | A delivery from a supplier |
| `adjustment` | A stock count found a difference |
| `damage` | Written off |
| `correction` | A mistake in the ledger being put right |

**The count and the ledger row are written together**, in one transaction with the row locked, so two people receiving the same delivery cannot both read the old balance and leave the ledger saying something the stock does not.

**There is no "edit the number" screen.** Staff record what they counted, or what was written off, and the ledger works out the difference. "We found three fewer than the system thought" is the useful fact, and it is what a stock count actually produces. A count that matches records nothing at all.

**Run `php artisan inventory:open-balances` once after upgrading.** Without it the ledger starts mid-story: the catalogue says forty of something and the ledger explains none of it. It is idempotent — a product with any history is left alone — and `--dry-run` shows what it would write.

## Low stock

A product has its own reorder level or falls back to the shop-wide default in Settings: a three-piece sofa set and a pack of screws should not share a threshold, but most products never need one of their own. With variants, the level is compared against the sum across them.

## Suppliers and purchasing

A purchase order is a quotation turned around — the same lines, the same inclusive pricing, but we are the ones paying.

```
draft ──► ordered ──► partial ──► received
  └──────────┴──► cancelled
```

- **Costs include GST**, like selling prices, so nobody has to remember which figures on which screen include tax.
- **Only a draft can be edited.** Once it has gone to the supplier it is a record of what was agreed, and editing it behind their back is how a delivery arrives against lines nobody recognises.
- **Receiving is where stock arrives**, through the ledger, with the purchase order number on every line. Deliveries come in parts and the order tracks what is still outstanding.
- **A line with no catalogue product cannot be stocked** — freight and packing have no shelf to go on.
- **Receiving records what we paid**, keeping the product's cost price current for the valuation report.
- **An order with a part delivery cannot be cancelled**: that stock is already on a shelf, and cancelling would leave it unexplained.

## Reports

Every figure obeys one rule: **a cancelled or returned order is not a sale**. They are counted separately so the loss is visible rather than hidden by its own exclusion.

**Revenue means money received**, not money invoiced. A cash-on-delivery order nobody has collected on is not revenue, and a report that says otherwise is the kind that gets believed until the bank statement disagrees.

| Report | What it answers |
|---|---|
| Sales | Received, invoiced, outstanding, average order, day by day |
| What sold | Units and revenue per product |
| Who bought | Orders and spend per customer |
| GST summary | One row per rate, split CGST/SGST or IGST |
| Stock | Value at cost, units on hand, products with no cost price |

**The GST summary counts by invoice date**, not order date, because GST is owed on invoices raised. An order with no invoice does not appear.

**"This financial year" runs April to March**, which is what it has to mean on an Indian shop's reports.

Each table exports as a CSV carrying the filters that were on screen. The day-by-day chart is drawn in CSS — no chart library to keep patched, and it prints with the page.

## Customers

Read-mostly on purpose. Staff can see who somebody is, what they have bought and what they are worth, and disable an account that is causing trouble. They **cannot** change an email or a password: an account somebody else can silently take over is not an account. There is no route for it, and a test asserts there never is.

## Settings added

| Key | Default | What it does |
|---|---|---|
| `low_stock_threshold` | 5 | The shop-wide reorder level |
| `stock_alert_email` | — | Where low-stock warnings would go |
| `purchase_prefix` | `PO` | Purchase order numbering |
| `purchase_next_sequence` | 1 | Its own counter |

## Notes

- **The line editor is now one script** serving both quotations and purchase orders, configured from the table's own attributes. Two near-identical copies drift apart the first time somebody fixes a bug in one of them.
- **`cost_price` is per product, not per variant.** Variants of the same product usually cost the same to make; if that stops being true, the column moves to the variant.
- **Low-stock alerts are not emailed yet.** The setting exists and the list is on the inventory screen; sending needs the scheduler, which `DEPLOY.md` covers.

## Fixes made during this phase

- **MySQL refused the low-stock query.** `HAVING` cannot reference a bare column without a `GROUP BY`, so comparing the summed variant stock against `products.low_stock_threshold` failed outright. Both sides are now aliases.
- **`StockService` wrote `stock_quantity` directly** in two places, and `ProductController` and the CSV importer in two more. Sales and returns now go through the ledger; the other two are the next thing worth routing through it.

## Worth doing next

- **Catalogue edits still bypass the ledger.** Changing stock on the product form, or importing a CSV with a `stock_quantity` column, moves the number without a line explaining it — exactly what this phase set out to stop. Both should become ledger movements of type `correction`.
- **Low-stock emails** once a worker is running.
- **`money()` groups in thousands, not lakh** — still true, still worth a deliberate pass. `₹ 179,499` would read as `₹ 1,79,499` to an Indian customer, and `amount_in_words()` already says it that way.
