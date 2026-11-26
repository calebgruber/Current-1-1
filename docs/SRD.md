# StageTrack Enterprise — Software Requirements Document (SRD)

| | |
|---|---|
| Product | StageTrack Enterprise |
| Version | 1.0 (target release) |
| Stack | Laravel 12 · PHP 8.3 · Livewire 4 · TallStackUI 4 · Blade · Tailwind CSS v4 · MySQL 8 |
| Status | Approved for implementation |

Related: [MODULES.md](MODULES.md) · [DATABASE.md](DATABASE.md) · [API.md](API.md) · [PERMISSIONS.md](PERMISSIONS.md) · [DECISIONS.md](DECISIONS.md)

---

## 1. Purpose

StageTrack Enterprise is a self-hosted production-operations platform that provides a **1:1 workflow replacement for Current RMS** tailored to educational theatre programs, performing arts centers, university production departments, electrics and audio shops, event spaces and rental operations. It manages the full life of equipment — catalogue, serialized assets and bulk stock, availability, jobs/productions, reservations, warehouse pick → prep → stage → checkout → return, maintenance, documents and reporting — while remaining installable on **commodity cPanel shared hosting** with no command line access.

## 2. Scope

### In scope (v1.0)

- Inventory catalogue (products, categories, manufacturers, tags, images, accessories/kits, bulk stock per location, hierarchical locations).
- Serialized asset tracking with status history, condition reports and barcode/QR labels.
- Availability engine & **Availability Explorer** with drill-down on every number.
- Jobs (productions/events/rentals) with an 11-stage lifecycle, allocations with spares, accessory expansion and conflict detection.
- Reservations (job-generated and standalone holds).
- Warehouse scanning workflows (pick, prep, stage, checkout, return, damage, lookup) and fullscreen display stations.
- Maintenance & damage management.
- No-code dependency/validation rules engine.
- Document template designer (visual + code) with live preview and PDF output for 13 document types.
- Report builder, exports (CSV/XLSX/PDF), dashboard widgets and scheduled delivery.
- Custom fields on 8 entity types.
- Contacts & organizations.
- Authentication with username/email, TOTP 2FA, passkeys, session management and security policies.
- Database-driven roles & permissions; audit logging.
- Global search with ⌘K command palette.
- Web setup wizard and web administration (migrations, backups/restore, logs, cache, health, storage, updates).

### Out of scope (v1.0) — see [ROADMAP.md](ROADMAP.md)

Accounting/invoicing sync, payment processing, public REST API tokens, offline PWA, native mobile apps, direct label-printer drivers, multi-tenant SaaS hosting, crew scheduling/labour booking, vehicle routing.

## 3. Stakeholders & personas

| Persona | Context | Primary goals | Key modules |
|---|---|---|---|
| **Production Manager** (PM) | Runs the season calendar for a theatre department or PAC | Create jobs from inquiries, quote, approve, avoid double-booking, see what is out and when it returns | Jobs, Reservations, Availability, Documents, Reporting |
| **Technical Director / Master Electrician (TD/ME)** | Owns a show's technical package | Build equipment lists by section with spares, ensure accessory completeness (cables, clamps, safeties), print pull sheets | Jobs, Rules, Documents, Availability |
| **Shop Head** (Electrics / Audio / Props) | Owns inventory and the shop floor | Keep catalogue accurate, track assets & maintenance, run the warehouse, manage damage | Inventory, Assets, Maintenance, Warehouse, Rules |
| **Student Crew** | Students on shop hours, often on shared devices | Pick/prep/return quickly with a scanner, clear feedback, no ambiguity | Warehouse, Availability (read) |
| **Warehouse Staff** | Dedicated staff at a rental house or PAC dock | High-throughput scanning, fullscreen station displays, damage capture | Warehouse, Maintenance |
| **Faculty** | Instructors / designers | See what exists, book class-use holds, read reports | Inventory (read), Reservations, Reporting |
| **Administrator** | IT-light staff member responsible for the install | Install via browser, manage users/roles/security, back up, update — without SSH | Administration, Settings, Authentication |

## 4. Glossary

