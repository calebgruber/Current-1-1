# Installation & Operations Guide

This guide covers installing StageTrack Enterprise on **cPanel shared hosting** (no SSH or Docker required), day-to-day operations, and setting up a **local development** environment.

- [1. Requirements](#1-requirements)
- [2. cPanel installation](#2-cpanel-installation)
- [3. Setup wizard](#3-setup-wizard)
- [4. Scheduler & queue (cron)](#4-scheduler--queue-cron)
- [5. Configuration reference](#5-configuration-reference)
- [6. Backups & restore](#6-backups--restore)
- [7. Updating](#7-updating)
- [8. Troubleshooting](#8-troubleshooting)
- [9. Local development](#9-local-development)

## 1. Requirements

| Component | Requirement |
|---|---|
| PHP | **8.3+** (cPanel → *Select PHP Version* / *MultiPHP Manager*) |
| PHP extensions | `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `intl`, `bcmath`, `fileinfo` (plus defaults: `ctype`, `curl`, `dom`, `json`, `tokenizer`, `xml`, `session`, `filter`) |
| Database | **MySQL 8.0+** (MariaDB 10.6+ works but FULLTEXT ranking differs), `utf8mb4` |
| PHP settings | `memory_limit` ≥ 256M, `max_execution_time` ≥ 120, `upload_max_filesize`/`post_max_size` ≥ 64M (for release zips and attachments) |
| Disk | ~150 MB for the application + uploads + backups |
| HTTPS | Required for passkeys (WebAuthn only works on secure origins) — enable AutoSSL |
| Cron | One cPanel cron job (or an external HTTP pinger) |

Node.js is **not** required on the server: release packages contain prebuilt assets in `public/build` and production dependencies in `vendor/`.

## 2. cPanel installation

1. **Create the database.** cPanel → *MySQL® Database Wizard*: create a database (e.g. `acct_stagetrack`), a user with a strong password, and grant **ALL PRIVILEGES**.
2. **Upload the release.** Download `stagetrack-x.y.z.zip`, open cPanel → *File Manager*, upload it to your home directory (e.g. `/home/acct/stagetrack`) — **not** inside `public_html` — and *Extract*.
3. **Point the web root at `public/`.** Choose one:
   - **Addon domain / subdomain (recommended):** cPanel → *Domains* → set *Document Root* to `/home/acct/stagetrack/public`.
   - **Primary domain with fixed `public_html`:** extract into `public_html/stagetrack` (or `public_html` itself) and keep the shipped root `.htaccess`, which rewrites every request into `public/` and denies access to everything else:

     ```apache
     <IfModule mod_rewrite.c>
         RewriteEngine On
         RewriteRule ^(.*)$ public/$1 [L]
     </IfModule>
     ```

     Verify that `https://your-domain/.env` and `https://your-domain/storage/logs/laravel.log` return **403/404**.
4. **Set the PHP version** to 8.3 for the domain and enable the extensions listed above.
5. **Permissions.** `storage/` and `bootstrap/cache/` must be writable by PHP (cPanel default `755` directories / `644` files with suPHP/PHP-FPM is fine). The `.env` file must be writable during setup.
6. **Enable HTTPS** (cPanel → *SSL/TLS Status* → *Run AutoSSL*).
7. Browse to your domain — you will be redirected to **`/setup`**.

> Before installation the app runs with file-based cache and sessions so it works without a database. The wizard switches to the database drivers.

## 3. Setup wizard

`EnsureApplicationInstalled` redirects every request to `/setup` until `storage/app/installed.lock` exists. After installation `/setup` returns **404**.

| Step | What happens |
|---|---|
| 1. Requirements | Checks PHP version, required extensions, writable `storage/`, `bootstrap/cache/`, `.env`. Blocking failures must be fixed before continuing. |
| 2. Database | Enter host (usually `localhost`), port `3306`, database, user, password. *Test connection* verifies credentials and the MySQL version, then writes `DB_*`, `APP_KEY` (generated), `APP_URL`, `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database` to `.env`. |
| 3. Install | Runs migrations, syncs the permission catalog (`config/permissions.php` → `permissions`), writes default settings and document templates. |
| 4. Administrator | Creates the first user with `is_super_admin = true` (name, username, email, password meeting the default policy). |
| 5. Organization | Organization name, timezone, currency, date format, logo. |
| 6. Finish | Writes `storage/app/installed.lock`, shows the cron line and the HTTP cron URL, then links to `/login`. |

**Next steps after install:** create roles (*Administration → Roles*), invite users, configure *Settings → Security* (2FA requirement, password policy), add locations and categories, then import products.

## 4. Scheduler & queue (cron)

The Laravel scheduler drives everything periodic — `routes/console.php` defines:

| Task | Frequency | Purpose |
|---|---|---|
| `reports:deliver-scheduled` | every 15 min | Email scheduled reports |
| `stagetrack:backup --scheduled` | daily 02:30 | Automatic backup + retention |
| `queue:work --stop-when-empty --max-time=50` | every minute | Drain the database queue (mail, exports) |

All tasks use `withoutOverlapping()`.

**Option A — cPanel cron (preferred).** cPanel → *Cron Jobs* → *Once Per Minute* (`* * * * *`):

```bash
cd /home/acct/stagetrack && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

Use the PHP 8.3 binary path for your host (often `/opt/cpanel/ea-php83/root/usr/bin/php`).

**Option B — HTTP cron.** If cron is unavailable, set `STAGETRACK_CRON_TOKEN` (a long random string) in `.env` and ping every minute from an external service:

```text
https://your-domain/cron/<STAGETRACK_CRON_TOKEN>
```

The endpoint runs `schedule:run` and returns `204`; a wrong token returns `404`.

*Administration → Health* shows the time of the last scheduler run; a stale heartbeat is flagged.

## 5. Configuration reference

Most configuration lives in **Settings** (database). `.env` holds infrastructure values:

| Variable | Default | Notes |
|---|---|---|
| `APP_NAME` | `StageTrack` | |
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Never enable debug in production |
| `APP_URL` | — | Must match the HTTPS origin (passkeys depend on it) |
| `APP_KEY` | generated | Encrypts TOTP secrets — **back it up**; losing it invalidates 2FA secrets |
| `DB_CONNECTION` | `mysql` | `sqlite` only for development/tests |
| `SESSION_DRIVER` | `database` | Required for the session management screen |
| `CACHE_STORE` | `database` | `file` also works |
| `QUEUE_CONNECTION` | `database` | Drained by the scheduler |
| `MAIL_*` | — | SMTP from cPanel *Email Accounts* |
| `STAGETRACK_ASSUME_INSTALLED` | `false` | Skip the lock-file check (CI, containers) |
| `STAGETRACK_BACKUPS_KEEP` | `14` | Number of backups retained |
| `STAGETRACK_UPDATE_FEED` | — | URL of the JSON version feed `{version, notes, download_url}` |
| `STAGETRACK_CRON_TOKEN` | — | Enables `/cron/{token}` |

## 6. Backups & restore

*Administration → Backups* (`admin.backups.*`):

- **Create backup** — `BackupManager` writes a pure-PHP SQL dump via PDO (no `mysqldump` needed) plus the `storage/app` files (uploads, generated documents) into a zip under `storage/app/backups`.
- **Scheduled backups** — daily at 02:30; the oldest beyond `STAGETRACK_BACKUPS_KEEP` are deleted.
- **Download** — keep off-site copies (the zip contains all data; store securely).
- **Restore** — upload or pick a backup → the app enters maintenance mode, restores the database and files, clears caches, runs pending migrations and exits maintenance mode. Requires `admin.backups.admin`. A safety backup is taken before restoring.
- `.env` is **not** included; record `APP_KEY` separately (password manager).

CLI equivalent (optional): `php artisan stagetrack:backup`.

## 7. Updating

*Administration → Updates* (`admin.updates.*`):

1. Take a backup (the updater offers this automatically).
2. **Check for updates** reads `STAGETRACK_UPDATE_FEED`; or **upload** a release zip manually.
3. The updater verifies the archive (rejects absolute paths and `..` entries — zip-slip protection), enables maintenance mode, extracts over the installation (preserving `.env`, `storage/`, `installed.lock`), runs migrations, clears/rebuilds caches, and disables maintenance mode.
4. Review *Administration → Migrations* for status; a failed migration can be rolled back there.

**Manual update (no updater):** upload and extract the new release over the old one (excluding `.env` and `storage/`), then open *Administration → Migrations* → *Run pending* and *Administration → Cache* → *Clear all*.

## 8. Troubleshooting

| Symptom | Fix |
|---|---|
| **500 error right after upload** | PHP version < 8.3 or missing extension; check `storage/logs/laravel.log` via File Manager. Ensure `storage/` and `bootstrap/cache/` are writable. |
| Redirect loop to `/setup` | `storage/app/installed.lock` missing or not readable; finish the wizard or restore the file. |
| `/setup` returns 404 | Already installed (lock file present). To reinstall, delete the lock file **and** empty the database. |
| CSS/JS missing (unstyled page) | Document root not pointing at `public/`, or `public/build` not uploaded. |
| "No application encryption key" | `.env` not writable during setup; set `APP_KEY` manually and re-run the database step. |
| Passkeys unavailable | Site must use HTTPS and `APP_URL` must match the browser origin; `security.allow_passkeys` must be on. |
| 2FA codes always rejected | Server clock drift — ask the host to sync NTP; codes allow ±30 s. |
| Locked out of admin | Wait `security.lockout_minutes`; another super admin can reset 2FA/password under *Administration → Users*. |
| Scheduled reports / emails not sent | Cron not running (check Health heartbeat) or `MAIL_*` wrong; inspect failed jobs in *Administration → Health*. |
| Backups fail on large sites | Raise `max_execution_time`/`memory_limit`; ensure disk quota. |
| Search returns nothing for short words | MySQL FULLTEXT ignores words shorter than `innodb_ft_min_token_size` (3); exact code matches still work. Rebuild the index from *Administration → System*. |
| Upload of release zip fails | Increase `upload_max_filesize`/`post_max_size`, or extract manually. |

Logs are viewable in *Administration → Logs* (`admin.logs.view`).

## 9. Local development

Prerequisites: PHP 8.3 with the extensions above, Composer 2, Node 20+, MySQL 8 (or SQLite).

```bash
git clone <repo-url> stagetrack && cd stagetrack
composer install
cp .env.example .env
php artisan key:generate
npm install
```

Database — either SQLite (default in `.env.example`):

```bash
touch database/database.sqlite
```

or MySQL (`DB_CONNECTION=mysql`, `DB_DATABASE=stagetrack`, …). Then either use the web wizard at `http://localhost:8000/setup`, or skip it:

```bash
php artisan migrate
php artisan stagetrack:sync-permissions
# set STAGETRACK_ASSUME_INSTALLED=true in .env, then create a super admin via tinker or the wizard
```

Run:

```bash
composer run dev        # serve + queue listener + logs + Vite concurrently
# or separately:
php artisan serve
npm run dev
```

Tests & quality:

```bash
php artisan test                     # PHPUnit — tests/Unit/{Module}, tests/Feature/{Module}
php artisan test --filter=Availability
vendor/bin/pint                      # code style
npm run build                        # production assets (Vite)
```

Building a release:

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
zip -r stagetrack-x.y.z.zip . -x ".git/*" "node_modules/*" "tests/*" ".env" "storage/logs/*" "storage/app/*"
```
