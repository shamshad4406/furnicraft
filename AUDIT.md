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

## 5. Before go-live — not code, deployment

1. **`APP_DEBUG=false`** and `APP_ENV=production`. Settings → System flags this in red while debug is on.
2. **Change the seeded passwords.** All five demo accounts are `Furni@2026`. The demo-account panel on the login screen only renders when `APP_ENV=local`, but the accounts are real.
3. **Set `SESSION_SECURE_COOKIE=true`** once TLS is on. `http_only` is already true and `same_site` is `lax`.
4. **Document root must be `public/`.** Anything else exposes `.env`, `storage/` and `vendor/`.
5. **Replace the demo artwork.** The seeded category/product images are SVG files written directly to `storage/app/public` by the demo seeder — our own files, no script in them, but an auditor will notice SVGs in an uploads folder. Uploads can no longer produce SVG. Replacing them with real photos clears this entirely.
6. **Mobile Lighthouse** was never measured — not available in this environment. Run it against staging; the Phase 3 exit criterion asks for 80+.
7. Rotate `APP_KEY` only with care: the SMTP password is encrypted with it and would need re-entering.