| Term | Definition |
|---|---|
| **Product** | A catalogue item type (e.g. "ETC Source Four Lustr 3"). Either *serialized* or *bulk*. |
| **Asset** | One physical serialized unit of a product, with asset number, serial, barcode and status. |
| **Bulk stock** | Quantity-tracked units per location (`stock_levels`), e.g. "Stage pin 10ft cable × 140". |
| **Location** | Hierarchical place: warehouse, room, shelf, bin, venue, vehicle. |
| **Accessory** | A product attached to another product with a per-unit quantity and type (required/optional/safety/consumable). |
| **Job** | A production, event, rental or class booking (`production_jobs`). Equivalent to a Current RMS Opportunity/Order. |
| **Allocation / Job item** | A line on a job: product + `required_quantity` (active) + `spare_quantity`. |
| **Pull quantity** | `required + spare` — what the warehouse physically pulls. |
| **Reservation / Hold** | A time-windowed quantity hold on a product (`reservations`): tentative, confirmed or released. |
| **Tentative** | Provisional hold from a quote/approved job; shown but not deducted unless `reservations.count_tentative`. |
| **Hold window** | `prep_starts_at` (or `starts_at`) → `returns_at` (or `ends_at`). |
| **Availability** | `total − reserved − checked_out − maintenance − damaged − lost (− tentative)` at the peak moment of a window. May be negative (shortage). |
| **Peak** | The instant inside a window when concurrent holds are greatest. |
| **Source** | The record behind an availability number (job, reservation, maintenance record, asset, location). |
| **Scan mode** | pick, prep, stage, checkout, return, damage, lookup. |
| **Station** | A named scanner/display device (e.g. "Dock TV 1"). |
| **Rule** | A no-code dependency/validation definition evaluated against a job's allocations. |
| **Template** | A document layout (visual blocks or code) rendered with the safe template language. |
| **Generated document** | Immutable HTML (and optional PDF) snapshot of a rendered template. |
| **Permission** | Capability key `module.resource.action` from the catalog. |
| **Role** | Administrator-defined bundle of permissions. |

## 5. Functional requirements

Priority: **M** = must (v1.0), **S** = should (v1.0 if time permits), **C** = could (later).

### 5.1 Inventory (INV)

| ID | Requirement | Pri |
|---|---|---|
| FR-INV-001 | Users with `inventory.products.create` can create products with name, SKU, category, manufacturer, model, description, tracking type (serialized/bulk), replacement cost, rental price, weight, barcode, default location, images and custom fields. | M |
| FR-INV-002 | SKU and barcode are unique when present; validation errors are shown inline. | M |
| FR-INV-003 | Products can be soft-deleted; deleted products remain visible in historical jobs, documents and reports. | M |
| FR-INV-004 | Hierarchical categories (parent/child) with colour, icon and sort order. | M |
| FR-INV-005 | Manufacturers can be created inline from the product form. | S |
| FR-INV-006 | Products can carry multiple tags; tags are usable in filters, rules and reports. | M |
| FR-INV-007 | Products can define accessories with per-unit quantity (decimal), type (required/optional/safety/consumable), auto-add flag and notes. A product cannot be its own accessory. | M |
| FR-INV-008 | Bulk products track stock per location with quantity and maintenance/damaged/lost buckets. Stock adjustments are audited; bulk adjustments need `inventory.stock.approve`. | M |
| FR-INV-009 | Locations form a tree (warehouse → room → shelf → bin, plus venue and vehicle types) with optional unique code and barcode. | M |
| FR-INV-010 | Product list supports search, filter (category, tag, manufacturer, tracking, active, custom fields), sort, pagination and CSV/XLSX export (`inventory.products.export`). | M |
| FR-INV-011 | Product detail page shows assets/stock, accessories, upcoming reservations, maintenance history, documents and audit trail. | M |
| FR-INV-012 | **Availability Explorer**: for any set of products and window, show total, reserved, checked out, maintenance, damaged, lost, tentative, available and peak time. | M |
| FR-INV-013 | Every number in the Availability Explorer is clickable and lists its sources (job, reservation, maintenance record, asset, location) with quantity, dates and link. | M |
| FR-INV-014 | Availability shows shortages as negative numbers with clear red styling and "overbooked by N" text. | M |
| FR-INV-015 | Availability respects `reservations.count_tentative`: when off, tentative holds are shown as a separate provisional figure and not deducted. | M |
| FR-INV-016 | Back-to-back bookings (one ends exactly when the next starts) never conflict. | M |
| FR-INV-017 | Availability timeline (per-day bars) for a product over a date range. | S |
| FR-INV-018 | CSV import of products and bulk stock with dry-run preview (`inventory.products.admin`). | S |

### 5.2 Assets (AST)

