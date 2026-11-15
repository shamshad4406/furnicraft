# FurniCraft — deployment checklist

Everything here is configuration, not code. The application ships secure by
default; these are the settings only the hosting environment can supply.

## 1. Document root

Point the virtual host at **`public/`**, never at the project root. Anything
else serves `.env`, `storage/` and `vendor/` over HTTP.

## 2. Environment

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.in
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
```

`APP_DEBUG=true` on a live site prints stack traces, environment variables and
database credentials on any error. Admin → Settings → System shows the current
value in red while it is on.

Generate a key if this is a fresh install: `php artisan key:generate`.

**Do not rotate `APP_KEY` afterwards** without re-entering the SMTP password —
it is encrypted with that key.

## 3. Build and cache

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --force          # first deploy only
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The seeder prints a generated staff password **once** on non-local
environments. Write it down, then change it after the first sign-in.

## 4. Uploads directory

`storage/app/public/.htaccess` ships with the project and blocks script
execution, forces `nosniff`, and makes SVG/XML download rather than render.
It is Apache-only. **On Nginx add the equivalent:**

```nginx
location ^~ /storage/ {
    add_header X-Content-Type-Options "nosniff" always;
    location ~* \.(php|phtml|phar|cgi|pl|py|sh)$ { deny all; }
    location ~* \.(svg|svgz|xml|html?)$ {
        add_header Content-Disposition "attachment" always;
        add_header Content-Security-Policy "default-src 'none'; sandbox" always;
    }
}
```

The application only ever writes WebP, so this is defence in depth rather than
the primary control.

## 5. Permissions

`storage/` and `bootstrap/cache/` must be writable by the web user; nothing
else should be.

## 6. Scheduler and queue

`QUEUE_CONNECTION=database` is already set. Run a worker under Supervisor and
add the scheduler to cron:

```
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

This is not optional once orders are live. `stock:release-expired` runs every
five minutes and frees the stock held for checkouts that were never paid for;
with no cron entry, an abandoned checkout keeps its stock reserved and the
items look out of stock to everyone else. Check it with `php artisan
schedule:list`.

## 7. Mail

Mail is configured in `.env`, not in the admin panel. Set `MAIL_MAILER` to the
provider you are using.

**Zoho ZeptoMail** (what this install uses) sends over its HTTP API:

```
MAIL_MAILER=zeptomail
MAIL_FROM_ADDRESS="support@yourdomain.com"
MAIL_FROM_NAME="Your Company"

ZOHO_MAIL_API_KEY=
ZOHO_API_URL=https://api.zeptomail.in/v1.1/email
ZOHO_MAIL_TIMEOUT=15
```

1. In the ZeptoMail console, add and verify your sending domain, then create a
   Mail Agent and copy its **Send Mail token** into `ZOHO_MAIL_API_KEY`. The
   token already starts with `Zoho-enczapikey`; paste it whole.
2. `MAIL_FROM_ADDRESS` must be an address on the verified domain. Zoho rejects
   anything else, and the error reads like a configuration fault rather than a
   sender fault, so check this first when nothing arrives.
3. `ZOHO_API_URL` is `api.zeptomail.in` for India-hosted accounts and
   `api.zeptomail.com` for the rest. **The wrong one answers 401**, which looks
   exactly like a bad key.
4. Verify before you go near a real customer:

```bash
php artisan mail:test you@yourdomain.com
```

`MAIL_MAILER=log` writes messages to `storage/logs` instead of sending them —
the right setting on a development machine, where test orders would otherwise
send real email to made-up addresses and dent your sender reputation.

> **Admin → Settings → SMTP** holds host, port, username and an encrypted
> password. Those fields are not applied at runtime; they are there for a
> future SMTP mailer. The `.env` values are what actually send mail today.

**Worth doing once there is a queue worker:** confirmations are sent inline, so
checkout waits on Zoho's API. Making `OrderConfirmation` queueable moves that
off the request — but only do it once a worker is genuinely running under
Supervisor, or the emails will queue and never leave.

## 8. Payments (Razorpay)

Keys live in `.env` and nowhere else. `.env` is gitignored; `.env.example`
carries placeholders only.

