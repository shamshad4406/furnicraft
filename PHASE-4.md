# FurniCraft — Phase 4: Cart, Checkout & Payments

Status: **complete**. Builds on [Phase 1](PHASE-1.md), [Phase 2](PHASE-2.md) and [Phase 3](PHASE-3.md).

## What was built

| Module | Where |
|---|---|
| Cart — add, change quantity, remove, line problems, pincode and coupon boxes | `app/Services/CartService.php`, `Front/CartController.php`, `front/cart.blade.php` |
| Wishlist — toggle from any product card, move to cart | `Front/WishlistController.php`, `front/wishlist.blade.php` |
| Pricing — GST extracted from the price, delivery by zone and weight, coupon discount, COD advance | `app/Services/PricingService.php` |
| Coupons — percentage, fixed amount, free delivery; minimum order, caps, total and per-customer limits, date window | `app/Services/CouponService.php`, `Admin/CouponController.php` |
| Checkout — address form, saved-address picker, order summary, payment choice | `Front/CheckoutController.php`, `front/checkout.blade.php` |
| Stock holds — 30-minute reservation taken when the order is placed, committed on payment, released on failure | `app/Services/StockService.php` |
| Orders — placement, idempotency, state transitions | `app/Services/OrderService.php`, `app/Services/OrderNumberService.php` |
| Razorpay — server-side order creation, signature verification, API re-confirmation, webhook | `app/Services/RazorpayService.php`, `Front/PaymentController.php` |
| Cash on delivery — zone-gated, with an admin-set advance on large orders | `PricingService::codAllowed()`, `codAdvance()` |
| My account — dashboard, profile, password, saved addresses, order history and order detail | `Front/AccountController.php`, `Front/AddressController.php`, `front/account/` |
| Order tracking — open to guests, by emailed link or by order number and email | `Front/OrderTrackingController.php`, `front/track-order.blade.php`, `front/track-status.blade.php` |
| Order confirmation email | `app/Mail/OrderConfirmation.php`, `emails/orders/confirmation.blade.php` |

Tables added: `carts`, `cart_items`, `wishlists`, `addresses`, `coupons`, `coupon_usages`, `orders`, `order_items`, `payments`, `payment_webhooks`, `stock_reservations`.

## Exit criteria

- [x] **A visitor can buy without an account** — guest checkout places an order, and whatever is in the cart or wishlist comes with them if they sign in later.
- [x] **Money is never taken on the client's word** — an order is marked paid only after the signature verifies, the Razorpay API confirms the payment is captured, and the amount matches. All three are tested, including forged and tampered callbacks.
- [x] **The same checkout cannot create two orders** — an idempotency key per attempt; a refresh or double-click returns the order already created.
- [x] **Stock cannot be oversold** — rows are locked while a hold is taken; a hold that cannot be met aborts the whole order.
- [x] **One confirmation email per order** — a claim-then-send column, so a retried callback and a duplicate webhook cannot email twice. A send failure is rolled back so it can be retried.
- [x] **Everything is tested** — 363 feature tests pass (154 before this phase, 209 new).

## Checkout, end to end

1. `GET /cart` — lines, problems (stock, price change), pincode check, coupon box.
2. `GET /checkout` — address form, pre-filled from the default saved address when signed in.
3. `POST /checkout` — validates, takes a 30-minute stock hold, creates a **pending** order under an idempotency key. An unmet hold aborts before any order exists.
4. `GET /checkout/{token}/pay` — Razorpay checkout, or a COD confirmation.
5. `POST /payment/{token}/callback` — signature → API confirm → amount match → paid.
6. `POST /webhooks/razorpay` — the same settlement path, arriving independently of the browser.
7. `GET /checkout/{token}/success` — receipt; the confirmation email has gone out.

Orders are addressed by `public_token`, not by order number: the number is an admin-configurable format that may contain slashes, and a token cannot be guessed by counting.

## Payment safety