| ID | Requirement | Pri |
|---|---|---|
| FR-AST-001 | Create serialized assets for a product with unique asset number, optional serial, unique barcode, condition, location, purchase data, warranty and custom fields. | M |
| FR-AST-002 | Bulk-create N assets with an asset-number pattern (e.g. `LX-{0000}`) and auto-generated barcodes. | M |
| FR-AST-003 | Asset status is one of available, allocated, checked_out, maintenance, damaged, lost, retired. Retired assets are excluded from totals. | M |
| FR-AST-004 | Every status change writes `asset_status_history` with from/to, location, context (job/maintenance/scan), user and notes. | M |
| FR-AST-005 | Manual status changes require `assets.status.edit`; marking lost/retired requires `assets.status.approve`. | M |
| FR-AST-006 | Condition reports with notes and photos. | M |
| FR-AST-007 | Print barcode (Code 128) and QR labels for one or many assets/locations/bulk products to PDF label sheets (`assets.labels.export`). | M |
| FR-AST-008 | Asset detail shows current job assignment, status history timeline, maintenance, scans and audit trail. | M |
| FR-AST-009 | Assets resolve from barcode, asset number or serial number when scanned or searched. | M |

### 5.3 Contacts (CON)

| ID | Requirement | Pri |
|---|---|---|
| FR-CON-001 | Manage people and organizations; a person may belong to an organization. | M |
| FR-CON-002 | Contacts have notes, attachments and custom fields and appear in global search. | M |
| FR-CON-003 | A job has a client contact plus any number of additional contacts with a role (e.g. "Stage Manager"). | M |

### 5.4 Jobs (JOB)

| ID | Requirement | Pri |
|---|---|---|
| FR-JOB-001 | Create jobs with name, client, venue, owner, colour, description, prep start, start, end, return dates and custom fields. Job numbers `J-00001` are auto-assigned. | M |
| FR-JOB-002 | Job lifecycle: Inquiry, Draft, Quote, Approved, Reserved, Prep, Ready, Checked Out, Returned, Closed, Archived. Only transitions defined in `JobStage::allowedTransitions()` are permitted. | M |
| FR-JOB-003 | Entering Approved or Reserved requires `jobs.stages.approve`; other transitions require `jobs.stages.edit`. | M |
| FR-JOB-004 | Each transition writes `job_stage_history` (from, to, user, notes) and an audit event. | M |
| FR-JOB-005 | Stage drives reservations: Quote/Approved → tentative; Reserved, Prep, Ready, Checked Out, Returned → confirmed; Closed/Archived → released; Inquiry/Draft → no holds. | M |
| FR-JOB-006 | Allocations record product, section, required quantity and spare quantity separately; pull quantity = required + spare is displayed everywhere. | M |
| FR-JOB-007 | Adding a product with auto-add accessories creates child lines with `ceil(per_unit × required)` required and `ceil(per_unit × spare)` spare; changing parent quantities recalculates children unless manually overridden. | M |
| FR-JOB-008 | Allocations are grouped by section and re-orderable by drag-and-drop. | S |
| FR-JOB-009 | Each allocation shows live availability for the job's hold window; shortages are flagged inline with a link to the Availability Explorer. | M |
| FR-JOB-010 | When `reservations.block_overbooking` is on, saving allocations or transitioning into a holding stage that would overbook is blocked unless the user has `reservations.overbooking.approve` and confirms an override (audited). | M |
| FR-JOB-011 | Allocation edits are allowed in Inquiry…Prep; later stages require `jobs.jobs.admin`. | M |
| FR-JOB-012 | Rule validation results (info/warning/error) display on the job and block entering Reserved when any *error*-severity rule fails (overridable with `jobs.stages.approve`). | M |
| FR-JOB-013 | Serialized assets can be pre-assigned to lines (role active/spare) or assigned by scanning during pick. | M |
| FR-JOB-014 | Job notes (pinnable) and file attachments (`jobs.files.*`). | M |
| FR-JOB-015 | Job list with stage filter chips, date range, owner, client, search; calendar view (month/week) coloured by job colour. | M |
| FR-JOB-016 | Duplicate a job (with allocations, without history/assignments). | S |
| FR-JOB-017 | Job totals (rental value from unit price × required quantity) shown on quotes. | S |

### 5.5 Reservations (RES)

| ID | Requirement | Pri |
|---|---|---|
| FR-RES-001 | `reservations` is the single source of availability holds; job allocations are synchronised into it automatically. | M |
| FR-RES-002 | Standalone holds (class use, rehearsals, loans) can be created with name, product, quantity, spare quantity, window, status and notes. | M |
| FR-RES-003 | Standalone holds go through the same conflict detection as jobs. | M |
| FR-RES-004 | Reservations list and calendar filterable by product, status, job and date. | M |
| FR-RES-005 | Releasing a hold sets status `released` (kept for history). | M |

### 5.6 Warehouse (WH)

