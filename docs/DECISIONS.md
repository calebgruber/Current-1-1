# Architecture Decision Records

Short ADRs for the decisions that shape StageTrack Enterprise. Format: *Context → Decision → Consequences*. Check each ADR's status; some original choices have been superseded.

---

## ADR-001 — Laravel + Inertia.js monolith

**Status: Superseded by ADR-016.**

**Context.** The product must be installable on cPanel shared hosting by non-developers, yet feel like a modern SPA (Linear/Notion-grade UX). A separate API + SPA doubles the surface area (auth, CORS, versioning, two deployables).

**Decision.** A single Laravel 12 application rendering Vue 3 + TypeScript pages through Inertia.js 2. Controllers return `Inertia::render('{Module}/{Page}', $props)`; a small set of JSON endpoints serves interactive widgets (search, scan, availability, preview, report run, passkeys). Ziggy exposes named routes to the frontend.

**Consequences.** One deployable, one auth/session system (cookie + CSRF), server-side authorization on every page, no API versioning burden in v1. A public token API is a later, additive concern (see ROADMAP). Frontend must be prebuilt (`public/build`) because shared hosts lack Node.

## ADR-002 — Domain modules under `app/Domain/{Module}`

**Context.** ~15 functional areas with different owners; Laravel's default `app/Http`, `app/Models` layout scatters a feature across the tree.

**Decision.** Each module owns `Models`, `Services`, `Http/Controllers`, `Http/Requests`, `Policies`, `DTOs`, `Enums`, `Console`, and a `routes.php`. `App\Providers\ModuleServiceProvider` auto-registers every `app/Domain/*/routes.php` inside the `web` middleware group and every class in `app/Domain/*/Console` as an Artisan command. Pages live in `resources/views`, tests in `tests/{Unit,Feature}/{Module}`.

**Consequences.** Adding a module needs no provider edits. Cross-module calls go through services (not controllers). `app/Models/User.php` stays at the Laravel default location for framework compatibility. Route caching (`route:cache`) still works because module routes are plain route files.

## ADR-003 — `production_jobs` / `ProductionJob` naming

**Context.** Laravel's database queue uses a `jobs` table and the `Job` concept; the business domain calls productions "jobs".

**Decision.** The table is `production_jobs`, the model `App\Domain\Jobs\Models\ProductionJob`, the morph alias `job`, and foreign keys stay `job_id`. UI copy says "Job".

**Consequences.** No collision with queue tables or `Illuminate\Contracts\Queue\Job`; foreign keys and URLs remain natural (`/jobs/{job}`).

## ADR-004 — Reservations are the single source of availability holds

**Context.** Holds come from jobs at various stages and from standalone bookings (class use, loans). Computing availability from several tables with different semantics is error-prone and slow.

**Decision.** Every hold is a row in `reservations` (`tentative` | `confirmed` | `released`). Job allocations are projected into reservations by `ReservationService` whenever allocations, dates or stage change (`JobStage::reservationStatus()`), one reservation per job line, with `quantity` = required and `spare_quantity` = spare. Checked-out quantities are derived from job items/assets in warehouse stages.

**Consequences.** Availability queries hit one indexed table (`product_id, starts_at, ends_at`). Standalone and job holds share conflict detection. Projection must be kept in sync transactionally — covered by feature tests.

## ADR-005 — Pure domain services for core logic

**Context.** Availability, scanning, rules, templates and custom-field validation are where bugs are costly; they must be exhaustively testable.

**Decision.** These are implemented as **pure** PHP classes with no database or framework I/O: `AvailabilityCalculator`, `ScanProcessor`, `RuleEvaluator`, `TemplateRenderer`/`ExpressionParser`, `BlockCompiler`, `CustomFieldValidator`, `Totp`. Thin "service" wrappers (`AvailabilityService`, `ConflictDetector`, `JobValidationService`, …) load data, call the pure core and persist effects.

**Consequences.** Fast, deterministic unit tests (no DB) for edge cases like back-to-back bookings and ceil() accessory math; the same core runs in previews, conflict checks and reports.

## ADR-006 — Own TOTP implementation

**Context.** 2FA is required. Packages like Fortify bring their own auth flows/routes that conflict with our username-or-email login and security-policy middleware; other TOTP libs add dependencies for ~100 lines of code.

**Decision.** Implement RFC 6238 (HMAC-SHA1, 30 s, 6 digits, ±1 step window, base32 secrets) in `App\Domain\Authentication\Services`. `Totp` (code/verify/provisioningUri, base32) and `RecoveryCodes` (10 codes, stored hashed) live there; secrets and the recovery-code hash list use Laravel's `encrypted` casts; QR codes via `bacon/bacon-qr-code`. Replay protection by remembering the last accepted time-step per user.

**Consequences.** Full control over UX and policy; small, reviewed code with RFC test vectors as unit tests.

## ADR-007 — Passkeys via the official `laravel/passkeys` package

**Context.** WebAuthn is complex and security-critical (attestation, assertion, counters, origins).

**Decision.** Use `laravel/passkeys` (server) and `@laravel/passkeys` (browser). The `User` model implements `PasskeyUser` with `PasskeyAuthenticatable`. `Passkeys::authorizeLoginUsing` in `AppServiceProvider` enforces `is_active` and `security.allow_passkeys`, updates login metadata and writes `login.passkey` audit events.

**Consequences.** Package routes (`/passkeys/login/options`, `/passkeys/login`, `/user/passkeys…`) are used as-is; verified passkey sign-in satisfies the "2FA required" policy. Enrollment alone does not: each session must have verified its own passkey or TOTP/recovery code. Existing sessions without proof, including sessions restored by remember-me, must sign in again. If an eligible factor already exists, security changes are blocked until it is verified; first-time enrollment remains available.

