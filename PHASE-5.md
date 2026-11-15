# FurniCraft — Phase 5: Orders & Delivery

Status: **complete**. Builds on [Phase 4](PHASE-4.md).

Phase 4 ended where the customer's part ends: an order placed, paid for and confirmed. Phase 5 is everything the shop does with it afterwards.

## What was built

| Module | Where |
|---|---|
| Admin order list — counts that double as filters, search by order, invoice, customer, phone or pincode | `Admin/OrderController.php`, `admin/orders/index.blade.php` |
| Order detail — customer, address, lines with GST, consignments, returns, history, actions | `admin/orders/show.blade.php` |
| Status workflow — what may move where, and what happens when it does | `app/Services/OrderWorkflow.php` |
| Status history — every move, who made it, why, and whether the customer was told | `OrderStatusHistory`, `order_status_histories` |
| GST tax invoice — numbering, CGST/SGST/IGST, printable A4 | `app/Services/InvoiceService.php`, `admin/orders/invoice.blade.php` |
| Shipments — part consignments, couriers, tracking, own-staff delivery | `app/Services/ShipmentService.php`, `Admin/ShipmentController.php` |
| Returns, replacements and warranty claims | `app/Services/ReturnService.php`, `Admin/ReturnController.php` |
| Customer self-service — cancel, request a return | `Front/OrderActionController.php` |
| CSV export of the filtered order list | `OrderController@export` |
| Gateway refunds — send the money back through Razorpay | `RazorpayService@refund` |
| State picker at checkout and in the address book | `indian_states()`, `front/partials/state-select.blade.php` |
| Order figures on the dashboard | `Admin/DashboardController.php` |
| Status emails — confirmed, shipped, delivered, cancelled, and return updates | `OrderStatusChanged`, `ReturnUpdated` |

Tables added: `order_status_histories`, `shipments`, `shipment_items`, `order_returns`, `order_return_items`. Columns added to `orders`: `invoice_number`, `invoiced_at`, `is_interstate`, `place_of_supply`, `place_of_supply_code`, `seller_gst_number`, `delivered_at`, `cancellation_reason`, `cancelled_by`.

## Exit criteria

- [x] **An order cannot move somewhere it should not.** The workflow owns every transition; nothing writes `status` directly any more.
- [x] **An unpaid online order cannot be marked delivered.** COD is exempt, because paying later is the point of it.
- [x] **Every move is on the record** — who, when, why, and whether the customer was emailed. The staff member's name survives their account being deleted.
- [x] **The GST split follows the goods.** Intra-state is CGST + SGST, inter-state is IGST, decided from the delivery state and frozen onto the invoice when it is raised.
- [x] **An invoice number is issued once**, in its own unbroken sequence, and a reprint is identical.
- [x] **Nothing can be over-shipped or over-returned**, however many consignments or claims an order is split across.
- [x] **Stock only comes back when somebody has looked at it.**
- [x] **Everything is tested** — 483 feature tests pass (363 before this phase, 120 new).

## The order's life

```
pending ──► confirmed ──► processing ──► shipped ──► delivered ──► returned
   │            │             │             │            │
   └────────────┴─────────────┴─► cancelled │            │
                                            └─► returned ┘
```

Two moves run backwards on purpose: **shipped → processing**, for a dispatch logged against the wrong order, and **delivered → returned**, for goods that come back. Everything else is one-way. `cancelled` and `returned` are the end.

**Cancelling** releases the held stock and frees the coupon. It does **not** refund anything — money only moves on the returns screen, where somebody has deliberately recorded it. A status dropdown should never move money.

**The customer** may cancel while an order is `pending` or `confirmed`. Once it is being prepared, cancelling is a phone call. Guests cannot cancel at all: a tracking link can be forwarded, and a forwarded link must not be able to cancel somebody's sofa.

## GST invoices

Catalogue prices are GST-inclusive, so the taxable value is the line less the tax already inside it. The invoice summarises per rate, which is what GST requires, not per product.

**Which tax applies** is decided by comparing the shop's state (Settings → GST) with the delivery state:

| | |
|---|---|
| Same state | CGST + SGST, half each |
| Different states | IGST |
| State not recognised | CGST + SGST, and the unknown place of supply is printed for someone to fix |

Checkout takes the state as free text, so the matcher handles what people actually type: `UP`, `U.P.`, `Uttar Pradesh`, `New Delhi, Delhi`, `J&K`, `Orissa` all resolve. Getting this wrong is not cosmetic — a Delhi shop delivering to UP owes IGST, and quietly charging CGST + SGST puts the wrong tax on a legal document.

**What is frozen when the invoice is raised:** its number, the date, the place of supply and its code, whether it is inter-state, and the seller's GSTIN. Moving the shop to another state next year must not rewrite last year's tax.

The invoice is a self-contained A4 document with its own styles — no admin chrome, no shared stylesheet — so a reprint in three years looks the same. There is no PDF library: the browser's print dialog saves it as PDF, which also means nothing to keep patched.

## Shipments

An order can go out in parts — a bed today, the matching almirah when it is back in stock. Each consignment is numbered after its order (`FC/2611/00007-1`, `-2`) and records courier, tracking number and link, packages, weight, and either a courier or one of your own staff.

The **order status follows its consignments**, not the other way round: staff work on the van-load in front of them.