| ID | Requirement | Pri |
|---|---|---|
| FR-WH-001 | Scan workspace with modes pick, prep, stage, checkout, return, damage, lookup; each mode gated by its `warehouse.*` permission. | M |
| FR-WH-002 | USB and Bluetooth/wireless scanners work as keyboards: a permanently focused input submits on Enter; focus is restored automatically after any click. Manual typing is supported. | M |
| FR-WH-003 | Each scan returns success / warning / error with a title and message and is logged in `scan_events` with station, user, job, asset/product, location and quantity. | M |
| FR-WH-004 | Pick: scanning an asset of a product on the job assigns it (active until required is met, then spare); scanning beyond pull quantity is an error unless `warehouse.allow_over_pull`. Bulk scans increment by a selectable quantity. | M |
| FR-WH-005 | Scanning an asset that is in maintenance, damaged, lost, retired or allocated to another job is an error naming the reason and the other job. | M |
| FR-WH-006 | Checkout marks assets `checked_out`, sets `checked_out_by/at`, increments `checked_out_quantity`; job can move to Checked Out when all required lines are satisfied (spares optional). | M |
| FR-WH-007 | Return marks assets returned → `available` (or `damaged` in damage mode), records `returned_by/at`, increments `returned_quantity`, and shows outstanding items. | M |
| FR-WH-008 | Damage mode creates a maintenance record (type damage) linked to the job and asset, with notes/photos; write-off requires `warehouse.damage.approve`. | M |
| FR-WH-009 | Lookup mode shows the asset/product/location card (status, location, current job, next booking) without changing state. | M |
| FR-WH-010 | Progress panel shows per line: required / spare / pull / picked / checked out / returned with colour-coded completion. | M |
| FR-WH-011 | Fullscreen display page (`warehouse.display.view`) for TVs/tablets: very large type, green success, amber warning, red error, last N scans, job progress. | M |
| FR-WH-012 | Audible feedback via WebAudio-generated tones (distinct success/warning/error), toggleable by `warehouse.sound_enabled` and per device. | M |
| FR-WH-013 | Touch targets ≥ 44×44 px on warehouse pages; usable on tablets in gloves. | M |

### 5.7 Maintenance (MNT)

| ID | Requirement | Pri |
|---|---|---|
| FR-MNT-001 | Maintenance records for an asset or bulk quantity with type (repair, inspection, pat_test, cleaning, damage, calibration), priority, title, description, due date, assignee and cost. References `MR-00001` auto-assigned. | M |
| FR-MNT-002 | Status workflow open → in_progress → awaiting_parts → completed / written_off. Active records reduce availability. | M |
| FR-MNT-003 | Opening a record for an asset sets it to `maintenance`; completing returns it to `available`; writing off sets `retired` (requires `maintenance.records.approve`). | M |
| FR-MNT-004 | Bulk maintenance adjusts `stock_levels.quantity_maintenance` / `quantity_damaged`. | M |
| FR-MNT-005 | Maintenance queue views (mine, overdue, by product) and notes/attachments. | M |

### 5.8 Rules (RUL)

| ID | Requirement | Pri |
|---|---|---|
| FR-RUL-001 | No-code rule builder: scope (category/tag/product/any), check (requires, requires_any, max_quantity, min_spares), requirement (category/tag/product + per + quantity), basis (pull/required), severity and message template with `{required} {found} {missing} {items}`. | M |
| FR-RUL-002 | Built-in accessory completeness check from `product_accessories` (required and safety accessories). | M |
| FR-RUL-003 | Rules are evaluated on job pages, on allocation save and before Reserved; results list the offending lines with a one-click "add missing items". | M |
| FR-RUL-004 | Rule test panel: evaluate a draft rule against an existing job before saving. | S |
| FR-RUL-005 | Rules can be activated/deactivated without deletion. | M |

### 5.9 Documents (DOC)

| ID | Requirement | Pri |
|---|---|---|
| FR-DOC-001 | Template types: quotation, project_quotation, draft_order, rental_agreement, picking_list, pull_sheet, pack_sheet, collection_note, delivery_note, invoice, return_sheet, maintenance_report, damage_report. One default per type. | M |
| FR-DOC-002 | Visual editor with blocks: heading, text (sanitized rich text), image/logo, fields, items_table, totals, signature, spacer, divider, columns, conditional, page_break, html. | M |
| FR-DOC-003 | Code editor for raw template HTML + CSS (requires `documents.templates.admin`). | M |
| FR-DOC-004 | Safe template language: `{{ expr \| filter }}`, `{% if/elseif/else/endif %}`, `{% for x in list %}` with `loop.index/first/last`, arithmetic, comparisons, and/or/not, functions `sum`, `count`, `round`, `min`, `max`, filters `currency`, `number`, `date`, `upper`, `lower`, `default`, `nl2br`, `raw`. No code execution; auto-escaping. | M |
| FR-DOC-005 | Live preview: the designer posts the draft (debounced ~400 ms) and renders the result in a sandboxed iframe against a chosen sample record. | M |
| FR-DOC-006 | Paper size (letter, A4, legal), orientation, watermark, logo and signature blocks. | M |
| FR-DOC-007 | Generate a document from a job/maintenance record: snapshot HTML into `generated_documents` (number `D-000001`) and download/print PDF via dompdf. | M |
| FR-DOC-008 | Pull sheets carry required, spare and pull quantities and expanded accessories. | M |
| FR-DOC-009 | Template versioning (version number increments on save; last editor stored). | M |