## ADR-008 — A safe template language for user-authored documents

**Context.** Administrators design quotes, pull sheets and invoices. Blade/Twig compile to PHP; giving users Blade is remote code execution, and Twig sandboxing is a large dependency with a history of escapes.

**Decision.** A purpose-built, non-Turing-complete language parsed by `TemplateRenderer`/`ExpressionParser`: `{{ expr | filter }}`, `{% if/elseif/else/endif %}`, `{% for x in list %}…{% endfor %}` (`loop.index/first/last`), arithmetic, comparisons, `and/or/not`, whitelisted functions (`sum`, `count`, `round`, `min`, `max`) and filters (`currency`, `number`, `date`, `upper`, `lower`, `default`, `nl2br`, `raw`). Auto-escaping by default; `raw` only on admin-controlled templates; data is plain arrays (no object method calls); no `eval`. Iteration and output size are bounded.

**Consequences.** Safe to expose to non-developers; behaviour is fully unit-testable; feature set is deliberately smaller than Liquid/Twig (documented in MODULES.md).

## ADR-009 — Sandboxed iframe previews

**Context.** Live preview must show exactly what dompdf will render, including CSS, without letting template HTML affect the app (CSS bleed, scripts, forms).

**Decision.** The designer POSTs the draft (debounced) to the preview endpoint; the returned HTML is shown in `<iframe sandbox srcdoc>` (no `allow-scripts`, no `allow-same-origin`). Rich-text and `html` blocks are sanitized server-side (`HtmlSanitizer`).

**Consequences.** Zero script execution and no access to the app's cookies/DOM from previews; CSS isolation for free.

## ADR-010 — Dependency-free XLSX writer

**Context.** PhpSpreadsheet is large, memory-hungry on shared hosting and pulls many dependencies.

**Decision.** A minimal Office Open XML writer (workbook, one sheet, shared strings, number/date cell styles) built on `ZipArchive`, streaming rows.

**Consequences.** Small footprint, low memory; no formulas/charts in exported XLSX (charts are in the PDF/UI).

## ADR-011 — `search_index` table instead of Laravel Scout

**Context.** Scout drivers (Meilisearch, Algolia, Typesense) need external services unavailable on shared hosting; Scout's database driver searches each model table separately.

**Decision.** One denormalised `search_index` table maintained by the `Searchable` trait (`toSearchEntry()`), with MySQL `FULLTEXT(title, subtitle, keywords, codes)` and a `LIKE` fallback on SQLite. Exact matches against `codes` (barcodes, serials, asset/job numbers) rank first.

**Consequences.** One query powers the ⌘K palette across all entity types, permission-filtered by `entity_type`. A rebuild command re-indexes everything after imports.

## ADR-012 — No Docker; cPanel-first operations

**Context.** Target organizations (schools, PACs) often only have shared hosting and no DevOps staff.

**Decision.** Everything required to run and maintain the app is achievable in a browser: setup wizard, web migrations, PHP-native backups (PDO dump + ZipArchive, no `mysqldump`), web updates, log viewer, HTTP cron endpoint; database queue drained by the scheduler (`queue:work --stop-when-empty`); file cache/sessions before install; prebuilt assets.

**Consequences.** Some operations are bounded by PHP time limits and are chunked. Containers remain possible but are not a supported deliverable.

## ADR-013 — Database-driven permissions over a capability catalog

**Context.** Each institution structures staff differently (students, faculty, shop heads, freelancers). Hard-coded roles never fit.

**Decision.** `config/permissions.php` is a *catalog* of `module.resource.action` capabilities synced into `permissions`. Roles are rows created by administrators; `Gate::before` resolves any 3-segment ability against the user's role permissions; `users.is_super_admin` bypasses. No role names appear in code.

**Consequences.** Unlimited custom roles; new features add catalog keys and a sync. Code must always check capabilities, never role names.

## ADR-014 — Tentative vs confirmed holds

**Context.** Current RMS distinguishes provisional quotes from firm orders; theatres often pencil in equipment months ahead.

**Decision.** Quote/Approved create *tentative* reservations that are displayed but not deducted (unless `reservations.count_tentative`); Reserved→Returned create *confirmed* reservations; Closed/Archived release.

**Consequences.** Planners see contention early without blocking firm bookings; the setting allows stricter shops to count pencils.

## ADR-015 — Required vs spare quantities

**Context.** Theatre shops always pull spares ("20 Lustr + 5 spare"); conflating spares with required quantity distorts quotes, rules and checkout completion.

**Decision.** `job_items.required_quantity` and `spare_quantity` are separate; `pull_quantity` = sum. Both are held in reservations; rules choose a `basis` (pull or required); checkout completeness uses required only.

**Consequences.** Every surface (pull sheets, availability, reports, warehouse) carries three numbers consistently.

## ADR-016 — Blade, Livewire, and TallStackUI interface

**Context.** Vue/Inertia pages duplicated the Laravel application boundary and the navigation made workflows difficult to scan. The requested UI direction is a consistent TallStackUI system with discoverable modules and configurable terminology.

**Decision.** Use Laravel Blade with TallStackUI components and Livewire for interactive server-driven views. Keep named Laravel routes and JSON endpoints for operations that need them. The shared authenticated shell uses permission-aware, color-coded module tabs instead of a sidebar; deployment settings control organization defaults, workflow switches, and module labels.

**Consequences.** There is one server-rendered UI and authorization boundary. Blade templates remain the source of truth for page markup; browser JavaScript is limited to focused interactions such as passkey flows and inline widgets.