- Anything dispatched → the order is `shipped`.
- Every consignment delivered **and** nothing left to send → `delivered`.
- Half an order arriving is not a delivered order.

A consignment marked `returned` frees its units to be sent again. A **delivered** consignment cannot be deleted — it is a record of what happened; mark it returned instead.

## Returns, replacements and warranty

One table for all three: each is "something is wrong with what arrived", raised by the customer, decided by staff, ending in a refund, a replacement or a refusal.

```
requested ──► approved ──► received ──► refunded / replaced
     └──────► rejected
```

- **The refund is what was actually paid** for those units, not the catalogue price, so a checkout discount does not come back as full value. A replacement or warranty repair carries no refund.
- **Approving refunds nothing and restocks nothing.** The box has not arrived yet.
- **Receiving is where stock may return**, ticked line by line — damaged goods come back but are not sellable.
- **Recording a refund** notes that the money went back, with its reference. The refund itself happens in Razorpay or at the counter; the app never moves money by itself.
- **A refusal needs a reason**, because the customer is told it.
- A rejected claim frees its units, so the customer can raise it again.

The customer-facing window is `return_window_days` in Settings (7 by default); setting it to 0 turns self-service returns off.

## Emails

| When | To whom |
|---|---|
| Confirmed, shipped, delivered, cancelled | The customer, if staff leave "Email the customer" ticked |
| Return approved, refused, received, refunded, replaced | The customer, if ticked |

`processing` is deliberately quiet — "we have started" is not news worth an email.

A guest's emails link to the tracking page; a registered customer's link to their account. **A failed email never undoes what happened**: the status change or refund stands, the failure is logged, and the screen says the customer could not be reached.

## Settings added

| Key | Default | What it does |
|---|---|---|
| `invoice_next_sequence` | 1 | The invoice counter, separate from orders |
| `invoice_terms` | — | Printed at the foot of every invoice |
| `return_window_days` | 7 | Days after delivery a customer may raise a return; 0 turns it off |
| `return_policy_note` | — | Shown on the return form |

## Notes

- **Admin URLs address an order by its token**, as the storefront does, so order ids cannot be enumerated by counting.
- **Returns sit under the `orders` permission.** Whoever handles orders handles what comes back from them; a separate permission would be one more thing to forget to grant.
- **Refunds can be sent or recorded.** Ticking "Send it through Razorpay now" calls the gateway and stores the refund id it returns; leaving it unticked records a refund made at the counter or by transfer. If the gateway refuses, **nothing is recorded** — a refund that did not happen must never look like one that did. The option only appears when the order has a captured online payment to reverse.
- **The state is picked from a list**, at checkout and in the address book, and validated against it server-side. That is the real fix for the GST split: a hand-typed "UP" cannot be matched with certainty, and guessing wrong puts the wrong tax on a legal document. The matcher's abbreviation handling stays for addresses saved before the list existed, and an address holding an unlisted state keeps it as its own option rather than being silently moved.
- **There is no delete for an order.** It is a financial record: cancel it, never remove it. `orders.delete` grants nothing in this module.

## Fixes made during this phase

- **`pluck` with a raw `SUM()` returned nothing usable**, so every order detail page would have crashed with "Undefined property: stdClass::$quantity". The aggregate now has an alias. The tests caught it before the screen did.
- **A delivery to "UP" was taxed as CGST + SGST.** The matcher only knew full state names, so an abbreviation fell through to the intra-state default — the wrong tax on a legal document. Abbreviations, old names and "City, State" now resolve.
- **The "Email the customer" checkbox did nothing when unticked.** `boolean('notify', true)` defaults to true when the key is absent, which is exactly what an unticked checkbox sends. Unticking now means what it says.
- **The returns list trimmed `public_token` out of its eager load**, and that column is the Order route key — every link to an order on that page threw.
- **Invoice numbers shared the order counter**, which would have left gaps in the invoice sequence that an auditor would have to ask about. Each kind now counts separately.
- **`OrderService::cancel()` was a second way to cancel an order** that knew none of the workflow's rules. It now delegates, so there is one path.
- **The confirmation email had a glued `@if`** (`delivered@if`), the same Blade trap as Phase 4. `BladeHygieneTest` caught it on the first run.

## Gaps closed in the completion pass

An audit of the phase's own code turned up five things that were declared but not finished:

- **`OrderController`'s middleware named `export` and `destroy`, and neither existed.** `export` is now a streamed CSV of the filtered list — the same filters as the screen, so the file always matches what was on it. `destroy` is gone: an order is cancelled, not deleted.
- **`picked_up` and `closed` were return statuses nothing could ever set.** `picked_up` is now the step between approving and receiving, which with furniture can be days. `closed` settles a claim resolved without money or goods moving — advice given, a part posted out — so it stops sitting open or being falsely recorded as refunded.
- **`shipments.update` was a route with no screen.** Each consignment now has an inline edit for courier, tracking number and link, packages, weight, assignee, expected date and who signed for it. A tracking number usually turns up after the van has left.
- **The dashboard knew nothing about orders** after an entire orders phase. It now leads with orders to action, money received this month, money still to collect, and returns waiting — each gated by `orders.view`, so a role that cannot open the module never sees its figures.
- **Refunds could only be recorded by hand**, which invited the mistake of recording one that was never sent.
