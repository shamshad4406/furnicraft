# FurniCraft — Phase 3: Customer Storefront

Status: **complete**. Builds on [Phase 1](PHASE-1.md) and [Phase 2](PHASE-2.md).

## What was built

| Module | Where |
|---|---|
| Homepage — banner slider, featured categories, featured products, best sellers, new arrivals, offers, recently viewed, testimonials, newsletter, footer | `app/Http/Controllers/Front/HomeController.php`, `resources/views/front/home.blade.php` |
| Category & listing pages — product grid, pagination, sorting | `Front/CategoryController.php`, `Front/ProductController.php` |
| Filters — category, price range, material, colour, size, discount, availability | `app/Services/ProductBrowser.php`, `front/partials/filters.blade.php` |
| Search — name, SKU, slug, material and variant SKU, with live suggestions | `ProductController@suggest`, `resources/js/app.js` |
| Product detail page — gallery with hover zoom, variant selector with live price/stock, dimensions, warranty, delivery estimate, related and recently viewed | `front/product-show.blade.php` |
| Pincode check — serviceability and delivery days | `app/Services/DeliveryService.php`, `Front/DeliveryController.php` |
| CMS pages — About, Contact, FAQ, Shipping, Return, Warranty, Privacy, Terms, editable from admin | `Admin/CmsPageController.php`, `Front/PageController.php` |
| Admin — pincode and delivery-zone management | `Admin/PincodeController.php`, `Admin/DeliveryZoneController.php` |

Tables added: `pincodes`, `delivery_zones`, `cms_pages`, `recently_viewed`.

## Exit criteria

- [x] **All pages usable at 360 px** — verified with no horizontal scroll on the homepage, listing, categories, a category page, a product page, search results and three content pages. Filters collapse behind a button below 992 px; the product facts, actions and gallery reflow; sub-category cards go full width below 576 px.
- [x] **Filters and search return correct results** — 30 tests cover each filter in isolation, filters combined, filters on a category page, price filtering on the *effective* price, stock derived from variants, and suggestions matching name, SKU and category while skipping drafts.
- [ ] **Mobile Lighthouse above 80 on the homepage** — not measured. Lighthouse is not available in this environment; run it against the staging URL before sign-off. The groundwork is there: WebP images with `loading="lazy"`, one CSS and two JS files, no blocking third-party scripts beyond Google Fonts.

154 feature tests pass (105 before this phase, 49 new).

## Storefront map

| URL | Page |
|---|---|
| `/` | Homepage |
| `/categories` | All categories |
| `/categories/{slug}` | One category and its sub-categories |
| `/products` | All products; also search results (`?q=`) |
| `/product/{slug}` | Product detail |
| `/{slug}` | CMS page — declared last so it never shadows a named route |

Filter keys: `q`, `category`, `brand`, `material`, `color`, `size`, `min_price`, `max_price`, `discount`, `in_stock`, `best_seller`, `new_arrival`. Sorts: `featured`, `newest`, `price_low`, `price_high`, `name`.

## Notes

- **Pincode data is a starter set** — 3 zones and 21 pincodes. Replace it from Admin → Content & Delivery → Pincodes; there is a bulk box that takes `pincode, city, state` per line, and a CSV export.
- **Delivery charges are stored, not applied.** Zones carry base charge, per-kg charge and a free-above threshold; checkout uses them in Phase 4.
- **CMS content is Markdown.** `##` headings, `**bold**`, `-` lists. Raw HTML is stripped on render, so a pasted `<script>` cannot execute. The eight seeded pages are marked *system*: their content is fully editable but the slug is fixed and they cannot be deleted, because the footer and header link to them.
- **Testimonials are placeholder text** in `ShowcaseCatalog`. They become real approved reviews with the review module in Phase 8.
- **Ratings filter is not built** — it needs reviews, which are Phase 8. The filter sidebar says so.
- **Recently viewed** is keyed by session id, with `customer_id` filled once someone signs in so the list can follow them across devices in Phase 4. Capped at 8 per visitor.
- The per-product enquiry form is Phase 6; "Enquiry Now" carries the product slug to the contact page so it can be pre-filled then.

## Fixes made during this phase

- **`recently_viewed.viewed_at` needed sub-second precision.** With second precision, several products opened in the same second left "most recent first" undefined. The column is now `timestamp(3)` and the query breaks ties on `id`.
- **A non-serviceable pincode showed its message twice** — once generic, once as the staff note. The note now replaces the generic line.
