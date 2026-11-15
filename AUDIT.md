# FurniCraft — Pre-audit review (Phases 1–3)

Run on 2026-11-14 against the live dev database. **214 tests pass.**

## 1. Database mapping — no gaps

All 20 tables the plan names for Phases 1–3 exist, InnoDB, `utf8mb4_unicode_ci`:

| Phase | Tables | Status |
|---|---|---|
| 1 | `users`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions`, `settings`, `activity_logs`, `login_histories`, `jobs`, `failed_jobs` | all present |
| 2 | `categories`, `brands`, `products`, `product_images`, `product_variants`, `product_specifications` | all present |
| 3 | `pincodes`, `delivery_zones`, `cms_pages`, `recently_viewed` | all present |

Beyond the plan: `customers` + `customer_password_reset_tokens` (needed for the separate customer guard the Phase 1 spec requires), plus Laravel's own `migrations`, `sessions`, `cache`, `cache_locks`, `job_batches`, `password_reset_tokens`, `model_has_permissions`.

**Foreign keys** — 13, all with deliberate delete rules: product children `CASCADE`; `products.category_id` `RESTRICT` (a category holding products cannot be deleted); `products.brand_id`, `categories.parent_id`, `pincodes.delivery_zone_id` `SET NULL`.

**Soft deletes** on `users`, `customers`, `categories`, `brands`, `products`, `cms_pages` — matching the plan.

**Added this pass:** `deleted_at` indexes on all six soft-deleting tables (every query adds `deleted_at is null`), and a `(session_id, viewed_at)` composite on `recently_viewed` to match how the list is read.

## 2. Functionality — verified

Storefront, admin and filters smoke-tested over HTTP; every route 200s, including empty-filter and combined-filter query strings. 214 feature tests cover auth and roles, catalog CRUD, bulk import, listings, filters, search, product detail, pincode check and CMS pages.

## 3. Form validation — complete coverage

Every write endpoint validates. 37 new tests assert the rules actually bite:

- **Required-field sweep** across all 8 admin create forms — empty submissions rejected.
- **Products** — price numeric and non-negative, GST capped at 28, status from a fixed list, category must exist, SKU character set, sale price below price, max 10 images.
- **Staff** — password length + letters + digits, confirmation match, role must exist on the admin guard.
- **Settings** — email format, 3-letter currency, real timezone, order prefix charset, unknown settings group 404s.
- **Delivery** — 6-digit pincode not starting with zero, duplicates rejected, bulk box reports bad lines by number, no negative charges.
- **Customer signup** — all fields, duplicate email, phone format.
- **Storefront GET filters** — newly validated: unknown `sort`, non-numeric price, over-long search term now rejected instead of reaching the query.

## 4. Security — findings and fixes

### Fixed

| Severity | Finding | Fix |
|---|---|---|
| **High** | Brand logo, company logo and favicon accepted **SVG**. An SVG served from our own origin can carry `<script>` — stored XSS. (It also crashed with a 500, because GD cannot decode SVG.) | SVG and ICO removed from all upload rules. Uploads are decoded with `getimagesize()` and the real MIME checked against JPEG/PNG/WebP, so a renamed file is rejected on content, not extension. |
| **High** | SMTP password stored in clear text in `settings` and echoed back into the form's HTML. | Encrypted at rest with `Crypt`; the form never renders it and a blank submit keeps the stored value. |
| **Medium** | No security response headers. | `SecurityHeaders` middleware: CSP (`script-src 'self'`, `frame-ancestors 'none'`, `object-src 'none'`), `nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy`, HSTS over HTTPS, and `no-store` on signed-in admin pages. |
| **Medium** | A bad upload threw an unhandled exception → 500 page. | `InvalidImageException` renders as a field error. Pixel count capped at 50 MP against decompression bombs. |
| **Low** | Offers ordering divided by `price`, which can be 0. | `NULLIF(price, 0)` guard. |
| **Low** | One inline `onchange` handler would have been silently broken by the CSP. | Moved into `app.js`; a test now fails if any view reintroduces an inline handler. |

### Verified clean

- **SQL injection** — all dynamic SQL is parameterised; `whereRaw` uses bindings, `orderByRaw` uses fixed strings. Payloads in filters and search are treated as text (tested).
- **XSS** — only four `{!! !!}` in the codebase: Markdown with raw HTML *stripped*, and `nl2br(e(...))`. Script tags in product names and search terms are escaped (tested).
- **CSRF** — Laravel's token on every state-changing form.
- **Authorization** — every admin controller carries `permission:` middleware; the dashboard is gated on its route. Role separation tested: Sales/Delivery get 403 on staff, settings and catalog writes.
- **Guard separation** — a customer session cannot reach any admin route (tested across five routes); an admin session does not authenticate the storefront.
- **Rate limiting** — admin login throttled per email+IP with lockout, password reset 6/min, customer login 10/min, pincode check 30/min, search suggest 60/min (the last two tested by driving them to 429).
- **Mass assignment** — `is_system` is unguarded-safe: it cannot be set through a form, and system CMS pages cannot be deleted or have their slug changed (tested).
- **No debug leftovers** — no `dd`, `dump`, `var_dump` or `console.log` in shipped code.

### Closed in the follow-up pass

| Finding | Fix |
|---|---|
| Uploads directory had no execution guard, and the demo artwork put 42 SVGs inside it. | `storage/app/public/.htaccess` (now kept by git) blocks interpreters, forces `nosniff`, and makes SVG/XML download rather than render. Separately, bundled demo artwork moved **out** of the uploads folder: it is referenced as `bundled/...` and served from `public/images`, so **the uploads directory now contains nothing but its two protection files**. The Nginx equivalent is in `DEPLOY.md`. |
| Seeder used a fixed, published password on every environment. | Outside `local`/`testing` the seeder generates a 20-character password, prints it once and tells the operator to change it. `SEED_ADMIN_PASSWORD` still overrides. |
| No production configuration guidance. | `.env.example` carries a commented hardening block; `DEPLOY.md` is a full checklist. |

### Performance, measured rather than guessed

Lighthouse is unavailable here, so the homepage was measured directly at
360 px with the Resource Timing API:

| | Before | After |
|---|---|---|
| Page weight | 553 KB | **477 KB** |
| Icon font fetch | 188 ms (serialised after CSS) | **19 ms** (preloaded, parallel) |
| Requests | 7 | 7 |

The stylesheet was shipping **2050 icon classes for the 99 the project uses**.
`build/generate-icons.mjs` now emits only the icons found in Blade, JS and PHP,
cutting ~76 KB from each stylesheet; `npm run build` regenerates it, and a new
icon is picked up automatically. The font is preloaded with the exact URL the
CSS requests, so it is fetched once and early.

Remaining weight is Bootstrap itself (~230 KB uncompressed, ~30 KB gzipped).
`artisan serve` does not compress; Apache/Nginx will, so the real figure over
the wire is far lower. That is why Lighthouse must still be run on staging.

## 5. Before go-live — not code, deployment

Full steps in **[DEPLOY.md](DEPLOY.md)**. The short version:

1. **`APP_DEBUG=false`** and `APP_ENV=production`. Settings → System flags this in red while debug is on.
2. **Change the seeded passwords.** On this local install all five demo accounts are still `Furni@2026`; a fresh non-local seed now generates a strong one instead.
3. **Set `SESSION_SECURE_COOKIE=true`** once TLS is on. `http_only` is already true and `same_site` is `lax`.
4. **Document root must be `public/`.** Anything else exposes `.env`, `storage/` and `vendor/`.
5. **On Nginx, add the uploads rules** from `DEPLOY.md` — the shipped `.htaccess` is Apache-only.
6. **Replace the bundled demo artwork** with real photographs. It is no longer a security point (nothing demo-related sits in the uploads folder), purely a content one.
7. **Mobile Lighthouse** — run it against staging. The page-weight and request work is done; what cannot be checked here is real-world compression and field metrics.
8. Rotate `APP_KEY` only with care: the SMTP password is encrypted with it and would need re-entering.

---

# Phase 4 addendum — Cart, Checkout & Payments

The review above covers Phases 1–3. Phase 4 adds money, so it was reviewed on
the same terms. Full detail in **[PHASE-4.md](PHASE-4.md)**.

## Payment integrity

An order reaches `paid` only through `OrderService::markPaid()`, and only after
three independent checks have passed, in this order:

1. **Signature** — HMAC-SHA256 over `order_id|payment_id` with the API secret,
   compared using `hash_equals`, so a wrong signature cannot be timed out.
2. **Re-confirmation** — the payment is read back from the Razorpay API and must
   report `captured`. A valid signature for an uncaptured payment is refused.
3. **Amount** — the captured amount must match the order total.

Nothing from the browser contributes to the amount. Totals are recomputed
server-side when the order is placed and never read back from the request.

Replay is closed on both paths: `payments.gateway_payment_id` is unique, so a
repeated callback updates one row instead of adding money twice, and the
gateway's event id is stored in `payment_webhooks` and must be new.

A blank `RAZORPAY_WEBHOOK_SECRET` **rejects** webhooks rather than accepting
unsigned ones. It is blank today, by design, until the secret is created in the
Razorpay dashboard — see "Before go-live" below.

## Ownership and scoping

Every customer-facing record is reached through the signed-in customer, not by
id from the URL:

| Route | Scoped by |
|---|---|
| `/account/*` | `auth('customer')->user()->…` for orders, addresses, wishlist |
| `/account/orders/{token}` | the customer's own orders only — another customer's token is 404, as is a guest order's |
| `/checkout/{token}/*`, `/payment/{token}/*` | the session that placed the order, or the signed-in owner |
| `account/addresses/{address}` | `abort_unless($address->customer_id === auth('customer')->id(), 404)` on update, delete and make-default |

Orders are addressed by a 32-character `public_token`, never by order number or
id, so they cannot be enumerated.

## Validation

Every new form validates server-side, and the rules are tested, not assumed:

- **Addresses** — type against a whitelist, pincode `^[1-9][0-9]{5}$`, phone
  against a digit/format pattern, lengths on every string.
- **Profile** — email and phone unique across customers, ignoring the current
  record and soft-deleted rows.
- **Password change** — the current password must be supplied and must match;
  the new one needs 8+ characters with letters and numbers.
- **Coupons** — a percentage cannot exceed 100 while a fixed amount can, the
  code is restricted to `[A-Za-z0-9_-]`, and an expiry must fall after its start.

## Abuse and concurrency

- **Oversell** — stock rows are locked for update while a hold is taken; a hold
  that cannot be met rolls the whole order back, so no order survives it.
- **Double submission** — an idempotency key per checkout attempt, re-checked
  inside the transaction, so a refresh or double-click returns the same order.
- **Duplicate confirmation emails** — the send is claimed by writing
  `confirmation_sent_at` before the mail goes out; a failure rolls the claim
  back so it can be retried rather than silently never sending.
- **Coupon over-redemption** — usage is recorded per order and released when an
  order is cancelled, so a cancellation does not burn a one-per-customer coupon.

## Found and fixed during this phase

- **The guest cart was lost on sign-in.** `Auth::attempt()` migrates the session
  as soon as the credentials check out, so the adoption code read the new
  session id while the guest's rows were still keyed on the old one. Caught in
  the browser, not by tests — the test HTTP client starts a fresh session per
  request, so it could not reproduce it. Now captured before `attempt()`, with
  `GuestSessionAdoptionTest` driving the controller directly to hold it there.
- **The confirmation email could not render.** `…this email@if ($supportPhone)`
  — Blade only recognises a directive at a word boundary, so the `@if` stayed in
  the output while its `@endif` compiled, giving a PHP syntax error. Every
  confirmation email would have failed. `BladeHygieneTest` now scans every view
  for a glued directive and compiles each one through `php -l`.
- **Saved addresses had no edit UI**, though the route existed and was tested.
- **`idempotency_key` was missing from `Order::$fillable`**, so it was dropped
  silently and the duplicate-order guard never matched.

## Before go-live — additions to the list above

9. **Create the Razorpay webhook** at `https://<site>/webhooks/razorpay` for
   `payment.captured` and `payment.failed`, and put its secret into
   `RAZORPAY_WEBHOOK_SECRET`. Until then webhooks are refused, and payments
   settle only on the browser callback — which is enough to take money, but
   leaves a payment unrecorded if the customer closes the tab at the wrong
   moment.
10. **Swap the Razorpay test keys for live keys** in `.env`. Nothing else
    changes; the key is never hardcoded or exposed to the client beyond the
    public key Razorpay's own checkout requires.
11. **Add the cron entry** that drives the scheduler:
    `* * * * * cd /path/to/FurniCraft && php artisan schedule:run >> /dev/null 2>&1`.
    The sweeper itself is scheduled in `routes/console.php` (`stock:release-expired`,
    every five minutes), but with no cron nothing runs it and abandoned
    checkouts keep their stock reserved.
12. **Change the seeded customer password** (`customer@furnicraft.in`) along
    with the staff ones.
