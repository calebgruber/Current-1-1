# StageTrack Enterprise

**Equipment, production and rental management for performing arts.** StageTrack is a modern 1:1 workflow replacement for Current RMS. It is built for educational theatre programs, performing arts centers, university production departments, electrics and audio shops, event spaces and rental operations, and it can be self-hosted on ordinary **cPanel shared hosting** with no Docker and no command line.

> **Development status:** StageTrack is incomplete and is not ready for production. The features and deployment workflow below describe the intended product; module screens, administration, security integrations and end-to-end workflows still require implementation and verification.

> Built with Laravel 12 · Vue 3 + TypeScript · Inertia.js · Tailwind CSS v4 · MySQL 8

## Planned feature highlights

- **Inventory & assets.** Serialized and bulk products, categories, manufacturers, tags, a locations tree, accessories (required, optional, safety, consumable, auto-add), asset status history, condition reports, and barcode/QR labels.
- **Availability you can trust.** A sweep-line peak calculation over any date window, where back-to-back bookings don't conflict. Results are broken down into reserved, checked out, tentative, maintenance, damaged and lost, and **every number links to the jobs, reservations and assets behind it**. Overbooking is blocked unless an authorized user overrides it.
- **Jobs with an 11-stage lifecycle.** Inquiry → Draft → Quote → Approved → Reserved → Prep → Ready → Checked Out → Returned → Closed → Archived. Approval steps are gated by permission. Quotes and approved jobs hold stock tentatively; Reserved and later stages hold it firmly.
- **Required vs spare.** "20 Lustr + 5 spare = 25 pulled" is tracked on every pull sheet, report and checkout, and accessories are expanded automatically.
- **Warehouse scanning.** Pick, prep, stage, checkout, return, damage and lookup modes work with any USB or Bluetooth scanner. A fullscreen TV/tablet display shows green, amber or red results with sounds, and every scan is logged.
- **Equipment rules.** A no-code rule builder covers cases like "moving lights need PowerCON", "wireless mics need 2 AA each" and "hanging fixtures need clamps + safety cables". Accessories are checked for completeness.
- **Document designer.** Covers 13 document types (quotes, pull sheets, invoices, delivery notes, damage reports and more). Use a visual block editor or code mode with a safe, sandboxed template language. Live preview updates as you type, and documents export to PDF.
- **Report builder.** 10 data sources, filters, grouping, aggregates, charts, custom fields, CSV/XLSX/PDF export, dashboard widgets, scheduled email delivery and role-restricted visibility.
- **Custom fields.** 14 field types across products, assets, jobs, reservations, documents, contacts, maintenance and locations, all usable in reports.
- **Security.** Sign in with username or email; TOTP 2FA with recovery codes; passkeys (Windows Hello, Touch ID, Face ID, security keys); session management; configurable password, lockout and 2FA policies; and a full audit log.
- **Roles you define.** About 130 granular capabilities. No roles are hard-coded.
- **Web administration.** Setup wizard, migrations (run, review, roll back), backups and restore, logs, health, storage, cache and one-click updates, all from the browser.
- **Fast, keyboard-first UX.** ⌘K command palette and global search (exact barcode/serial matches first), `g i` / `g j` / `g w` / `g a` navigation, dark mode, a responsive layout and WCAG 2.2 AA.

## Quick start

### cPanel (planned deployment)

1. Create a MySQL 8 database and user in cPanel.
2. Upload and extract the release zip **outside** `public_html`, then point the domain's document root at `public/`.
3. Select PHP 8.3 with the `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `intl`, `bcmath` and `fileinfo` extensions.
4. Visit your domain. The **setup wizard** (`/setup`) checks requirements, writes `.env`, installs the database and creates your administrator.
5. Add a cron job: `* * * * * cd /home/acct/stagetrack && php artisan schedule:run` (or use the HTTP cron URL).

The full guide is in [docs/INSTALLATION.md](docs/INSTALLATION.md).

### Local development

Install PHP 8.3 with Composer and the SQLite, mbstring, XML, curl and zip PHP
extensions, plus Node.js 22 and npm. From the project root:

```bash
composer install
if [ ! -f .env ]; then cp .env.example .env && php artisan key:generate; fi
npm ci
npm run build
php artisan serve
```

Open `http://localhost:8000/setup` and complete the wizard in one browser
session. Select SQLite and use `database.sqlite` as the database name for a
local database, or supply credentials for a separate MySQL database. The
wizard creates the administrator and installs the base data. Keep access
restricted during setup; see [the installation guide](docs/INSTALLATION.md).

For frontend development, run `npm run dev` in a second terminal while
`php artisan serve` is running. Run `php artisan test`, `npm run typecheck`
and `npm run build` to check changes. Tests use an in-memory SQLite database.
Do not regenerate the application key on an existing installation: it is used
to encrypt data. The wizard sets production-oriented environment values, so
review `.env` after setup when working locally.

## Documentation

| | |
|---|---|
| [Documentation index](docs/README.md) | Start here |
| [Software Requirements (SRD)](docs/SRD.md) | Requirements, personas, Current RMS parity, acceptance criteria |
| [Modules & workflows](docs/MODULES.md) | Architecture, module responsibilities, sequence and state diagrams |
| [Database](docs/DATABASE.md) | Schema, indexes, ER diagram |
| [HTTP API](docs/API.md) | Endpoints, permissions, JSON examples |
| [Permissions](docs/PERMISSIONS.md) | Capability catalog and role model |
| [Architecture decisions](docs/DECISIONS.md) | ADRs |
| [Roadmap](docs/ROADMAP.md) | Phases and future ideas |
| [Installation & operations](docs/INSTALLATION.md) | cPanel, cron, backups, updates, troubleshooting |

## Project layout

```text
app/Domain/{Module}/     Models, Services, Http/Controllers, Policies, DTOs, Enums, Console, routes.php
app/Support/             shared traits (Auditable, Searchable, HasCustomFields) and HTML sanitizer
config/permissions.php   permission catalog
config/stagetrack.php    app version, backups, updates, cron token, search
database/migrations/     schema
resources/js/            Vue 3 + TS: Modules/{Module}/Pages, Components, Layouts
tests/Unit|Feature/{Module}
docs/                    planning & architecture documentation
```

## License

Proprietary. All rights reserved.