### 5.10 Reporting (RPT)

| ID | Requirement | Pri |
|---|---|---|
| FR-RPT-001 | Report builder over sources: products, assets, reservations, jobs, job_items, maintenance, locations, users, documents, scan_events — whitelisted columns only, including custom fields of the source entity. | M |
| FR-RPT-002 | Filters eq, neq, gt, lt, gte, lte, contains, not_contains, starts_with, in, not_in, between, empty (is null), not_empty (not null), last_days, next_days, is_true, is_false, combined with all/any; group by (with day/week/month/year date buckets); aggregates count, sum, avg, min, max; calculated columns (safe expression syntax); sort; limit (≤ 50 000 rows). | M |
| FR-RPT-003 | Charts: bar, line, pie, doughnut, single number, none. | M |
| FR-RPT-004 | Export CSV, XLSX, PDF (`reporting.reports.export`). | M |
| FR-RPT-005 | Reports can be private, shared with all, or restricted to roles. | M |
| FR-RPT-006 | Pin reports to the dashboard as widgets. | M |
| FR-RPT-007 | Scheduled email delivery daily/weekly/monthly at an hour, in a format, to recipients; run by `reports:deliver-scheduled`. | M |
| FR-RPT-008 | Built-in starter reports (utilisation, overdue returns, maintenance cost, damage by job, scans per user). | S |

### 5.11 Custom fields (CF)

| ID | Requirement | Pri |
|---|---|---|
| FR-CF-001 | Define fields for product, asset, job, reservation, document, contact, maintenance, location with types text, long_text, number, currency, date, datetime, checkbox, dropdown, multi_select, file, user, asset, url, relation. | M |
| FR-CF-002 | Field options: group, help text, options (choices / target / currency), validation (min, max, regex), required, show in list, searchable, sort order. | M |
| FR-CF-003 | Values are validated by `CustomFieldValidator` and stored in typed columns. | M |
| FR-CF-004 | Searchable fields are included in the search index; list columns and report columns/filters support custom fields. | M |

### 5.12 Search (SRC)

| ID | Requirement | Pri |
|---|---|---|
| FR-SRC-001 | Global search across products, assets, locations, contacts, jobs, standalone reservations, maintenance, documents, reports. | M |
| FR-SRC-002 | Exact code matches (barcode, serial, asset number, job number) rank first. | M |
| FR-SRC-003 | ⌘K / Ctrl+K command palette with search results, navigation commands and actions ("New job", "Open availability"). | M |
| FR-SRC-004 | Results respect permissions (entity types the user cannot view are omitted). | M |
| FR-SRC-005 | Search index rebuild command and admin button. | M |

### 5.13 Authentication & security (AUTH)

| ID | Requirement | Pri |
|---|---|---|
| FR-AUTH-001 | Sign in with username or email + password. | M |
| FR-AUTH-002 | TOTP two-factor (RFC 6238, 30 s, 6 digits, ±1 step) with QR enrolment, confirmation, 10 single-use recovery codes (format `XXXX-XXXX-XXXX`, stored hashed), regenerate and disable. Secrets encrypted at rest. | M |
| FR-AUTH-003 | Passkeys (WebAuthn) — register, name, list, remove; passwordless sign-in; disabled when `security.allow_passkeys` is false. | M |
| FR-AUTH-004 | Account lockout after `security.max_login_attempts` failures for `security.lockout_minutes`. | M |
| FR-AUTH-005 | Password policy: min length, mixed case + numbers, expiry; expired passwords force change. | M |
| FR-AUTH-006 | Require 2FA for everyone or for administrators; users without 2FA/passkey are redirected to security settings. | M |
| FR-AUTH-007 | Session management: list active sessions (device, IP, last activity), revoke one or all others. | M |
| FR-AUTH-008 | Password reset by email link. | M |
| FR-AUTH-009 | Deactivated users are signed out on their next request. | M |
| FR-AUTH-010 | Auth events audited (login, failure, lockout, 2FA/passkey changes, session revocation). | M |

### 5.14 Administration (ADM)