| Risk | What stops it |
|---|---|
| A forged "payment succeeded" callback | HMAC-SHA256 over `order_id\|payment_id`, compared with `hash_equals` |
| A valid signature for a payment that was never captured | The order is re-read from the Razorpay API and must say `captured` |
| Paying ₹1 for a ₹50,000 order | The captured amount must match the order total |
| The same payment counted twice | A unique index on `gateway_payment_id`; the payment row is updated, not added |
| A replayed webhook | The gateway event id is stored and must be new |
| A webhook with no secret configured | Refused outright — a blank secret never means "accept anything" |
| Tampering with the price between cart and payment | Totals are recomputed server-side at placement; nothing from the browser is trusted |

## Email (Zoho ZeptoMail)

Order confirmations go out through Zoho ZeptoMail's HTTP API. It is wired in as
a Laravel mail driver, not as a one-off call, so every Mailable in the app uses
it and nothing in the application code mentions Zoho:

| Piece | Where |
|---|---|
| The transport — builds Zoho's request, reads its errors | `app/Mail/Transport/ZeptoMailTransport.php` |
| Driver registration (`MAIL_MAILER=zeptomail`) | `app/Providers/MailServiceProvider.php` |
| Mailer definition | `config/mail.php` |
| Credentials | `.env` (keys documented in `.env.example`) |

```
MAIL_MAILER=zeptomail
MAIL_FROM_ADDRESS="support@example.com"
MAIL_FROM_NAME="Your Company"

ZOHO_MAIL_API_KEY=            # the Mail Agent's Send Mail token
ZOHO_API_URL=https://api.zeptomail.in/v1.1/email
```

**The API, not SMTP.** Shared hosting often blocks outbound SMTP ports, and an
HTTP call comes back with a response body, so a rejected message says *why* —
an unverified sender, a revoked token — instead of timing out.

**Check it without placing an order:**

```bash
php artisan mail:test you@example.com
```

It prints the mailer, the From address and the key's length and last characters
(never the key), then sends. On failure it reports Zoho's own reason and lists
the three things that usually cause it.

**The `.in` endpoint matters.** India-hosted ZeptoMail accounts answer on
`api.zeptomail.in`; everyone else uses `.com`. The wrong one returns 401, which
reads like a bad key.

**A mail failure never fails an order.** The order is placed and confirmed
first; if Zoho rejects the message, the error is logged and
`confirmation_sent_at` is rolled back so it can be retried. A payment that
happened stays happened.

## Razorpay configuration

The keys live in `.env` and nowhere else (`.env` is gitignored; `.env.example` carries placeholders only):

```
RAZORPAY_KEY=rzp_test_...
RAZORPAY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=
```

`RAZORPAY_WEBHOOK_SECRET` is **deliberately blank**, so webhooks are rejected until it is set. Before go-live: add the webhook in the Razorpay dashboard pointing at `https://<site>/webhooks/razorpay` for `payment.captured` and `payment.failed`, then put the secret it gives you into `.env`. The webhook route is registered under the `api` middleware, so it carries no session and no CSRF token.

Test keys are in use now. Swapping to live keys is a `.env` change and nothing else.

## My account

| URL | Page |
|---|---|
| `/account` | Overview — order, address and wishlist counts, three most recent orders |
| `/account/orders` | Order history, filterable by status |
| `/account/orders/{token}` | Order detail — items, totals, delivery address, payment |
| `/account/addresses` | Saved addresses — add, edit inline, make default, remove |
| `/account/profile` | Name, email, phone; separate password change |

Every page reads through `auth('customer')->user()->…`, so no route takes an id or token that could select somebody else's data. Another customer's order token returns 404, as does a guest order's.

## Checking on an order afterwards

A signed-in customer uses My Orders. A guest has no account, and the session that placed the order is gone as soon as they close the browser — so without this, their order would be unreachable for good.

| URL | Who | What |
|---|---|---|
| `/account/orders` | signed in | Every order, with status and payment |
| `/track-order` | anyone | Look an order up with its number and the email it was placed with |
| `/track-order/{token}` | anyone with the link | Status, timeline, items, totals, payment state |

The confirmation email carries the right link for each: account order detail for a customer, the tracking page for a guest. The success page says the same thing before the customer leaves it, since for a guest that is the one moment we have their attention.

