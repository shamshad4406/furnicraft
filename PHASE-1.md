# FurniCraft — Phase 1: Project Setup & Foundation

Status: **complete**. Stack per the development plan: Laravel 12, PHP 8.2, MySQL/MariaDB,
Bootstrap 5, Spatie permission + activitylog.

## What was built

| Module | Where |
|---|---|
| Project structure (Front / Admin / Services) | `app/Http/Controllers/{Admin,Front}`, `app/Services` |
| Database base (utf8mb4, soft deletes, seeders) | `database/migrations`, `database/seeders` |
| Separate admin + customer guards | `config/auth.php`, `app/Models/{User,Customer}.php` |
| Login, logout, forgot/reset password, throttling | `app/Http/Controllers/Admin/Auth`, `app/Http/Controllers/Front/Auth` |
| Roles & permissions (5 roles, 72 permissions) | `database/seeders/RolePermissionSeeder.php` |
| Bootstrap 5 admin layout (sidebar, header, breadcrumbs, alerts, table + form components) | `resources/views/admin/layouts`, `resources/views/components/admin` |
| Website settings (7 tabs incl. company, GST, SMTP, currency, order numbering) | `app/Http/Controllers/Admin/SettingController.php` |
| Activity log + login history | `app/Http/Controllers/Admin/ActivityLogController.php` |
| Staff user management (add/edit/disable/remove, assign role) | `app/Http/Controllers/Admin/UserController.php` |
| Storefront shell matching the approved design | `resources/views/front` |

Tables: `users`, `customers`, `roles`, `permissions`, `model_has_roles`, `role_has_permissions`,
`settings`, `activity_logs`, `login_histories`, `jobs`, `failed_jobs`, `sessions`, `cache`.

## Exit criteria

- [x] Each role sees only its allowed menu — the sidebar and the dashboard tiles are both permission-gated; direct URLs return a branded 403.
- [x] Password reset email works — branded mail linking to `/admin/reset-password/...` (admin) or `/reset-password/...` (customer).
- [x] Seeders create the default admin and roles.
- [x] Settings page saves data (cached payload is flushed on write).
- [x] 39 feature tests pass.

## Running it

```bash
php artisan serve
```

Storefront `/` · Admin panel `/admin/login`

Rebuild CSS/JS after changing `resources/scss` or `resources/js`:

```bash
npm run build
```

`npm run dev` watches the SCSS.

> **Build note:** this machine's Application Control policy blocks Rollup's native binary, so
> Vite was replaced with the pure-JS Sass CLI plus a vendor copy step (`build/*.mjs`).
> Output lands in `public/assets/` and is linked with `asset_v()` (mtime cache-busting).

## Seeded staff accounts

| Email | Role |
|---|---|
| admin@furnicraft.in | Admin |
| manager@furnicraft.in | Manager |
| sales@furnicraft.in | Sales |
| inventory@furnicraft.in | Inventory |
| delivery@furnicraft.in | Delivery |

Password for all: `Furni@2026` — **change these before staging.** Override at seed time with
`SEED_ADMIN_PASSWORD` in `.env`. The demo-account panel on the login screen only renders when
`APP_ENV=local`.

## Notes for the next phases

- `app/Services/ShowcaseCatalog.php` holds the placeholder categories/products that drive the
  homepage. Phase 2 creates the real tables; Phase 3 swaps this class for database queries —
  the Blade views already read the same keys, so the markup does not change.
- Nav links for Products / Categories / About / Contact render a "arriving in Phase N" page
  (`app/Http/Controllers/Front/PageController.php`) so the navigation is complete today.
- Permissions for every later-phase module (catalog, orders, inventory, …) are already seeded,
  so roles will not need re-editing when those modules land. Their sidebar entries show disabled
  with a phase badge.
- The `customers` table is minimal on purpose — Phase 4 adds addresses, wishlist, cart and OTP
  verification on top of it.
- SMTP settings are stored but not yet wired into the mailer; Laravel still reads `MAIL_*`
  from `.env`. Switch this over during Phase 9 deployment.
- `APP_DEBUG=true` locally — must be off before go-live (flagged on Settings → System).