| ID | Requirement | Pri |
|---|---|---|
| FR-ADM-001 | Setup wizard at `/setup`: requirements → DB test & `.env` write → migrate + permission catalog → super admin → organization → finish (writes `storage/app/installed.lock`); locked (404) afterwards. | M |
| FR-ADM-002 | Users: create/edit/deactivate, assign roles, reset 2FA, unlock, revoke sessions. | M |
| FR-ADM-003 | Roles: create/edit/delete, permission matrix grouped by module with select-all per module/action, sync catalog. | M |
| FR-ADM-004 | Migrations: status, pending, run, rollback last batch — with automatic pre-migration backup. | M |
| FR-ADM-005 | Backups: create (SQL dump + storage files zip), download, delete, restore (with confirmation), scheduled daily backups, retention `STAGETRACK_BACKUPS_KEEP`. | M |
| FR-ADM-006 | Log viewer: list log files, tail/paginate, filter by level, download, clear. | M |
| FR-ADM-007 | Health: PHP version/extensions, DB connectivity, writable paths, queue backlog, failed jobs, scheduler heartbeat, last backup age, disk space. | M |
| FR-ADM-008 | Storage usage by area (uploads, backups, logs, cache, documents). | M |
| FR-ADM-009 | Cache: clear application/config/route/view caches and rebuild optimisations. | M |
| FR-ADM-010 | Updates: check feed, upload release zip, verify, back up, maintenance mode, extract (zip-slip safe), migrate, sync permissions, clear caches, exit maintenance. | M |
| FR-ADM-011 | Audit log browser with filters and export. | M |
| FR-ADM-012 | HTTP cron endpoint `/cron/{token}` for hosts without CLI cron. | M |

### 5.15 Settings (SET)

| ID | Requirement | Pri |
|---|---|---|
| FR-SET-001 | Organization: name, logo, address, currency, timezone, date format, default job duration. | M |
| FR-SET-002 | Security policies (`security.*`). | M |
| FR-SET-003 | Warehouse (`warehouse.*`) and reservations (`reservations.*`) behaviour toggles. | M |
| FR-SET-004 | Per-user preferences: theme (system/light/dark), sounds, default warehouse station. | M |

### 5.16 UX (UX)

| ID | Requirement | Pri |
|---|---|---|
| FR-UX-001 | Responsive, color-coded module tabs with icons; hide modules the user lacks permission for. | M |
| FR-UX-002 | Keyboard shortcuts: `g i` inventory, `g j` jobs, `g w` warehouse, `g a` availability, `n` new (context), `/` search, `?` shortcut help, `⌘K` palette. Disabled while typing in inputs. | M |
| FR-UX-003 | Dark mode (system/light/dark) persisted per user. | M |
| FR-UX-004 | Responsive from 360 px phones to 4K displays. | M |
| FR-UX-005 | Toasts for flash messages; optimistic UI on inline edits with rollback on error. | M |

## 6. Non-functional requirements

### 6.1 Performance

| ID | Requirement | Target |
|---|---|---|
| NFR-PERF-001 | Availability for one product over any window with 50,000 assets and 100,000 reservations in the database | p95 < 300 ms server time |
| NFR-PERF-002 | Availability for a job with 200 lines (batched) | p95 < 800 ms |
| NFR-PERF-003 | Global search (FULLTEXT) | p95 < 150 ms |
| NFR-PERF-004 | Scan processing round-trip on LAN | p95 < 250 ms (server < 100 ms) |
| NFR-PERF-005 | Server-rendered page loads (list pages, 50 rows) | p95 < 500 ms TTFB on shared hosting |
| NFR-PERF-006 | Template live preview | < 500 ms for 300-line jobs |
| NFR-PERF-007 | Report run (100k source rows, aggregated) | < 3 s; UI shows spinner and is cancellable |
| NFR-PERF-008 | Frontend initial JS (gzip) | < 300 kB; module pages code-split |

### 6.2 Accessibility

- NFR-A11Y-001 WCAG 2.2 AA: contrast ≥ 4.5:1, visible focus indicators (2 px outline + offset) on every interactive element, focus not obscured (2.4.11), target size minimum (2.5.8).
- NFR-A11Y-002 Full keyboard operability; dialogs trap focus and restore it on close; skip-to-content link.
- NFR-A11Y-003 Status changes (scan results, toasts) announced via `aria-live` regions; colour never the sole signal (icons + text).
- NFR-A11Y-004 Respect `prefers-reduced-motion` and `prefers-color-scheme`.

### 6.3 Security

