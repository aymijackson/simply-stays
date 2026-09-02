# Deployment checklist

This is a Laravel 12 modular monolith (see `Modules/`). Follow this checklist for a
production deploy — it assumes a standard LAMP/LEMP-style host or a container platform
that can run PHP-FPM + a queue worker + MySQL.

## 1. Server requirements

- PHP 8.2+ with extensions: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`,
  `json`, `bcmath`, `fileinfo`, `gd` (used by spatie/laravel-medialibrary for image handling).
- MySQL 8+ or MariaDB 10.6+.
- Composer 2.x.
- A process supervisor (systemd or Supervisor) to keep the queue worker alive.

Front-end assets (`public/css`, `public/js`, `public/vendor`) are plain, unbundled files
committed to the repo — there's no Node/npm/build step at deploy time.

## 2. Environment variables

Copy `.env.example` to `.env` and set, at minimum:

- `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://your-domain`
  (`APP_URL` matters here specifically — it's used to build payment gateway callback/webhook
  URLs, so a wrong value breaks checkout redirects and signature-verified webhooks).
- `APP_KEY` — generate with `php artisan key:generate` (do this once; never regenerate on an
  existing install, it invalidates all encrypted data and active sessions).
- `DB_*` — production database credentials.
- `MAIL_*` — a real transport (Postmark/SES/SMTP/etc). Notifications (booking, payment,
  commission emails) are queued and will silently no-op to the log if this is left as `log`.
- `QUEUE_CONNECTION=database` (default) or `redis` if available — notifications and other
  queued work depend on a worker actually processing this queue (step 5).
- Payment gateways — set real keys per gateway you intend to accept:
  - `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`
  - `PAYSTACK_SECRET_KEY`
  - `FLUTTERWAVE_SECRET_KEY`, `FLUTTERWAVE_WEBHOOK_HASH`
  - `PAYMENTS_DEFAULT_GATEWAY` — which one the checkout page defaults to.
- `REFERRALS_DEFAULT_COMMISSION_RATE` — default agent commission % if you want something
  other than the 5% default.

## 3. Install & build

```bash
composer install --no-dev --optimize-autoloader
php artisan storage:link
php artisan migrate --force
```

`storage:link` is required — listing/service photos are served from `public/storage`,
symlinked to `storage/app/public` (spatie/laravel-medialibrary's default disk).

**Shared hosting without symlink support:** if `public/` is deployed out to a sibling
document-root folder (e.g. cPanel-style hosting where the app lives outside the web root)
and the host doesn't allow symlinks, skip `storage:link` entirely. Instead set
`PUBLIC_DISK_ROOT=/path/to/document-root/storage` in `.env` so uploads are written directly
into the served folder instead of `storage/app/public`. See `scripts/deploy-public.sh`,
which syncs `public/`'s contents into that sibling folder, rewrites `index.php`'s
`../vendor`/`../bootstrap`/`../storage` paths to go through the app folder, and warns if
`PUBLIC_DISK_ROOT` doesn't match the folder it just deployed to.

## 4. Cache the framework for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Re-run these after every deploy. If you change `.env` values on a running server, you must
re-run `config:cache` (or `config:clear` if you don't cache config) — cached config silently
ignores new `.env` values otherwise.

## 5. Queue worker

Booking/payment/referral notifications and other background work run on the queue — nothing
sends without a worker running. Example Supervisor config:

```ini
[program:short-stay-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/app/artisan queue:work --sleep=3 --tries=3 --max-time=3600
directory=/path/to/app
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/path/to/app/storage/logs/worker.log
stopwaitsecs=3600
```

Restart the worker after every deploy (`supervisorctl restart short-stay-worker:*`) —
running workers keep old code in memory otherwise.

## 6. Payment gateway webhook configuration

Register these URLs in each gateway's dashboard once `APP_URL` is live:

- Stripe: `https://your-domain/payments/webhook/stripe` — subscribe to at least
  `checkout.session.completed`. Copy the signing secret into `STRIPE_WEBHOOK_SECRET`.
- Paystack: `https://your-domain/payments/webhook/paystack` in Settings → API Keys & Webhooks.
- Flutterwave: `https://your-domain/payments/webhook/flutterwave`, and set the same
  "secret hash" value in both the Flutterwave dashboard and `FLUTTERWAVE_WEBHOOK_HASH`
  (Flutterwave verifies webhooks by exact string match on this header, not HMAC).

These three routes are the only ones exempted from CSRF verification
(`bootstrap/app.php` → `validateCsrfTokens(except: ['payments/webhook/*'])`) — don't widen
that exemption list without a specific reason.

## 7. File permissions

`storage/` and `bootstrap/cache/` must be writable by the web server user:

```bash
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

## 8. Security checklist before going live

- [ ] `APP_DEBUG=false` — a stack trace leaking to end users is an information disclosure risk.
- [ ] `.env` is not web-readable and not committed to version control.
- [ ] HTTPS is enforced (payment gateway redirects and webhooks require it in practice).
- [ ] Rate limiting is active — this app throttles `/api/*` at 60 req/min per user/IP
      (`app/Providers/AppServiceProvider.php`) and login/register at 6 req/min
      (`Modules/Auth/routes/{web,api}.php`). Confirm these survive behind any reverse proxy /
      CDN (check `TrustProxies` / `X-Forwarded-For` handling if you're behind one).
- [ ] Payment gateway keys are live-mode keys, not test-mode, before accepting real bookings.
- [ ] Database backups are configured (this app has no built-in backup tooling).

## 9. Post-deploy smoke test

1. `GET /up` — Laravel's built-in health check route, should return 200.
2. Register a guest account, log in, browse `/listings`.
3. Confirm queued mail actually sends: trigger a booking request and check the worker log /
   inbox rather than assuming — `MAIL_MAILER=log` in `.env` will pass silently without ever
   reaching a real inbox.
4. Complete one real low-value test booking end-to-end through each payment gateway you've
   enabled, in that gateway's test/sandbox mode first if it's your first time wiring it up.
5. Check `/admin` loads for an Admin-role account and the activity log
   (`/admin/activity`) is recording events.

## 10. Ongoing maintenance not yet automated

These are known gaps, not yet built into the app — plan for them separately:

- No scheduled job auto-transitions bookings from `confirmed` to `completed` after checkout
  passes; review eligibility currently also accepts `confirmed` bookings whose checkout date
  has passed, which covers most of this, but a real `completed` status update is not automatic.
- No automated database backup/retention policy.
- No log rotation configured beyond Laravel's default daily channel — set `LOG_CHANNEL=daily`
  and confirm your platform rotates/ships `storage/logs/*.log`.
