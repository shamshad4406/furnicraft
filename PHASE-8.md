# FurniCraft — Phase 8: Marketing & SEO

Status: **complete**. Builds on [Phase 7](PHASE-7.md).

Everything up to here was the shop working. This phase is the shop being found, and being believed once it is found. Three things, and one rule running through all of them: **nothing is claimed that is not true.** A rating with no reviews behind it, an "in stock" on something sold out, a mailing list holding somebody who never asked to be on it — each of those is the kind of thing that costs a shop its rich results, its sender reputation, or a customer.

## What was built

| Module | Where |
|---|---|
| Homepage banners, scheduled, reorderable, with click counts | `app/Models/Banner.php`, `Admin/BannerController.php`, `admin/banners/` |
| Customer reviews with moderation, verified purchase, public replies | `app/Services/ReviewService.php`, `Admin/ReviewController.php`, `Front/ReviewController.php` |
| Ratings rolled onto products, with a filter and a sort | `products.rating_average`, `rating_count`, `ProductBrowser` |
| Mailing list with double opt-in and one-click unsubscribe | `app/Services/NewsletterService.php`, `Front/NewsletterController.php` |
| List management and CSV export | `Admin/NewsletterController.php`, `admin/newsletter/` |
| `sitemap.xml` and `robots.txt`, generated from live data | `app/Services/SeoService.php`, `Front/SeoController.php` |
| Schema.org JSON-LD for the shop, products and breadcrumbs | `SeoService@organisation`, `@product`, `@breadcrumbs` |
| Canonical URLs, Open Graph, per-page robots | `front/layouts/app.blade.php` |
| Google Analytics, without an inline script | `resources/js/analytics.js`, `SecurityHeaders` |
| Marketing settings tab | `admin/settings/tabs/marketing.blade.php` |

Tables added: `banners`, `reviews`, `newsletter_subscribers`. Columns added to `products`: `rating_average`, `rating_count`.

## Exit criteria

- [x] **No review reaches a product page until somebody has read it.** Including a review somebody edits after it was published.
- [x] **The rating shown is the average of exactly what is published** — never of what is waiting, rejected, or deleted.
- [x] **A banner stops on its own.** Nobody has to remember that the sale ended on Sunday.
- [x] **No banner can point off this site**, and the click counter will not redirect anywhere the banner does not already say.
- [x] **Nobody joins the mailing list without confirming**, and leaving takes one click and no sign-in.
- [x] **Nothing private is in the sitemap or indexable** — accounts, checkout, carts, and anything addressed by a one-off token.
- [x] **No structured-data claim without the fact behind it.** `AggregateRating` only with published reviews; `availability` from the actual stock.
- [x] **No inline script**, so the content-security policy stays as strict as it was.
- [x] **Everything is tested** — 671 feature tests pass (601 before this phase, 70 new).

## Reviews

A review arrives `pending` and stays there. The admin screen opens on the queue, because a moderation screen nobody opens is the same as no moderation at all.

```
pending ──approve──→ approved ──┬── featured on the homepage
   │                            └── replied to in public
   ├──reject───→ rejected
   └──spam─────→ spam
```

| Decision | What happens |
|---|---|
| Publish | Goes on the product page; the product's average is recalculated |
| Do not publish | Stays off, with an internal note saying why |
| Spam | Same, and the note says so |
| Show on the homepage | Only possible once published; dropped automatically if it is later rejected |
| Reply | The shop answers underneath, in public |

**Verified purchase is derived, never ticked.** The badge appears when the reviewer's email has a **delivered** order containing that product — delivered, not merely placed, because somebody who ordered yesterday has not seen the thing yet.

**A second review of the same product by the same person is an edit of one opinion, not a second one.** It replaces the first and goes back into the queue: the old approval was for different words.

**The surname is shortened to an initial** — "Ravi Kumar Sharma" shows as "Ravi S.". A review is public, and leaving one is not asking to be searchable by full name. The email address never reaches the page at all.

**`reviews_require_purchase`** decides who may write one. Off (the default) anybody may, and the verified badge does the honest work of telling them apart. On, only a delivered order gets you a form. Either way nothing is published unread.

### The rating on the product

`rating_count` and `rating_average` live on the product so a listing can sort and filter on them without counting reviews per row. They are **written by query**, not through the model — a caller holding a product loaded before the review changed would otherwise find the new figures matching its own stale attributes, nothing dirty, and `save()` quietly writing nothing. A rating on a product that no published review supports is exactly the sort of claim this phase exists to prevent, so the write does not depend on who is holding the model.

## Banners

One table serves the hero slider and the smaller promotional blocks, keyed by `placement`. The homepage uses admin banners when there are any and falls back to the built-in slides when there are none, so an empty table is not an empty page.

| State | Why it is not showing |
|---|---|
| Live | It is showing |
| Scheduled | `starts_at` has not arrived |
| Finished | `ends_at` has passed |
| Off | Somebody switched it off |