- NFR-SEC-001 OWASP ASVS L2 alignment: CSRF on all state-changing web routes, session fixation protection, secure/HttpOnly/SameSite=Lax cookies, HTTPS required in production.
- NFR-SEC-002 Authorization enforced server-side on every route (`can:` middleware / `Gate::authorize`).
- NFR-SEC-003 User-authored templates cannot execute PHP/JS on the server; previews render in `<iframe sandbox>` without `allow-scripts`; rich text is sanitized.
- NFR-SEC-004 Report builder only uses whitelisted columns and bound parameters — no raw SQL from users.
- NFR-SEC-005 Secrets (TOTP, recovery codes) encrypted with `APP_KEY`; passwords hashed with bcrypt/argon2id.
- NFR-SEC-006 Login throttling and lockout; passkey/2FA endpoints rate limited.
- NFR-SEC-007 Uploads: MIME/extension allow-list, size limits, stored outside `public/` (served via authorized controllers); update zips are zip-slip checked.
- NFR-SEC-008 Audit trail for all business data changes and security events.
- NFR-SEC-009 Setup wizard permanently disabled after install (404).

### 6.4 Hosting & operability

- NFR-OPS-001 Runs on cPanel shared hosting with PHP 8.3 and MySQL 8; no Docker, no Node.js, no SSH required.
- NFR-OPS-002 Background work via the database queue processed by the scheduler (`queue:work --stop-when-empty`), triggered by one cron entry or the HTTP cron endpoint.
- NFR-OPS-003 All maintenance tasks (migrate, backup, restore, cache, update, logs) available in the web UI.
- NFR-OPS-004 Daily automatic backups with retention.
- NFR-OPS-005 Health endpoint `/up` for uptime monitoring.

### 6.5 Reliability & data integrity

- NFR-REL-001 Stage transitions, allocation saves and scans run in DB transactions with row locks on affected job items/assets.
- NFR-REL-002 Foreign keys enforced; soft deletes on business records.
- NFR-REL-003 Restores are validated before applying; a safety backup is taken automatically before restore/migrate/update.

### 6.6 Maintainability & quality

- NFR-MNT-001 Domain logic in pure services (AvailabilityCalculator, ScanProcessor, RuleEvaluator, TemplateRenderer, CustomFieldValidator, Totp…) with unit tests; feature tests per module.
- NFR-MNT-002 Code style enforced by Laravel Pint; TypeScript strict mode for browser enhancements; PHP feature tests cover server-rendered pages.
- NFR-MNT-003 ≥ 80 % line coverage on `app/Domain/*/Services`.

### 6.7 Compatibility

- Browsers: last 2 versions of Chrome, Edge, Firefox, Safari (macOS/iOS/iPadOS); Android Chrome.
- Passkeys: Windows Hello, Touch ID/Face ID (iCloud Keychain), Android, FIDO2 security keys.
- Scanners: any HID keyboard-wedge barcode scanner (USB or Bluetooth) with Enter suffix.

## 7. Assumptions & constraints

- A1 The host provides PHP ≥ 8.3 with `pdo_mysql`, `mbstring`, `openssl`, `zip`, `gd`, `intl`, `bcmath`, `fileinfo` and a MySQL 8 database.
- A2 Outbound SMTP is available for password resets and scheduled reports (otherwise these features are degraded but the app works).
- A3 The organization has one timezone; all timestamps are stored in UTC.
- A4 Single organization per installation (no multi-tenancy).
- A5 Scanners are configured to send Enter after each code.
- C1 No long-running daemons (shared hosting): the queue is drained per minute by the scheduler.
- C2 No Node.js on the server: production assets are prebuilt into `public/build` and shipped in the release zip.
- C3 PHP `max_execution_time` may be 30–60 s; backups and reports are chunked/streamed.
- C4 `exec`/`proc_open` may be disabled; nothing relies on shelling out (no `mysqldump`).

## 8. Current RMS workflow parity matrix