```
RAZORPAY_KEY=rzp_live_...
RAZORPAY_SECRET=...
RAZORPAY_WEBHOOK_SECRET=...
```

1. **Swap the test keys for live keys.** Nothing in the code changes — the
   secret is never hardcoded, and only the public key reaches the browser,
   because Razorpay's own checkout needs it.
2. **Create the webhook** in the Razorpay dashboard, pointing at
   `https://<site>/webhooks/razorpay`, subscribed to `payment.captured` and
   `payment.failed`.
3. **Put the webhook secret into `RAZORPAY_WEBHOOK_SECRET`.** While it is
   blank, webhooks are *rejected* — an unsigned webhook is never trusted. Until
   it is set, payments settle only on the browser callback, which works, but
   leaves a payment unrecorded if the customer closes the tab at the wrong
   moment.
4. **Place one live order for a small amount and refund it**, to confirm the
   whole path end to end on the production domain.

The webhook route sits under the `api` middleware group, so it has no session
and no CSRF token. It must not be moved under `web`.

## 9. Stock

Run this **once**, after migrating, before anybody touches the inventory screens:

```bash
php artisan inventory:open-balances
```

It writes an opening balance for stock that was on the shelf before the ledger
existed. Without it the ledger starts mid-story — the catalogue says forty of
something and the ledger explains none of it. It is idempotent, so a second run
does nothing, and `--dry-run` shows what it would write first.

Afterwards, fill in **cost prices**. A product without one counts as zero in the
stock valuation report. They fill themselves in as deliveries are received, so
this only matters for stock that was already there.

## 10. Search engines

`robots.txt` and `sitemap.xml` are **generated by the app**, not files on disk.
A file of either name in `public/` is served by the web server before the route
ever runs, which is how a correct, tested robots.txt ends up absent from the
live site. Laravel ships a stock `public/robots.txt`; it has been removed here,
and nothing should put one back. A test asserts neither file exists.

Check both on the real domain after going live:

```bash
curl -s https://example.com/robots.txt
curl -s https://example.com/sitemap.xml | head -20
```

**`seo_allow_indexing` is the switch that matters.** Leave it off on staging —
every page then says `noindex, nofollow` and robots.txt disallows everything.
Turn it on in Settings → Marketing & SEO on the day the shop goes live, not
before: a half-built shop that outranks the real one is hard to undo.

Analytics and Search Console are the same screen. The measurement ID is blank
by default and **nothing is loaded and no Google host is allowed by the content
security policy until it is filled in.**

## 11. Before sign-off

- [ ] Change the seeded staff passwords **and the seeded customer password**
      (`customer@furnicraft.in`); delete any demo accounts not needed.
- [ ] Remove the seeded demo order and demo coupons if the client does not want
      them (`ShopDemoSeeder` creates them; it is skipped on re-seed if the
      customer already has orders).
- [ ] Replace the bundled demo artwork with real product photographs. Uploads
      go through the optimiser and are stored as WebP.
- [ ] Load the real pincode list (Admin → Content & Delivery → Pincodes has a
      bulk paste box and a CSV export).
- [ ] Review the eight content pages — they ship with sensible defaults but the
      company details, policies and timelines must be the client's own.
- [ ] Run Lighthouse against the staging homepage on mobile. The Phase 3 exit
      criterion is 80+; this was never measurable in the build environment.
- [ ] Run `php artisan inventory:open-balances` and check a product's ledger
      opens with the count it should.
- [ ] Set the shop-wide reorder level in Settings → Inventory, and give the
      slow-moving products their own.
- [ ] Confirm `php artisan schedule:list` shows `stock:release-expired` and
      that the cron entry from section 6 is in place.
- [ ] Turn **`seo_allow_indexing` on** once the shop is really live, and check
      `robots.txt` and `sitemap.xml` on the real domain.
- [ ] Submit the sitemap in Search Console, after adding the verification code
      in Settings → Marketing & SEO.
- [ ] Decide on **`reviews_require_purchase`**. Off is the usual choice; the
      verified-purchase badge does the honest work either way.
- [ ] Remove the demo banners, reviews and subscribers if the client does not
      want them (`MarketingDemoSeeder` creates them; it is the only thing that
      does).
- [ ] Take a backup and **test restoring it**.