Scheduling is applied **in the query**, so a banner whose run has ended simply stops appearing. It stays switched on; nothing had to be remembered.

**The link has to be a path on this site.** `cta_url` is validated against `^/[^/\\]` — not `https://`, not `//evil.example`, not `javascript:`. An open redirect on a shop is a phishing link wearing the shop's name, and the click counter takes its destination from the stored banner rather than from the request for the same reason.

**Order is nudged, not typed.** Two numbered fields that have to agree is how a slider ends up with three banners all at position 2, so the list offers up and down, swaps with the neighbour, and nudges them apart if the swap leaves them equal.

## The mailing list

Double opt-in by default. The footer box is open to the world, and somebody typing a stranger's address into it should not sign that stranger up.

```
form ──→ pending ──(link in email)──→ subscribed ──(one click)──→ unsubscribed
             │                                                         │
             └── not mailable                          re-subscribing: only by
                                                       filling the form in again
```

- Addresses are stored lower-case, so the same person cannot end up on the list twice.
- The confirmation token is **spent on use**, so the link cannot be replayed.
- Unsubscribe is addressed by a 40-character token, never the row id, and needs no sign-in. Anything else is a dark pattern that produces spam complaints instead of unsubscribes.
- A failed confirmation email does not lose the sign-up: they are recorded as pending and can ask again.
- **The export leaves unsubscribes out** by default, and every row carries that person's unsubscribe URL, so whatever sends the mail can offer one-click unsubscribe.

FurniCraft keeps the list; it does not send the campaigns. That is deliberate — it is a furniture shop, not a mail platform, and the CSV is what a real platform takes.

## SEO

`sitemap.xml` and `robots.txt` are generated per request rather than written to disk: a sitemap built on request cannot go stale, and this catalogue is small enough that building it costs nothing worth caching around.

The sitemap carries active products, active categories, active CMS pages and the four public landing pages. Drafts and switched-off rows are left out — a sitemap full of 404s is worse than none.

`robots.txt` disallows `/admin`, `/account`, `/checkout`, `/cart`, `/wishlist`, `/track-order`, `/quotation`, `/enquiry/sent`, `/newsletter` and `*?q=`. **With `seo_allow_indexing` off it disallows everything**, and every page says `noindex, nofollow` for itself in case a crawler ignores the file. A staging site that gets indexed is worse than one nobody finds.

### Structured data

| Schema | Where |
|---|---|
| `FurnitureStore` — name, address, phone, social profiles | Homepage |
| `Product` — price, availability, brand, material, colour | Each product page |
| `AggregateRating` | Only when `rating_count > 0` |
| `BreadcrumbList` | Listings and product pages |

Every page carries a canonical URL **without the query string**, so a filtered listing and a sorted one do not compete with each other.

### Analytics

`google_analytics_id` is blank by default and nothing loads. Set it and two things happen together: the tag appears, and the content-security policy opens for Google's hosts — **only then**. The measurement ID rides on the script tag as `data-ga-id` and `analytics.js` reads it from `document.currentScript`, because the policy allows no inline script and the usual gtag snippet is one.

The ID is validated against Google's own format (`G-XXXXXXXXXX` or `UA-…`), so a mistyped one is caught at the settings screen rather than by weeks of missing data.

## Settings

The Marketing & SEO tab, all of which something reads:

| Setting | Default | Effect |
|---|---|---|
| `seo_allow_indexing` | on | Off: `noindex` everywhere and `Disallow: /` |
| `google_analytics_id` | blank | Blank: no script, no CSP allowance |
| `google_site_verification` | blank | Adds the Search Console meta tag |
| `reviews_enabled` | on | Off hides the review form; published reviews stay |
| `reviews_require_purchase` | off | On: only delivered orders may review |
| `newsletter_enabled` | on | Off removes the footer form; the list is kept |
| `newsletter_double_optin` | on | Off subscribes immediately — leave it on |
| `newsletter_intro` | set | One line above the footer form |

## Demo data

```bash
php artisan db:seed --class=MarketingDemoSeeder
```

Three banners (one running, one finished, one scheduled), three reviews (one featured with a reply, one published, one waiting) and four subscribers across every status — enough that each screen shows all its states at once.

## Still open

- **Review images.** Customers cannot attach a photo of the thing in their room, which is the most persuasive review there is.
- **Campaign sending.** The list exports; nothing here sends. By design, but it does mean a second tool.
- **`money()` groups in thousands, not lakh** — `₹ 179,499` should read `₹ 1,79,499`. Carried from Phase 7.
- **Catalogue edits and CSV import still bypass the stock ledger.** Carried from Phase 7.
- **Mobile Lighthouse** has never been measurable in this environment; the layout is checked at phone width by hand.

Next: [Phase 9](PHASE-9.md) — testing and launch.
