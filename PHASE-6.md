# FurniCraft — Phase 6: Enquiry & Quotation

Status: **complete**. Builds on [Phase 5](PHASE-5.md).

Not everything a furniture shop sells is on the shelf. This phase is for the conversation that happens before an order exists: someone asks, somebody answers, a price is agreed in writing, and the agreement becomes an order.

## What was built

| Module | Where |
|---|---|
| Public enquiry form — general, about a product, custom furniture, bulk | `Front/EnquiryController.php`, `front/enquiry.blade.php` |
| Photo and sketch attachments | `EnquiryService@attach`, through the existing `ImageService` |
| Admin inbox — counts that filter, search inside what people wrote, assignment | `Admin/EnquiryController.php`, `admin/enquiries/` |
| Conversation thread — emailed replies, phone notes, internal notes | `EnquiryReply`, `EnquiryService@reply` |
| Logging an enquiry that came by phone or at the counter | `admin/enquiries/create.blade.php` |
| Quotation builder — catalogue lines and made-to-order lines, live totals | `Admin/QuotationController.php`, `resources/js/quotation-lines.js` |
| GST pricing, inclusive, with the same CGST/SGST/IGST rule as invoices | `QuotationService` |
| Printable A4 quotation | `admin/quotations/print.blade.php` |
| Customer accepts or declines online, no sign-in | `Front/QuotationResponseController.php`, `front/quotation.blade.php` |
| Accepted quotation becomes an order | `QuotationService@convertToOrder` |

Tables added: `enquiries`, `enquiry_attachments`, `enquiry_replies`, `quotations`, `quotation_items`.

## Exit criteria

- [x] **Anyone can ask without an account.** Most people enquiring about a sofa do not have one, and making them register is how a sale goes quiet.
- [x] **Nothing is lost.** The enquiry is saved before the acknowledgement is attempted, so a mail failure never swallows somebody's question.
- [x] **"Unanswered" means unanswered.** Only a reply the customer can actually see sets `responded_at`; an internal note does not.
- [x] **A quotation can price something that does not exist yet** — that is the point of quoting rather than selling off the shelf.
- [x] **The figure quoted is the figure charged.** Converting copies the totals across; it never re-prices against a catalogue that has moved.
- [x] **A quotation cannot be accepted after it lapses.**
- [x] **Everything is tested** — 544 feature tests pass (483 before this phase, 61 new).

## How a sale goes

```
enquiry → reply → quotation → sent → accepted → order
   │                            └──→ declined
   └──→ closed / spam                └──→ expired
```

The enquiry's status follows along by itself: raising a quotation marks it **quoted**, converting one marks it **converted**. Nobody has to remember to tidy up behind themselves.

## Enquiries

Four kinds share one table, because the handling is identical — a question arrives, somebody answers it, sometimes it gets priced. What differs is only which fields are filled in.

**Three ways in**, all pointing at the same form: the Enquiry button in the header, "Enquiry Now" on a product card, and "Send Enquiry" on a product page. The last two carry the slug, so the form opens with that product attached.

**The thread** holds three kinds of entry, and the difference matters:

| Channel | Sends an email | Counts as answered | Visible to the customer |
|---|---|---|---|
| Emailed | yes | yes | yes |
| Phone / WhatsApp | no | yes | it already happened |
| Internal note | no | **no** | no |

A note is staff talking to each other. Counting it as a response would make the "not answered yet" figure on the inbox a lie, and that figure is the only honest measure of whether anybody is actually getting back to people. Notes are tinted differently on screen so nobody pastes one into an email.

**Guarding the public form.** A honeypot field, hidden off-screen rather than with `display:none` because bots skip that; a 10-per-minute rate limit by IP; and a two-minute window per email address, since a second identical submission is nearly always an impatient second click rather than a second question. Spam is hidden from the inbox unless asked for — spam in the list is how an inbox stops being used.

