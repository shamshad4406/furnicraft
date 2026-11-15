# FurniCraft — Furniture E-Commerce Platform

FurniCraft is a full-featured furniture e-commerce web application: a storefront where customers explore products, pick variants, save wishlists, fill a cart and pay online or on delivery, and an admin panel that runs the business behind it.

Built with **Laravel 12, PHP 8.2+, MySQL/MariaDB, Bootstrap 5 and Razorpay**, in nine phases.

## Where the project stands

| Phase | Scope | Status |
|---|---|---|
| 1 | [Project Setup & Foundation](PHASE-1.md) — auth, roles, staff, settings, activity log | complete |
| 2 | [Catalog Management](PHASE-2.md) — categories, brands, products, variants, images, import/export | complete |
| 3 | [Customer Storefront](PHASE-3.md) — homepage, listings, filters, search, product pages, CMS | complete |
| 4 | [Cart, Checkout & Payments](PHASE-4.md) — cart, wishlist, coupons, checkout, Razorpay, COD, my account | complete |
| 5 | [Orders & Delivery](PHASE-5.md) — admin order processing, status workflow, GST invoices, shipments, returns | complete |
| 6 | [Enquiry & Quotation](PHASE-6.md) — enquiries, replies, quotations, accept online, convert to order | complete |
| 7 | [Inventory & Reports](PHASE-7.md) — stock ledger, suppliers, purchase orders, customers, reports | complete |
| 8 | [Marketing & SEO](PHASE-8.md) — banners, reviews, sitemap, robots, structured data, newsletter | complete |
| 9 | Testing & Launch | next |

`config/phases.php` is the single source for this; the admin dashboard and footer read from it.

Also worth reading: **[AUDIT.md](AUDIT.md)** (security and validation review) and **[DEPLOY.md](DEPLOY.md)** (going live).

## Running it locally

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

The storefront is at `/`, the admin panel at `/admin`.

Seeded sign-ins (local only — outside local a password is generated and printed once):

| Who | Email | Password |
|---|---|---|
| Admin | `admin@furnicraft.in` | `Furni@2026` |
| Manager / Sales / Inventory / Delivery | `<role>@furnicraft.in` | `Furni@2026` |
| Customer | `customer@furnicraft.in` | `Furni@2026` |

Override with `SEED_ADMIN_PASSWORD` / `SEED_CUSTOMER_PASSWORD`. **Change them before the site is reachable from anywhere but your machine.**

## Build pipeline

There is no Vite here. Rollup's native binary is blocked by Application Control on the development machine, so assets are built with the pure-JS Sass CLI plus three small Node scripts:

```bash
npm run build     # vendor + icons + js + css
npm run dev       # the same, watching
```

- `build/copy-vendor.mjs` — Bootstrap, Bootstrap Icons fonts
- `build/generate-icons.mjs` — scans the views and emits only the icons actually used (155 of 2,050)
- `build/copy-js.mjs` — app scripts

Output lands in `public/assets/` and is linked with `asset_v()`, which appends the file's mtime so a changed file is never served from cache.

## Tests

```bash
php artisan test
```

671 feature tests. They run against `furnicraft_test`, configured in `phpunit.xml`; create it once with `CREATE DATABASE furnicraft_test;`.

## Project objective

Digitise furniture business operations — product management, online sales, payments, inventory, customer enquiries, order processing and reporting — in one centralised platform, on an architecture that can grow with the business and works on desktop, tablet and mobile.
