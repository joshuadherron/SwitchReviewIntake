# The IT Guys — Switch Review public PHP app

Existing five-step form, same `/api/switch-review` submission path. No redesign,
Node app, frontend framework, Graph credentials, sales sessions or sales code.
Target: PHP **8.3**, verified in Hostinger settings; PDO MySQL required.
Production deployment is now explicitly authorized by the owner. Use the separate
swform production database; mail stays off. No production backend has been deployed yet.
The production template is private/config.production.example.php (empty password).
The handler also supports SITE_ROOT/switch-private/config.php beside public_html,
so private credentials can be loaded without hosting-wide file access or SetEnv.

## Review migrations first — nothing has been run

Run `migrations/003-swform-table.sql` in the NEW staging form database
`u809002269_swform_test`. Run `migrations/004-sales-prospects.sql` in the SALES
staging database. The latter checks for existing columns and works after old 001.
001/002 are obsolete; do not rerun them. Existing tables/columns are retained.
The owner runs SQL manually after backup. Create the databases/users in Hostinger:
swform_insert_test for staging, swform_insert for production (use the actual
Hostinger-prefixed usernames). Each user has ALL privileges on its own swform
database only and NO access to any sales database. The PHP handler still executes
only prepared INSERTs; it has no prospects access or automatic migrations.
Production database is u809002269_swform; do not configure/deploy it yet.

## Hostinger staging setup

1. Create **switchtest.theitguys.us** on PHP/HTML hosting with PHP 8.3 and PDO MySQL.
   Keep switch.theitguys.us unchanged.
2. Upload only index.html, style.css, form.js, favicon.svg, handler.php, .htaccess.
   Do not upload migrations, tests, private config, README or sales source.
   Apache/LiteSpeed must honor rewrites/mod_headers. Back up/merge an existing
   .htaccess if present. DirectoryIndex routes GET to the single PHP handler.
3. Use environment config, or copy private/config.example.php **outside
   public_html**, e.g. /home/ACCOUNT/.switch-review/config.php. Set its values
   on the server, chmod 600, and point SWITCH_CONFIG_FILE at the absolute path.
   A nonsecret `SetEnv SWITCH_CONFIG_FILE /absolute/private/config.php` in
   .htaccess may be used if Hostinger's environment UI is unavailable. Never
   store real credentials in source or the web root. Disable display_errors;
   use a private PHP error log. Detailed SQL errors/submissions are never logged.
4. Create RATE_DIR outside web root (0700). Generate a random RATE_SECRET of at
   least 32 characters. Rate files contain timestamps only, with HMAC IP filenames.
   flock must work on this consistent local directory across all PHP workers.
   Missing rate storage fails closed. Periodically remove files older than a day
   with an account-owned maintenance job; never use a public uploads directory.
5. Set PUBLIC_ORIGIN to `https://switchtest.theitguys.us`, without trailing slash.
   Force HTTPS in Hostinger. The handler redirects cleartext to that fixed HTTPS
   origin; HSTS and security headers are supplied. Verify .htaccess header rules
   on assets too, and PHP post_max_size >= 16K (the app still enforces 10K).
6. TRUSTED_PROXIES defaults empty: use REMOTE_ADDR/native HTTPS. If Hostinger uses
   a TLS-terminating reverse proxy, obtain its exact IPs from hosting and configure
   them. Verify real client IP forwarding in staging. Arbitrary client-supplied
   forwarded headers are never trusted; rate identity is the rightmost untrusted
   IP in a trusted proxy chain. This must be checked before ads.

## Environment variables

Environment overrides private config; private config keys omit SWITCH_:

| Environment | Purpose |
| --- | --- |
| SWITCH_CONFIG_FILE | Optional absolute private PHP config path |
| SWITCH_PUBLIC_ORIGIN | Exact HTTPS staging origin |
| SWITCH_DB_HOST / SWITCH_DB_PORT | MySQL host/port, default port 3306 |
| SWITCH_DB_NAME | u809002269_swform_test for staging; never the sales DB |
| SWITCH_DB_USER / SWITCH_DB_PASSWORD | Database-scoped swform user and password |
| SWITCH_DB_SSL_CA | MySQL TLS CA path if required |
| SWITCH_RATE_DIR | Existing private directory outside web root |
| SWITCH_RATE_SECRET | Random secret of at least 32 characters |
| SWITCH_TRUSTED_PROXIES | Exact trusted proxy IPs, comma separated |

No Graph/shared-mailbox variables belong in the public app.

## Behavior and editing

GET /, /index.html, /switch-review fills UTM/fbclid fields even without JavaScript.
POST /api/switch-review accepts URL-encoded fields, preserving repeated concerns
that PHP's default parser would discard. Max encoded body: **10,240 bytes**.
All attempts count toward five/hour/IP; sixth gets 429 with Retry-After.
Honeypot returns apparent success without inserting. Browser source is ignored;
the sales importer sets Switch Review.

Validation enforces chip allowlists, required contact/consent, UTF-8 and limits.
Email is capped at 200 characters to fit the existing sales email column; longer
values are explicitly rejected, never truncated. Other text is stored only with
Something else selected. Concerns may be empty. Invalid fields get 422 with JSON
field errors, or the plain form rerendered with escaped answers/choices retained.
Other responses: 400 duplicate scalar fields, 403 unexpected origin, 413 >10KB,
415 unsupported format, 503 unavailable DB/rate config. JSON ok:true is sent only
after successful insert, except the honeypot decoy. No-JS success shows the
existing confirmation; PHP root-relative assets keep /api/ from breaking CSS.

Edit copy/chips in index.html and matching handler allowlists when values change.
Styles remain in style.css. The only necessary frontend behavior adjustment is
CSS-based progress state to satisfy CSP without unsafe-inline. No pixels, new
packages or third-party scripts. PDO is supplied by the hosting PHP runtime.

## Tests and staging acceptance

Run `php -l handler.php` and `php tests/handler.test.php` on PHP 8.3. Tests use PDO
doubles and temporary private rate files, no live DB: validation, honeypot,
repeated choices, rate limit/expiry, body limit, UTM, prepared INSERT, outside-size
flag, fallback/prefill/errors/confirmation, asset paths and HTML escaping.

After manual staging migration/configuration, verify:

- Form account has privileges only on swform_test and none on sales. It may
  SELECT/UPDATE/DELETE within its own isolated DB under this Hostinger plan.
- JS and no-JS submission with ?utm_source=facebook&utm_campaign=switch-review
  save all answers and attribution; validation retains answers; honeypot saves none.
- >10KB is 413, sixth request is 429, HTTPS redirects, HSTS/CSP headers apply,
  CSS/JS work under CSP, and trusted proxy/client IP configuration is correct.
- Separate sales staging changes import once on reruns, show New lead while mail
  is off, preserve ranges and schedule followups. Then test restricted Graph mail.

No new SQL, user permissions, Exchange admin actions or deployments have been run
by this change. The owner reports that the previous 001 already ran in sales.