**Attachments** go through the same optimiser as catalogue images: converted to WebP, capped at 1400px, and refused if the file is not really an image. Three per enquiry.

## Quotations

Built from an enquiry, or from nothing. Starting one from an enquiry brings the customer's details across, and if they asked about a product, that product becomes the first line.

**Lines can be anything.** Pick a catalogue product and the row fills itself in, or leave that box empty and describe what you are making. A line with no product behind it is marked *made to order*.

**Prices include GST**, exactly as in the catalogue and on orders. Quoting exclusive of tax and adding it at checkout is how a customer ends up arguing about the total. The CGST/SGST/IGST split follows the delivery state, using the same matcher the invoice uses.

**Lines are replaced wholesale**, never half-updated: a quotation is a single offer, and a document whose lines and total disagree is worse than no document.

**Validity is worked out, not stored.** A quotation past its date reads as expired and stops being answerable the moment the day turns, without waiting for a scheduled job to notice.

## The customer's copy

A link in their email, no sign-in. Accepting is not a payment and commits them to nothing irreversible — staff turn an accepted quotation into an order and the customer pays against the invoice — which is why a token is enough here when it would not be enough to cancel an order.

They can add a note when accepting, or say why when declining. Either way the shop is emailed, because nobody is watching the admin panel all day.

A **draft is a 404** to the customer: it was never sent, so as far as the world is concerned it does not exist.

## Becoming an order

A copy, not a re-interpretation. The customer agreed to those figures, so the order carries them across unchanged — totals, tax, place of supply and all.

- **No stock is held.** A quotation is usually for something being made, and a thirty-minute hold would expire before anyone acted on it.
- **The payment method is cash on delivery**, because a staff-raised order is settled against an invoice rather than through the storefront's payment step.
- **Converting twice returns the same order.** The idempotency key is the quotation itself, so a double-click cannot raise two orders for one agreement.
- Converting without a delivery address is refused — an order has to go somewhere.

## Settings added

| Key | Default | What it does |
|---|---|---|
| `enquiry_prefix` | `ENQ` | Enquiry reference prefix |
| `enquiry_next_sequence` | 1 | Its own counter |
| `quotation_next_sequence` | 1 | Its own counter |
| `quotation_validity_days` | 15 | How long a new quotation holds |
| `quotation_terms` | — | Printed on every quotation |

## Notes

- **Enquiries and quotations have their own number sequences**, like orders and invoices, so neither has gaps somebody has to explain.
- **An enquiry with a quotation against it cannot be deleted**, and a quotation the customer has seen cannot be deleted — only a draft can. Both are records.
- **The quotation line editor is a real file**, not an inline script: the Content-Security-Policy allows neither inline handlers nor inline scripts, so an `onclick` here would silently do nothing.
- **Live totals in the editor are advisory.** The server prices the quotation when it saves, and that is the figure that counts.

## Fixes made during this phase

- **The line editor's renumbering only matched digits**, so a cloned row kept `items[__INDEX__]`. Two added rows then shared a key and the second quietly overwrote the first when PHP built the array — lines would have vanished on save with nothing to show anybody why. Found by adding two rows in the browser and reading the field names.
- **The admin "log an enquiry" screen was a 500** — the controller method existed and was routed, but the view was never written. Found by opening the page.
- **A bad image upload on the enquiry form showed no error at all.** The `InvalidImageException` handler picked a field from a hard-coded list that knew nothing about `attachments`, so the message was attached to a field that is not on the page. It now uses whichever field actually carried a file, which also means the next form with an upload works without anyone remembering to extend a list.
- **The editor's live total used Indian digit grouping** while the saved page used Western, so the figure changed shape after saving. The editor now matches `money()`.

## Worth doing next

- **`money()` groups in thousands, not lakh.** `₹ 104,000` would read as `₹ 1,04,000` to an Indian customer, and `amount_in_words()` already says "One Lakh Four Thousand". Changing the helper would touch every price on the site, so it is worth doing deliberately rather than in passing.