| Current RMS concept | StageTrack equivalent | Notes |
|---|---|---|
| Products / Product groups | Products / Categories (hierarchical) + Tags | |
| Serialised stock | Assets (`assets`) | asset number + serial + barcode |
| Bulk stock / Stock levels | `stock_levels` per location | maintenance/damaged/lost buckets |
| Accessories (accessory, safety, component) | Product accessories (required, optional, safety, consumable; per-unit decimal qty; auto-add) | expanded on pull sheets with ceil() |
| Stores | Locations (warehouse/room/shelf/bin/venue/vehicle tree) | |
| Members (contacts, organisations, venues) | Contacts (person/organization) + Locations type venue | |
| Opportunities | Jobs in Inquiry/Draft/Quote/Approved | |
| Orders | Jobs in Reserved → Closed | single record across lifecycle |
| Opportunity states (Enquiry, Draft, Quotation, Order) | JobStage (Inquiry, Draft, Quote, Approved, Reserved, …) | finer-grained warehouse stages |
| Provisional / Reserved quotes | Tentative vs confirmed reservations | `reservations.count_tentative` |
| Opportunity items / groups | Job items with sections | required + spare quantities |
| Item allocation | Job item assets (role active/spare) | |
| Prepare / Book out / Check in | Warehouse modes prep / checkout / return (+ pick, stage, damage, lookup) | scan-driven |
| Availability screen / Global check | Availability Explorer with drill-down sources and peak time | |
| Quarantines | Maintenance records (type damage) + asset status damaged/lost | |
| Inspections / Test & tag | Maintenance types inspection, pat_test, calibration | |
| Document layouts (Liquid) | Document templates (visual blocks or code) with safe template language | live preview |
| Discussions / Activities / Attachments | Notes (pinnable) + attachments | |
| Custom fields | Custom fields (14 types, 8 entities) | reportable typed storage |
| Reports / Exports | Report builder + CSV/XLSX/PDF + scheduled delivery + dashboard widgets | |
| Users & permission groups | Users + database-driven roles over a capability catalog | |
| System settings | Settings (general, security, warehouse, reservations) | |
| Barcode labels | Asset/location/product label PDFs (Code 128 + QR) | |
| Projects (multi-opportunity) | *v1.1* job grouping ("Season") | roadmap |
| Invoices / Xero/QuickBooks sync | Invoice document only; accounting sync *v1.2* | roadmap |
| Public API | *v1.1* personal access tokens | roadmap |
| Mobile app | Responsive web + warehouse PWA *v1.1* | roadmap |
| Crew & vehicles | *Out of scope* | |

## 9. Acceptance criteria

The v1.0 release is accepted when all of the following pass:

1. **Install**: on a fresh cPanel account, uploading the release zip and completing `/setup` yields a working login in < 10 minutes without SSH; revisiting `/setup` returns 404.
2. **Spares**: a job with *20 × Lustr + 5 spare* shows required 20 / spare 5 / pull 25 on the job page, pull sheet, availability and warehouse progress; checkout can complete when 20 are scanned, spares optional.
3. **Accessories**: Lustr with accessory "PowerCON cable ×1 required" and "safety cable ×1 safety" expands to 20 + 5 of each on the pull sheet; a 0.5-per-unit accessory with 5 required, 1 spare expands to 3 required (ceil 2.5) and 1 spare.
4. **Availability**: with 30 owned, 2 in maintenance, 1 retired, job A holding 20 from Mon–Wed and job B holding 10 Wed–Fri (A ends Wed 12:00, B starts Wed 12:00), availability for Mon–Fri is `29 total − 2 maintenance − 20 peak reserved = 7` (no false conflict at the boundary), and clicking "reserved" lists jobs A and B with quantities and dates.
5. **Tentative**: a Quote job shows its holds as tentative and does not reduce "available" unless `reservations.count_tentative` is enabled.
6. **Overbooking**: with blocking on, approving a job that would make availability negative is refused with the shortage listed; a user holding `reservations.overbooking.approve` can override, and the override is in the audit log.
7. **Stage gating**: a user without `jobs.stages.approve` cannot move Quote → Approved (403 / disabled button); disallowed transitions (e.g. Inquiry → Checked Out) are rejected.
8. **Scanning**: scanning an asset already checked out to another job produces a red error naming that job, an audible error tone and a `scan_events` row with result `error`; scanning a valid asset in pick mode shows green success and increments picked.
9. **Rules**: "Moving lights require 1 PowerCON per unit" flags a job with 10 movers and 6 PowerCONs as "missing 4"; "Add missing" adds 4.
10. **Documents**: a template using `{% for item in items %}{{ item.name }} {{ item.pull_quantity }}{% endfor %}` and `{{ sum(items,'line_total') | currency }}` previews live and exports a PDF; a template containing `<?php` or `{{ system('id') }}` renders it as text / errors safely without execution.
11. **Reports**: a grouped report of jobs by stage with count exports identically to CSV and XLSX (XLSX opens in Excel/LibreOffice); a report restricted to role X is invisible to users without X.
12. **Security**: 5 wrong passwords lock the account for 15 minutes; TOTP and recovery code login work; a passkey can be registered and used to sign in; revoking "other sessions" logs out a second browser.
13. **Administration**: create backup → modify data → restore → data matches the backup; pending migration shows in Migrations and runs from the UI; uploading a zip containing `../evil.php` is rejected.
14. **Search**: typing an exact asset barcode in ⌘K returns that asset first in < 150 ms.
15. **Accessibility**: axe-core reports no serious/critical violations on dashboard, jobs, job detail, availability, warehouse scan, and template designer pages; all flows completable by keyboard.
16. **Quality**: `php artisan test`, `npm run typecheck`, and `npm run build` succeed in CI.