**What the tracking page deliberately does not show.** A tracking link can be forwarded, or left behind in a shared browser's history, so the page shows only the city, state and pincode of the delivery address — never the street or the phone number. Those stay behind the account, or behind the session that placed the order.

**Guessing is closed off.** The lookup needs the order number *and* the matching email. A wrong email and an order number that does not exist give the identical message, so neither can be used to confirm the other, and six failed attempts per IP per minute stops a run at it. A successful lookup clears the counter.

## Seeded demo data

`ShopDemoSeeder` (part of `db:seed`) adds a customer, two addresses, three coupons and one delivered order, so the account area and the coupon screens are not empty on a fresh install.

- `customer@furnicraft.in` / `Furni@2026` — local only; outside local a password is generated and printed once. `SEED_CUSTOMER_PASSWORD` overrides either way.
- Coupons: `WELCOME500` (₹500 off above ₹5,000), `FESTIVE10` (10% up to ₹3,000, above ₹10,000), `FREEDELIVERY` (above ₹15,000).

## Notes

- **GST is extracted, not added.** Catalogue prices are inclusive, so tax is `subtotal − subtotal / (1 + rate/100)`. The totals a customer sees never change at the payment step.
- **Stock holds expire.** `php artisan stock:release-expired` frees holds older than 30 minutes and is scheduled every five minutes in `routes/console.php`. It only runs if the server has the `schedule:run` cron entry — see `DEPLOY.md`.
- **COD is zone-gated** and takes an advance on large orders, set in Website Settings.
- **Coupon usage is recorded per order** and released when an order is cancelled, so a cancelled order does not burn someone's one-per-customer coupon.
- **A used coupon cannot be deleted**, only deactivated — the usage rows are a record of real orders.
- **Admin order management is Phase 5.** The customer-facing order pages are complete; status changes, invoices, shipment tracking and cancellation from the admin side come next.

## Fixes made during this phase

- **The guest cart was lost on sign-in.** `Auth::attempt()` migrates the session the moment the credentials check out, so the code that adopted the guest's rows was reading the *new* session id while the rows were still keyed on the old one. The id is now captured before `attempt()` is called. `GuestSessionAdoptionTest` drives the controller directly — the test HTTP client starts a fresh session per request, so this can only be caught below the HTTP layer — and it fails if the capture moves back.
- **The confirmation email would not render.** `…this email@if ($supportPhone)` — Blade only recognises a directive at a word boundary, so the `@if` was left in the output while its `@endif` compiled, producing a PHP syntax error. Every confirmation email would have failed. The sentence is now built in PHP, and `BladeHygieneTest` scans every view for a directive glued to a word character and compiles each one through `php -l`. (The same mistake had already cost a bug in Phase 2; now it cannot reach production.)
- **Saved addresses had no edit screen.** The `PUT` route existed and was tested, but nothing in the UI reached it. Each card now has an inline edit form sharing one field partial with the add form; old input is replayed only into the form it came from, so a failed edit no longer refills the add form.
- **The checkout page collapsed through the footer.** `.fc-panel` carried `height: 100%`, which is right when a column holds one panel but wrong when it holds four, as checkout does: each panel grew to the full column height, so the page ran to 8,486 px and the last panels rendered over the footer. The height is gone; the three places where two panels sit side by side and should end level now say so with Bootstrap's `h-100`, as the order page already did. `BladeHygieneTest` asserts the rule is not reintroduced.
- **Checkout errors showed raw array keys** — "The shipping.city field is required." `attributes()` named only four of the nested fields; it now names every one of them in both address blocks, and a test fails if any message contains a raw key.
- **A saved address did not fill in its own pincode.** The prefill took the pincode from the cart's delivery check instead, so the street came from one place and the pincode from another. The saved address now wins for every field of the block.
- **`idempotency_key` was missing from `Order::$fillable`**, so it was silently dropped and the duplicate-order guard never matched.
- **The admin footer and dashboard roadmap still said "Phase 2".** Both now read `config/phases.php`, so moving the project on is one edit.
