# HTTP API Specification

The current interface is a Laravel monolith using Blade, Livewire, and TallStackUI. This file is an earlier API design document, not a verified inventory of the current HTTP routes; use `php artisan route:list` and the module route files for integration.

- **Page endpoints** render server-side Blade views and use normal HTML form submissions/redirects.
- **JSON endpoints** serve lookups and interactive operations such as scanning, previews, reports and passkeys.

> **Implementation status.** Route examples below remain an unverified design specification. Module routes live in `app/Domain/{Module}/routes.php` and are loaded by `ModuleServiceProvider` inside the `web` middleware group. Check those files and `php artisan route:list` before integrating. Known differences include:
>
> - Setup requirements uses `POST`, not `GET`; `/setup/database/test` and `/setup/application` exist, while `/setup/organization` does not.
> - The search page route is named `search.index`. Warehouse station and display paths differ from the design below, and scan-event undo routes are not implemented.
> - Custom-field input endpoints are `/custom-fields/lookup`, `/custom-fields/upload` and `/custom-fields/download`; the settings UI is available under `/settings/general`. Route details in this design file are not authoritative.
>
> Other differences may remain; request and response examples below have not been comprehensively verified against the implementation.

## Contents

- [Conventions](#conventions)
- [Page rendering](#page-rendering)
- [System & setup](#system--setup)
- [Authentication & account](#authentication--account)
- [Passkeys (laravel/passkeys)](#passkeys-laravelpasskeys)
- [Dashboard & search](#dashboard--search)
- [Inventory](#inventory) · [Assets](#assets) · [Contacts](#contacts)
- [Jobs](#jobs) · [Reservations](#reservations) · [Warehouse](#warehouse) · [Maintenance](#maintenance)
- [Rules](#rules) · [Documents](#documents) · [Reporting](#reporting) · [Custom fields](#custom-fields) · [Settings](#settings)
- [Administration](#administration)
- [Error format](#error-format)

## Conventions

| Topic | Convention |
|---|---|
| Middleware | Module routes run in the `web` group with session/CSRF and `EnforceSecurityPolicy`. All routes except guest/setup/cron also use `auth`. |
| Authentication | Session cookie. Every non-GET request needs Laravel's CSRF token; Blade forms include it with `@csrf`. |
| Authorization | The **Permission** column lists the catalog key checked with `can:` middleware or `$this->authorize()`. Super admins pass every check. A failed check returns `403`. |
| Route names | `{module}.{resource}.{action}` in Laravel style (`index`, `create`, `store`, `show`, `edit`, `update`, `destroy`). Blade uses Laravel's `route()` helper. |
| Mutations | Form submissions redirect (`303`) back or to the resource and set a flash message (`flash.success` / `flash.error` / `flash.warning`). JSON endpoints return `200`/`201`/`422`. |
| Validation | HTML forms redirect back with session errors; JSON endpoints use Laravel validation responses. |
| Pagination | List pages take `?page=`, `?per_page=` (≤100), `?search=`, `?sort=field` / `?sort=-field` and `?filter[key]=value`. They return a Laravel paginator `{data, links, meta}`. |
| Dates | ISO 8601 strings in the organization's timezone (`general.timezone`). |
| Custom fields | Forms accept `custom_fields: {key: value}`. Pages receive `custom_field_definitions` and `custom_fields`. |

## Page rendering

Authenticated pages use Blade layouts and TallStackUI components. Permission-aware navigation and controls use Laravel's `@can` directives; the route and controller remain authoritative for access checks. Livewire powers interactive pages/widgets where server-side state is useful.

## System & setup

| Method | Path | Name | Access | Description |
|---|---|---|---|---|
| GET | `/up` | — | public | Laravel health check. Returns `200` when the app boots. |
| GET | `/setup` | `setup.index` | before install only | Wizard (`Setup/Wizard`); props `{step, requirements}` |
| GET | `/setup/requirements` | `setup.requirements` | before install | JSON requirements report |
| POST | `/setup/database` | `setup.database` | before install | Test the connection and write `.env` |
| POST | `/setup/install` | `setup.install` | before install | Migrate, sync the permission catalog, write default settings |
| POST | `/setup/admin` | `setup.admin` | before install | Create the super admin |
| POST | `/setup/organization` | `setup.organization` | before install | Organization settings |
| POST | `/setup/finish` | `setup.finish` | before install | Write `storage/app/installed.lock` |
| GET | `/cron/{token}` | `cron.run` | token = `STAGETRACK_CRON_TOKEN` | Runs `schedule:run`. Returns `204`, or `404` if the token is wrong or unset. |

`/setup*` returns `404` once the app is installed. Until then, every other route redirects to `/setup`.

`POST /setup/database` example:

```json
// request
{ "host": "localhost", "port": 3306, "database": "acct_stagetrack", "username": "acct_st", "password": "••••" }
// 200
{ "ok": true, "server_version": "8.0.36", "message": "Connected. Configuration saved." }
// 422
{ "ok": false, "message": "SQLSTATE[HY000] [1045] Access denied for user 'acct_st'@'localhost'" }
```

`GET /setup/requirements` response:

```json
{
  "ok": false,
  "php": { "required": "8.3.0", "current": "8.3.9", "ok": true },
  "extensions": [ { "name": "pdo_mysql", "ok": true }, { "name": "intl", "ok": false } ],
  "writable": [ { "path": "storage", "ok": true }, { "path": "bootstrap/cache", "ok": true }, { "path": ".env", "ok": true } ]
}
```

## Authentication & account

| Method | Path | Name | Access | Description |
|---|---|---|---|---|
| GET | `/login` | `login` | guest | `Auth/Login`. Props `{passkeys_enabled, status}` |
| POST | `/login` | `login.store` | guest, throttled | `{login, password, remember}`. `login` can be a username or an email. Redirects to `/two-factor-challenge` when TOTP is enabled. |
| POST | `/logout` | `logout` | auth | Ends the session |
| GET | `/two-factor-challenge` | `two-factor.challenge` | pending 2FA | `Auth/TwoFactorChallenge` |
| POST | `/two-factor-challenge` | `two-factor.verify` | pending 2FA, throttled | `{code}` or `{recovery_code}` |
| GET | `/forgot-password` | `password.request` | guest | `Auth/ForgotPassword` |
| POST | `/forgot-password` | `password.email` | guest, throttled | `{email}` |
| GET | `/reset-password/{token}` | `password.reset` | guest | `Auth/ResetPassword` |
| POST | `/reset-password` | `password.update` | guest | `{token, email, password, password_confirmation}` |
| GET | `/confirm-password` | `password.confirm` | auth | `Auth/ConfirmPassword`. Required before passkey registration and 2FA changes. |
| POST | `/confirm-password` | `password.confirm.store` | auth | `{password}` |
| GET | `/account` | `account.profile` | auth | `Account/Profile` |
| PUT | `/account` | `account.profile.update` | auth | `{name, username, email, preferences}` |
| PUT | `/account/password` | `account.password.update` | auth | `{current_password, password, password_confirmation}`. The policy comes from `SecurityPolicy::passwordRule()`. |
| GET | `/account/security` | `account.security` | auth | `Account/Security`. Props `{two_factor:{enabled, confirmed_at, recovery_codes_remaining}, passkeys:[{id,name,last_used_at,created_at}], sessions:[...], policy:{require_two_factor, password_expired}}` |
| POST | `/account/two-factor` | `account.security.two-factor.enable` | auth + password.confirm | Starts enrolment. Returns `{secret, otpauth_url, qr_svg}`. |
| POST | `/account/two-factor/confirm` | `account.security.two-factor.confirm` | auth | `{code}`. Returns `{recovery_codes: [10 codes like "K7QM-2XPA-9RTD"]}` once (stored hashed). |
| POST | `/account/two-factor/recovery-codes` | `account.security.two-factor.recovery` | auth + password.confirm | Regenerates the codes |
| DELETE | `/account/two-factor` | `account.security.two-factor.disable` | auth + password.confirm | Blocked when 2FA is mandatory for the user |
| GET | `/account/sessions` | `account.security.sessions` | auth | JSON list (also shown on the Security page) |
| DELETE | `/account/sessions/{id}` | `account.security.sessions.destroy` | auth + password.confirm | Revokes one session |
| DELETE | `/account/sessions` | `account.security.sessions.destroy-others` | auth + password.confirm | Signs out all other sessions |

`EnforceSecurityPolicy` requires a verified second factor in the current session when 2FA is required, not just an enrolled factor. A verified passkey login or password plus TOTP/recovery code satisfies the policy. Without a usable factor, security setup remains available; once a factor exists, an unverified session can view security settings or sign out but cannot replace factors or access the rest of the application. Such sessions must sign in again using a factor. JSON requests receive a 403 instead of a redirect. Password expiry is enforced separately.

Sessions response:

```json
{ "data": [
  { "id": "Yx8…", "ip_address": "10.0.4.17", "agent": { "browser": "Safari", "platform": "macOS", "device": "desktop" },
    "last_active_at": "2026-03-02T14:05:11-05:00", "is_current": true }
] }
```

## Passkeys (laravel/passkeys)

These routes are registered by the `laravel/passkeys` package and used unchanged. The browser side uses `@laravel/passkeys`.

| Method | Path | Name | Middleware | Description |
|---|---|---|---|---|
| GET | `/passkeys/login/options` | `passkey.login-options` | guest | WebAuthn assertion options (challenge) |
| POST | `/passkeys/login` | `passkey.login` | guest | Verifies the assertion and logs in |
| GET | `/passkeys/confirm/options` | `passkey.confirm-options` | auth | Options for confirming with a passkey instead of a password |
| POST | `/passkeys/confirm` | `passkey.confirm` | auth | Confirms the password-confirmation step |
| GET | `/user/passkeys/options` | `passkey.registration-options` | auth, password.confirm | WebAuthn creation options |
| POST | `/user/passkeys` | `passkey.store` | auth, password.confirm | Stores a new credential `{name, credential}` |
| DELETE | `/user/passkeys/{passkey}` | `passkey.destroy` | auth, password.confirm | Removes a credential |

`Passkeys::authorizeLoginUsing` (AppServiceProvider) rejects inactive users, and rejects everyone when `security.allow_passkeys` is off. On success it updates the login timestamp and IP and writes the `login.passkey` audit event.

```json
// GET /passkeys/login/options → 200
{ "options": { "challenge": "q0bN0…", "rpId": "stagetrack.example.edu", "timeout": 60000,
  "userVerification": "required", "allowCredentials": [] } }

// POST /passkeys/login
{ "credential": { "id": "AXk…", "rawId": "AXk…", "type": "public-key",
  "response": { "clientDataJSON": "…", "authenticatorData": "…", "signature": "…", "userHandle": "…" } },
  "remember": false }
// → 200 { "redirect": "/" }      422 { "message": "This passkey is not valid." }
```

## Dashboard & search

| Method | Path | Name | Permission | Description |
|---|---|---|---|---|
| GET | `/` | `dashboard` | auth | `Dashboard` (routes/web.php). Props `{widgets:[{report_id, title, chart, data}], upcoming_jobs, overdue_returns, shortages, maintenance_due}`. Widgets are reports with `show_on_dashboard` that are visible to the user (needs `reporting.dashboard.view`). |
| GET | `/search` | `search` | auth | JSON for the ⌘K palette |

`GET /search?q=LX-0042[&types[]=asset&limit=30]` → `SearchService::search()`. Results only include entity types whose view permission the user has (`SearchService::TYPES`: product, asset, job, reservation, contact, maintenance, document, report, location). Exact code matches come first. `limit` is capped at 100; the palette shows `stagetrack.search.limit` (8) per group.

```json
{
  "query": "LX-0042",
  "results": [
    { "type": "asset", "type_label": "Assets", "id": 314, "title": "LX-0042 · ETC Source Four 26°",
      "subtitle": "Main Store › Bay 3 · available", "url": "/assets/314", "exact": true, "icon": "tag" },
    { "type": "job", "type_label": "Jobs", "id": 12, "title": "J-00042 · Spring Musical",
      "subtitle": "Reserved · Mar 3 – Mar 22", "url": "/jobs/12", "exact": false, "icon": "briefcase" }
  ],
  "commands": [ { "id": "new-job", "title": "New job", "url": "/jobs/create", "shortcut": "n" } ]
}
```

## Inventory

| Method | Path | Name | Permission | Page / response |
|---|---|---|---|---|
| GET | `/inventory/products` | `inventory.products.index` | `inventory.products.view` | `Inventory/Index`. Props `{products: paginator, filters, categories, tags}` |
| GET | `/inventory/products/create` | `inventory.products.create` | `inventory.products.create` | `Inventory/Form` |
| POST | `/inventory/products` | `inventory.products.store` | `inventory.products.create` | Body below |
| GET | `/inventory/products/{product}` | `inventory.products.show` | `inventory.products.view` | `Inventory/Show`. Props `{product, accessories, assets, stock_levels, availability, upcoming, custom_fields}` |
| GET | `/inventory/products/{product}/edit` | `inventory.products.edit` | `inventory.products.edit` | `Inventory/Form` |
| PUT | `/inventory/products/{product}` | `inventory.products.update` | `inventory.products.edit` | |
| PATCH | `/inventory/products/bulk` | `inventory.products.bulk` | `inventory.products.edit` | `{product_ids: [1, …], changes: {category_id?, is_active?}}`; 1–100 distinct, non-deleted products. `category_id: null` explicitly clears the category. Only these fields are accepted; all changes and per-product audits are transactional. |
| DELETE | `/inventory/products/{product}` | `inventory.products.destroy` | `inventory.products.delete` | Soft delete |
| POST | `/inventory/products/{product}/images` | `inventory.products.images.store` | `inventory.products.edit` | multipart `image` |
| DELETE | `/inventory/products/{product}/images/{image}` | `inventory.products.images.destroy` | `inventory.products.edit` | |
| PUT | `/inventory/products/{product}/accessories` | `inventory.products.accessories.update` | `inventory.products.edit` | `{accessories:[{accessory_id, quantity, type, auto_add, sort_order}]}` |
| PUT | `/inventory/products/{product}/stock` | `inventory.products.stock.update` | `inventory.stock.edit` | Bulk only: `{location_id, quantity, maintenance, damaged, lost, reason}` |
| GET | `/inventory/products/export` | `inventory.products.export` | `inventory.products.export` | CSV download |
| GET | `/inventory/availability` | `inventory.availability.index` | `inventory.availability.view` | `Inventory/Availability` (Explorer) |
| GET | `/inventory/availability/data` | `inventory.availability.data` | `inventory.availability.view` | JSON (below) |
| GET | `/inventory/products/{product}/availability` | `inventory.products.availability` | `inventory.availability.view` | JSON breakdown + timeline |
| GET | `/inventory/categories` | `inventory.categories.index` | `inventory.categories.view` | `Inventory/Categories` (tree) |
| POST/PUT/DELETE | `/inventory/categories[/{category}]` | `inventory.categories.store/update/destroy` | `inventory.categories.create/edit/delete` | `{name, parent_id, color, icon, sort_order}` |
| PUT | `/inventory/categories/move` | `inventory.categories.move` | `inventory.categories.edit` | `{id, parent_id, before_id, version}`; null parent means top level and null before means append. Uses the `treeVersion` from the category page; stale requests and cycles are rejected. |
| GET | `/inventory/manufacturers` | `inventory.manufacturers.index` | `inventory.categories.view` | `Inventory/Manufacturers` |
| POST/PUT/DELETE | `/inventory/manufacturers[/{manufacturer}]` | `inventory.manufacturers.*` | `inventory.categories.*` | |
| GET | `/inventory/locations` | `inventory.locations.index` | `inventory.locations.view` | `Inventory/Locations` (tree) |
| GET | `/inventory/locations/{location}` | `inventory.locations.show` | `inventory.locations.view` | Contents & stock |
| POST/PUT/DELETE | `/inventory/locations[/{location}]` | `inventory.locations.store/update/destroy` | `inventory.locations.create/edit/delete` | |

Product store/update body:

```json
{
  "name": "ETC Source Four LED Series 3 Lustr", "sku": "LX-S4L3", "barcode": "0840123400012",
  "tracking": "serialized", "category_id": 4, "manufacturer_id": 2, "model": "S4L3-LUSTR",
  "description": "…", "replacement_cost": 2850.00, "rental_price": 45.00, "weight_kg": 7.1,
  "default_location_id": 3, "tags": ["moving-light", "powercon"], "is_active": true,
  "custom_fields": { "dmx_footprint": 8, "lamp_type": "LED" }
}
```

`GET /inventory/availability/data?product_ids[]=7&product_ids[]=9&from=2026-03-03T08:00&to=2026-03-22T23:00&category_id=&location_id=`:

```json
{
  "window": { "from": "2026-03-03T08:00:00-05:00", "to": "2026-03-22T23:00:00-04:00" },
  "count_tentative": false,
  "data": [
    {
      "product": { "id": 7, "name": "Source Four LED Lustr", "tracking": "serialized", "url": "/inventory/products/7" },
      "owned": 26, "total": 24, "reserved": 20, "checked_out": 0, "tentative": 6,
      "maintenance": 1, "damaged": 1, "lost": 0, "retired": 2,
      "available": 2, "shortage": 0, "peak_at": "2026-03-10T09:00:00-04:00",
      "sources": {
        "reserved": [
          { "type": "job", "id": 12, "label": "J-00042 Spring Musical (20 + 5 spare)", "quantity": 20,
            "url": "/jobs/12", "starts_at": "2026-03-03T08:00:00-05:00", "ends_at": "2026-03-22T23:00:00-04:00" }
        ],
        "tentative": [
          { "type": "job", "id": 15, "label": "J-00045 Dance Showcase (quote)", "quantity": 6,
            "url": "/jobs/15", "starts_at": "2026-03-09T08:00:00-04:00", "ends_at": "2026-03-12T23:00:00-04:00" }
        ],
        "maintenance": [
          { "type": "maintenance", "id": 33, "label": "MR-00033 Fan noise", "quantity": 1, "url": "/maintenance/33", "starts_at": null, "ends_at": null }
        ],
        "damaged": [
          { "type": "asset", "id": 318, "label": "LX-0046", "quantity": 1, "url": "/assets/318", "starts_at": null, "ends_at": null }
        ]
      }
    }
  ]
}
```

`GET /inventory/products/{product}/availability?from=…&days=30` returns `{breakdown: {...as above}, timeline: [{date, available, reserved, checked_out, tentative}], locations: [{location_id, name, path, quantity, maintenance, damaged, lost}]}` (`AvailabilityService::forProduct`, `timeline`, `locations`).

## Assets

| Method | Path | Name | Permission | Page / response |
|---|---|---|---|---|
| GET | `/assets` | `assets.index` | `assets.assets.view` | `Assets/Index` |
| GET | `/assets/create` | `assets.create` | `assets.assets.create` | `Assets/Form` |
| POST | `/assets` | `assets.store` | `assets.assets.create` | `{product_id, asset_number?, serial_number, barcode, location_id, condition, purchased_at, purchase_cost, warranty_expires_at, notes, custom_fields}`. Bulk create: `{product_id, count, prefix, start_number, location_id}` |
| GET | `/assets/{asset}` | `assets.show` | `assets.assets.view` | `Assets/Show`. Props `{asset, status_history, condition_reports, assignments, maintenance, scan_events, custom_fields}` |
| POST | `/assets/{asset}/condition-reports` | `assets.condition-reports.store` | `assets.assets.edit` | `{condition, notes, photos[]}` |
| GET | `/assets/{asset}/edit` | `assets.edit` | `assets.assets.edit` | `Assets/Form` |
| PUT | `/assets/{asset}` | `assets.update` | `assets.assets.edit` | |
| DELETE | `/assets/{asset}` | `assets.destroy` | `assets.assets.delete` | Soft delete |
| PUT | `/assets/{asset}/status` | `assets.status.update` | `assets.status.edit` (`assets.status.approve` to set `lost`/`retired`) | `{status, reason}` |
| GET | `/assets/labels` | `assets.labels.index` | `assets.labels.view` | `Assets/Labels` |
| POST | `/assets/labels` | `assets.labels.print` | `assets.labels.export` | `{asset_ids[], format: "barcode" \| "qr", size}` → PDF |
| GET | `/assets/export` | `assets.export` | `assets.assets.export` | CSV |

## Contacts

| Method | Path | Name | Permission |
|---|---|---|---|
| GET | `/contacts` | `contacts.index` | `contacts.contacts.view` → `Contacts/Index` |
| GET | `/contacts/create` · POST `/contacts` | `contacts.create` · `contacts.store` | `contacts.contacts.create` |
| GET | `/contacts/{contact}` | `contacts.show` | `contacts.contacts.view` → `Contacts/Show` (jobs, documents) |
| GET | `/contacts/{contact}/edit` · PUT `/contacts/{contact}` | `contacts.edit` · `contacts.update` | `contacts.contacts.edit` |
| DELETE | `/contacts/{contact}` | `contacts.destroy` | `contacts.contacts.delete` |
| GET | `/contacts/export` | `contacts.export` | `contacts.contacts.export` |

Body: `{type: "organization" | "person", name, organization_id?, title, email, phone, address, notes, custom_fields}`.

## Jobs

| Method | Path | Name | Permission | Page / response |
|---|---|---|---|---|
| GET | `/jobs` | `jobs.index` | `jobs.jobs.view` | `Jobs/Index`. Filters: stage, client, date range, owner |
| GET | `/jobs/calendar` | `jobs.calendar` | `jobs.jobs.view` | `Jobs/Calendar`. `?from&to` returns events |
| GET | `/jobs/create` | `jobs.create` | `jobs.jobs.create` | `Jobs/Form` |
| POST | `/jobs` | `jobs.store` | `jobs.jobs.create` | Body below |
| GET | `/jobs/{job}` | `jobs.show` | `jobs.jobs.view` | `Jobs/Show` |
| GET | `/jobs/{job}/edit` · PUT `/jobs/{job}` | `jobs.edit` · `jobs.update` | `jobs.jobs.edit` | Changing dates resyncs reservations |
| DELETE | `/jobs/{job}` | `jobs.destroy` | `jobs.jobs.delete` | Soft delete; releases reservations |
| POST | `/jobs/{job}/duplicate` | `jobs.duplicate` | `jobs.jobs.create` | Copy as Draft |
| POST | `/jobs/{job}/stage` | `jobs.stage` | `jobs.stages.edit` (+ `jobs.stages.approve` for Approved/Reserved; + `reservations.overbooking.approve` for `override`) | `{stage, notes?, override?}` |
| PUT | `/jobs/{job}/sections` | `jobs.sections.rename` | `jobs.items.edit` | `{from, to}`. Sections are a free-text `job_items.section` label, so renaming updates every matching item |
| POST | `/jobs/{job}/items` | `jobs.items.store` | `jobs.items.create` | `{product_id, required_quantity, spare_quantity, section?, notes?, unit_price?, with_accessories: true}` → `JobService::addItem` |
| PUT | `/jobs/{job}/items/{item}` | `jobs.items.update` | `jobs.items.edit` | `{required_quantity?, spare_quantity?, section?, sort_order?, notes?, unit_price?}` |
| DELETE | `/jobs/{job}/items/{item}` | `jobs.items.destroy` | `jobs.items.delete` | Also removes auto-added accessories |
| PUT | `/jobs/{job}/items/reorder` | `jobs.items.reorder` | `jobs.items.edit` | `{items:[{id, section, sort_order}]}` |
| PUT | `/jobs/{job}/items/{item}/assets` | `jobs.items.assets` | `jobs.items.edit` | `{asset_ids[]}` pre-assign serials |
| GET | `/jobs/{job}/conflicts` | `jobs.conflicts` | `jobs.jobs.view` | JSON `ConflictReport[]` |
| GET | `/jobs/{job}/validation` | `jobs.validation` | `jobs.jobs.view` | JSON `RuleFinding[]` |
| GET | `/jobs/{job}/pull-sheet` | `jobs.pull-sheet` | `jobs.jobs.view` | `Jobs/PullSheet`. Props from `JobService::pullSheet` |
| POST | `/jobs/{job}/notes` · DELETE `…/notes/{note}` | `jobs.notes.*` | `jobs.jobs.edit` | `{body}` |
| POST | `/jobs/{job}/attachments` · DELETE `…/attachments/{attachment}` | `jobs.attachments.*` | `jobs.files.create` / `jobs.files.delete` | multipart `file` |
| GET | `/jobs/export` | `jobs.export` | `jobs.jobs.export` | CSV |

Job body:

```json
{
  "name": "Spring Musical — Into the Woods", "client_contact_id": 3, "venue_location_id": 8, "owner_id": 5, "color": "#7c3aed",
  "prep_starts_at": "2026-03-01T09:00", "starts_at": "2026-03-03T08:00", "ends_at": "2026-03-21T23:00",
  "returns_at": "2026-03-22T23:00", "description": "…", "contacts": [ { "contact_id": 9, "role": "Stage manager" } ],
  "custom_fields": { "course_code": "THEA 340" }
}
```

`Jobs/Show` props (abridged):

```json
{
  "job": { "id": 12, "number": "J-00042", "name": "Spring Musical", "stage": "reserved", "stage_label": "Reserved",
           "allowed_transitions": ["approved", "prep", "archived"], "hold_starts_at": "…", "hold_ends_at": "…" },
  "sections": [ { "name": "Front of house", "items": [
     { "id": 101, "product": { "id": 7, "name": "Source Four LED Lustr" }, "required_quantity": 20, "spare_quantity": 5,
       "pull_quantity": 25, "picked_quantity": 0, "checked_out_quantity": 0, "returned_quantity": 0,
       "parent_item_id": null, "accessory_type": null, "availability": { "available": 2, "shortage": 0 } } ] } ],
  "conflicts": [], "findings": [], "stage_history": [], "documents": [], "can": { "approve": true, "edit": true }
}
```

`GET /jobs/{job}/pull-sheet` (JSON variant via `Accept: application/json`):

```json
{
  "job": { "id": 12, "number": "J-00042" },
  "lines": [
    { "product_id": 7, "product": "Source Four LED Lustr", "required": 20, "spare": 5, "pull": 25,
      "location": "Main Store › Bay 3", "assets": ["LX-0040", "LX-0041"],
      "accessories": [
        { "product_id": 31, "product": "PowerCON 10ft", "type": "required", "per_unit": 1, "required": 20, "spare": 5, "pull": 25 },
        { "product_id": 40, "product": "Safety cable", "type": "safety", "per_unit": 1, "required": 20, "spare": 5, "pull": 25 }
      ] }
  ],
  "totals": { "required": 60, "spare": 15, "pull": 75 }
}
```

Stage change errors (`422`, or a redirect with `errors.stage`):

```json
{ "message": "3 items are short for the requested dates.",
  "errors": { "stage": ["Cannot move to Reserved: Source Four LED Lustr short by 4."] },
  "conflicts": [ { "product_id": 7, "product": "Source Four LED Lustr", "requested": 25, "available": 21, "tentative": 0,
                   "shortage": 4, "level": "shortage", "message": "…", "competing": [ { "type": "job", "id": 14, "label": "J-00044", "quantity": 10 } ] } ] }
```

## Reservations

| Method | Path | Name | Permission | Page / response |
|---|---|---|---|---|
| GET | `/reservations` | `reservations.index` | `reservations.reservations.view` | `Reservations/Index` (job and standalone holds) |
| GET | `/reservations/create` | `reservations.create` | `reservations.reservations.create` | `Reservations/Form` |
| POST | `/reservations` | `reservations.store` | `reservations.reservations.create` | `{name, product_id, quantity, spare_quantity, starts_at, ends_at, status, notes, override?}` (e.g. name "THEA 101 lab — week 6") → `ReservationService::save` |
| GET | `/reservations/{reservation}` | `reservations.show` | `reservations.reservations.view` | `Reservations/Show` |
| GET/PUT | `/reservations/{reservation}/edit` · `/reservations/{reservation}` | `reservations.edit` · `reservations.update` | `reservations.reservations.edit` | Job-generated reservations are read-only (edit the job) |
| POST | `/reservations/{reservation}/confirm` | `reservations.confirm` | `reservations.reservations.approve` | tentative → confirmed |
| POST | `/reservations/{reservation}/release` | `reservations.release` | `reservations.reservations.edit` | |
| DELETE | `/reservations/{reservation}` | `reservations.destroy` | `reservations.reservations.delete` | |
| POST | `/reservations/check` | `reservations.check` | `reservations.reservations.view` | JSON `ConflictReport` |
| GET | `/reservations/export` | `reservations.export` | `reservations.reservations.export` | CSV |

```json
// POST /reservations/check
{ "product_id": 7, "quantity": 4, "starts_at": "2026-04-01T08:00", "ends_at": "2026-04-03T18:00", "ignore_id": null }
// 200
{ "product_id": 7, "product": "Source Four LED Lustr", "requested": 4, "available": 6, "tentative": 2, "shortage": 0,
  "level": "ok", "message": "4 of 6 available.", "competing": [] }
```

`level` is `ok`, `tentative` (fits only if competing quotes don't convert) or `shortage`. When `reservations.block_overbooking` is on, a `shortage` on save returns `422` unless `override: true` is sent and the user has `reservations.overbooking.approve`.

## Warehouse

| Method | Path | Name | Permission | Page / response |
|---|---|---|---|---|
| GET | `/warehouse` | `warehouse.index` | any `warehouse.*.view` | `Warehouse/Index`: jobs in warehouse-active stages, recent scans |
| GET | `/warehouse/jobs/{job}/{mode}` | `warehouse.scan.page` | `warehouse.{picking\|prep\|staging\|checkout\|returns\|damage}.view` | `Warehouse/Scan`. Props `{job, mode, modes, lines, progress, sound_enabled}` |
| GET | `/warehouse/display/{job?}` | `warehouse.display` | `warehouse.display.view` | `Warehouse/Display` (fullscreen) |
| POST | `/warehouse/scan` | `warehouse.scan` | the mode's `.edit` key; `lookup` needs any warehouse `view` | JSON (below) |
| GET | `/warehouse/jobs/{job}/progress` | `warehouse.progress` | warehouse view | JSON progress (polled by the display) |
| POST | `/warehouse/scan-events/{event}/undo` | `warehouse.scan.undo` | the mode's `.edit` key | Reverses the effects of the last scan |
| GET | `/warehouse/scan-events` | `warehouse.events` | any warehouse view | Scan log (`Warehouse/Events`) |

Mode → permission: `pick` → `warehouse.picking`, `prep` → `warehouse.prep`, `stage` → `warehouse.staging`, `checkout` → `warehouse.checkout`, `return` → `warehouse.returns`, `damage` → `warehouse.damage`.

```json
// POST /warehouse/scan
{ "mode": "pick", "code": "LX-0042", "job_id": 12, "quantity": 1, "station": "Bay 3 tablet" }

// 200 — success
{
  "result": { "level": "success", "sound": "success", "title": "Picked LX-0042",
              "message": "Source Four LED Lustr · 21 of 25 picked (20 + 5 spare)",
              "line": { "id": 101, "product": "Source Four LED Lustr", "required": 20, "spare": 5, "picked": 21 },
              "item": { "kind": "asset", "asset_id": 314, "asset_number": "LX-0042" }, "role": "spare" },
  "progress": { "required": 60, "spare": 15, "pull": 75, "done": 46, "percent": 61 },
  "event_id": 9812
}

// 200 — warning
{ "result": { "level": "warning", "sound": "warning", "title": "Already picked",
              "message": "LX-0042 was already scanned for this job.", "line": { "id": 101 }, "item": { "asset_number": "LX-0042" }, "role": null } }

// 200 — error (no effects applied; still logged in scan_events)
{ "result": { "level": "error", "sound": "error", "title": "Asset in maintenance",
              "message": "LX-0046 is in maintenance (MR-00033) and cannot be picked.", "line": null, "item": { "asset_number": "LX-0046" }, "role": null } }
```

Scan outcomes are always returned with HTTP `200`, so the scanner UI handles them in one place. `422` is reserved for malformed requests, such as an unknown mode or a missing `job_id` for a mode that needs a job.

## Maintenance

| Method | Path | Name | Permission |
|---|---|---|---|
| GET | `/maintenance` | `maintenance.index` | `maintenance.records.view` → `Maintenance/Index` (queue; filters status/priority/due) |
| GET | `/maintenance/create` · POST `/maintenance` | `maintenance.create` · `maintenance.store` | `maintenance.records.create` |
| POST | `/maintenance/asset-lookup` | `maintenance.asset-lookup` | `maintenance.records.create` |
| GET | `/maintenance/{record}` | `maintenance.show` | `maintenance.records.view` → `Maintenance/Show` |
| GET | `/maintenance/{record}/edit` · PUT `/maintenance/{record}` | `maintenance.edit` · `maintenance.update` | `maintenance.records.edit` |
| POST | `/maintenance/{record}/status` | `maintenance.status` | `maintenance.records.edit`; `maintenance.records.approve` to close or write off |
| DELETE | `/maintenance/{record}` | `maintenance.destroy` | `maintenance.records.delete` |
| GET | `/maintenance/export` | `maintenance.export` | `maintenance.records.export` |

Body: `{asset_id? | product_id + quantity, type, priority, title, description, status, due_at, assigned_to, job_id?, cost, resolution?, custom_fields}`. The `reference` (`MR-00001`) is generated.

Asset lookup accepts `{product_id, code}` and returns `{asset: {id, asset_number}}` for an exact, case-sensitive barcode, serial number or asset number within the selected serialized product. It returns 404 for no match, 409 for multiple matches, and 422 for invalid input or an asset that cannot enter maintenance. Deleted products and assets are excluded. Saving still validates the current asset state and product relationship.

## Rules

| Method | Path | Name | Permission | Page / response |
|---|---|---|---|---|
| GET | `/rules` | `rules.index` | `rules.rules.view` | `Rules/Index` |
| GET | `/rules/create` · `/rules/{rule}/edit` | `rules.create` · `rules.edit` | `rules.rules.create` / `.edit` | `Rules/Builder`. Props `{rule, checks, matches, severities, categories, tags, products}` |
| POST | `/rules` · PUT `/rules/{rule}` | `rules.store` · `rules.update` | `rules.rules.create` / `.edit` | `{name, description, severity, is_active, definition}`, validated by `RuleEvaluator::validateDefinition` |
| DELETE | `/rules/{rule}` | `rules.destroy` | `rules.rules.delete` | |
| POST | `/rules/test` | `rules.test` | `rules.rules.view` | Runs a draft definition against a job |

```json
// POST /rules/test
{ "job_id": 12, "definition": {
    "scope": { "match": "tag", "values": ["wireless-mic"] }, "check": "requires",
    "requirement": { "match": "product", "values": [55], "per": 1, "quantity": 2 },
    "basis": "pull", "message": "{items} need {required} AA batteries; found {found} ({missing} missing)" } }
// 200
{ "findings": [ { "rule_id": null, "rule": "Draft rule", "severity": "warning",
    "message": "Shure ULXD2 ×8 need 16 AA batteries; found 10 (6 missing)",
    "required": 16, "found": 10, "missing": 6, "items": ["Shure ULXD2 ×8"],
    "suggestions": [ { "product_id": 55, "product": "AA battery", "quantity": 6 } ] } ] }
```

`GET /jobs/{job}/validation` returns `{findings: RuleFinding[]}` in the same shape. Accessory-completeness findings use `rule: "Accessory completeness"`.

## Documents

| Method | Path | Name | Permission | Page / response |
|---|---|---|---|---|
| GET | `/documents` | `documents.index` | `documents.documents.view` | `Documents/Index` (generated documents) |
| GET | `/documents/{document}` | `documents.show` | `documents.documents.view` | `Documents/Show` (snapshot preview) |
| GET | `/documents/{document}/pdf` | `documents.pdf` | `documents.documents.view` (download: `.export`) | PDF |
| POST | `/documents` | `documents.store` | `documents.documents.create` | `{template_id, documentable_type: "job" \| "maintenance" \| "asset", documentable_id}` → renders, snapshots, redirects |
| DELETE | `/documents/{document}` | `documents.destroy` | `documents.documents.delete` | |
| GET | `/documents/templates` | `documents.templates.index` | `documents.templates.view` | `Documents/Templates` |
| GET | `/documents/templates/create` · `/documents/templates/{template}/edit` | `documents.templates.create` · `.edit` | `documents.templates.create` / `.edit` | `Documents/Designer`. Props `{template, types, block_types, filters, functions, sample_entities}` |
| POST | `/documents/templates` · PUT `/documents/templates/{template}` | `documents.templates.store` · `.update` | `.create` / `.edit` | Body below |
| POST | `/documents/templates/{template}/duplicate` | `documents.templates.duplicate` | `documents.templates.create` | |
| POST | `/documents/templates/{template}/default` | `documents.templates.default` | `documents.templates.admin` | Makes it the default for its type |
| DELETE | `/documents/templates/{template}` | `documents.templates.destroy` | `documents.templates.delete` | |
| POST | `/documents/templates/preview` | `documents.templates.preview` | `documents.templates.view` | JSON preview (below) |

Template body:

```json
{
  "name": "Pull sheet — Electrics", "type": "pull_sheet", "editor": "visual",
  "entity_type": "job", "paper_size": "letter", "orientation": "portrait", "watermark": "DRAFT",
  "blocks": [
    { "type": "heading", "text": "Pull sheet {{ job.number }}", "level": 1 },
    { "type": "fields", "fields": [ { "label": "Show", "value": "{{ job.name }}" }, { "label": "Load-in", "value": "{{ job.starts_at | date('D M j') }}" } ] },
    { "type": "items_table", "source": "items", "columns": ["product", "required", "spare", "pull", "location"], "show_accessories": true },
    { "type": "signature", "label": "Pulled by" }
  ],
  "html": null, "css": null
}
```

For `editor: "code"`, send `html` and `css` and leave `blocks` empty.

```json
// POST /documents/templates/preview   (debounced ~400 ms by the designer)
{ "editor": "code", "type": "invoice", "sample": { "type": "job", "id": 12 },
  "html": "<h1>Invoice {{ job.number }}</h1>{% for i in items %}<p>{{ loop.index }}. {{ i.name }} — {{ i.total | currency }}</p>{% endfor %}<strong>{{ sum(items,'total') | currency }}</strong>",
  "css": "h1{font-size:20pt}" }
// 200
{ "html": "<!doctype html><html><head><style>…</style></head><body><h1>Invoice J-00042</h1>…</body></html>", "error": null }
// 200 with template error (the designer shows it inline)
{ "html": null, "error": { "message": "Unknown filter \"curency\"", "line": 1 } }
```

The client loads the HTML into `<iframe sandbox srcdoc>` (no scripts, no same-origin access). See [ADR-009](DECISIONS.md#adr-009--sandboxed-iframe-previews).

## Reporting

| Method | Path | Name | Permission | Page / response |
|---|---|---|---|---|
| GET | `/reports` | `reports.index` | `reporting.reports.view` | `Reporting/Index`. Only reports where `Report::isVisibleTo(user)` is true |
| GET | `/reports/create` · `/reports/{report}/edit` | `reports.create` · `reports.edit` | `reporting.reports.create` / `.edit` | `Reporting/Builder`. Props `{report, sources: ReportSourceRegistry::catalogue(can, customFields), operators, aggregates, roles}` |
| POST | `/reports` · PUT `/reports/{report}` | `reports.store` · `reports.update` | `.create` / `.edit` | `{name, description, source, definition, is_shared, role_ids[]}` |
| GET | `/reports/{report}` | `reports.show` | `reporting.reports.view` + visibility | `Reporting/Show` |
| DELETE | `/reports/{report}` | `reports.destroy` | `reporting.reports.delete` | |
| POST | `/reports/run` | `reports.run` | `reporting.reports.view` | Runs a draft definition and returns JSON |
| GET | `/reports/{report}/data` | `reports.data` | view + visibility | JSON for a saved report |
| GET | `/reports/{report}/export/{format}` | `reports.export` | `reporting.reports.export` | `format` is `csv`, `xlsx` or `pdf` |
| GET | `/reports/{report}/schedules` · POST · PUT/DELETE `/report-schedules/{schedule}` | `reports.schedules.*` | `reporting.schedules.*` | `{frequency: daily \| weekly \| monthly, hour, day_of_week?, day_of_month? (1–28), format: csv \| xlsx \| pdf, recipients[], is_active}` |
| PUT | `/reports/{report}/dashboard` | `reports.dashboard` | `reporting.reports.edit` | `{show_on_dashboard: bool}`. The report then shows as a dashboard widget for users who can see it and have `reporting.dashboard.view` |

Definition contract (from `ReportRunner`):

```text
definition = {
  columns:    list<column key>                      // detail mode
  calculated: list<{key, label, expression, type}>  // per-row formulas (template expression syntax)
  filters:    list<{column, operator, value}>
  match:      'all' | 'any'
  group_by:   list<{column, bucket?: day|week|month|year}>
  aggregates: list<{fn: count|sum|avg|min|max, column?, label?}>
  sort:       list<{column, direction}>
  limit:      int (≤ 50 000)
  chart:      {type: none|bar|line|pie|doughnut|number, x?, y?}
}
```

Column keys come from `ReportSourceRegistry` (for example `job_items`: `job_number, job, job_stage, job_starts_at, product, category, section, is_accessory, required_quantity, spare_quantity, pull_quantity, picked_quantity, checked_out_quantity, returned_quantity, outstanding_quantity, unit_price, line_value`). Sources with an entity type also expose custom fields as `cf:{key}` pseudo-columns.

```json
// POST /reports/run
{
  "source": "job_items",
  "definition": {
    "filters": [ { "column": "job_stage", "operator": "in", "value": ["reserved", "prep", "ready", "checked_out"] },
                 { "column": "job_starts_at", "operator": "next_days", "value": 30 } ],
    "match": "all",
    "group_by": [ { "column": "category" } ],
    "aggregates": [ { "fn": "sum", "column": "required_quantity", "label": "Required" },
                    { "fn": "sum", "column": "spare_quantity", "label": "Spare" },
                    { "fn": "count" } ],
    "sort": [ { "column": "a0", "direction": "desc" } ],
    "limit": 100,
    "chart": { "type": "bar", "x": "category", "y": ["a0", "a1"] }
  }
}
// 200
{
  "columns": [ { "key": "g0", "label": "Category", "type": "string" },
               { "key": "a0", "label": "Required", "type": "number" },
               { "key": "a1", "label": "Spare", "type": "number" },
               { "key": "a2", "label": "Count", "type": "number" } ],
  "rows": [ { "g0": "LED Profiles", "a0": 48, "a1": 9, "a2": 6 }, { "g0": "Wireless", "a0": 16, "a1": 4, "a2": 3 } ],
  "totals": { "a0": 64, "a1": 13, "a2": 9 },
  "chart": { "type": "bar", "labels": ["LED Profiles", "Wireless"],
             "datasets": [ { "label": "Required", "data": [48, 16] }, { "label": "Spare", "data": [9, 4] } ] },
  "truncated": false,
  "grouped": true
}
// 422 — validation errors from ReportRunner::validate (whitelist)
{ "message": "The given data was invalid.", "errors": { "definition": ["Unknown column \"password\"."] } }
```

Result keys are aliases: `g{i}` for group columns, `a{i}` for aggregates and `c{i}` for detail columns. `columns[].label` carries the display name.

Filter operators (`ReportRunner::OPERATORS`): `eq, neq, gt, gte, lt, lte, contains, not_contains, starts_with, in, not_in, empty, not_empty, between, last_days, next_days, is_true, is_false`. Aggregates: `count, sum, avg, min, max`. Charts: `none, bar, line, pie, doughnut, number`. Exports use `ReportRunner::tabular()` with `Export\CsvWriter`, `Export\XlsxWriter` or a dompdf PDF.

## Custom fields

| Method | Path | Name | Permission |
|---|---|---|---|
| GET | `/settings/custom-fields` | `custom-fields.index` | `settings.custom_fields.view` → `CustomFields/Index` (grouped by entity type) |
| POST | `/settings/custom-fields` | `custom-fields.store` | `settings.custom_fields.create` |
| PUT | `/settings/custom-fields/{definition}` | `custom-fields.update` | `settings.custom_fields.edit` |
| PUT | `/settings/custom-fields/reorder` | `custom-fields.reorder` | `settings.custom_fields.edit` |
| DELETE | `/settings/custom-fields/{definition}` | `custom-fields.destroy` | `settings.custom_fields.delete` |
| POST | `/custom-fields/files` | `custom-fields.files.store` | edit permission of the owning entity | multipart upload for `file` fields; returns `{path, name, size}` |

```json
{ "entity_type": "job", "key": "course_code", "label": "Course code", "type": "dropdown", "group": "Academic",
  "options": ["THEA 101", "THEA 340", "DANC 210"], "validation": { "max": 20 }, "is_required": false,
  "is_searchable": true, "show_in_list": true, "help_text": "Course this production supports", "sort_order": 3 }
```

## Settings

| Method | Path | Name | Permission |
|---|---|---|---|
| GET | `/settings` | `settings.index` | `settings.general.view` → `Settings/Index` (tabs: General, Jobs & reservations, Warehouse, Security*) |
| PUT | `/settings/general` | `settings.general.update` | `settings.general.edit`. Takes `general.*`, `reservations.*` and `warehouse.*` keys |
| PUT | `/settings/security` | `settings.security.update` | `settings.security.edit`. Takes `security.*` keys |

\* The Security tab needs `settings.security.view`. The keys and defaults are listed in `SettingsRepository::DEFAULTS` (see [MODULES.md#settings](MODULES.md#settings)).

## Administration

| Method | Path | Name | Permission | Notes |
|---|---|---|---|---|
| GET | `/admin/users` | `admin.users.index` | `admin.users.view` | `Administration/Users/Index` |
| POST · PUT · DELETE | `/admin/users[/{user}]` | `admin.users.store/update/destroy` | `admin.users.create/edit/delete` | `{name, username, email, password?, role_ids[], is_active}`. Only super admins can set `is_super_admin`, and the last super admin can't be demoted |
| POST | `/admin/users/{user}/reset-two-factor` | `admin.users.reset-two-factor` | `admin.users.admin` | |
| DELETE | `/admin/users/{user}/sessions` | `admin.users.sessions.destroy` | `admin.users.admin` | Sign the user out everywhere |
| GET | `/admin/users/export` | `admin.users.export` | `admin.users.export` | CSV |
| GET | `/admin/roles` | `admin.roles.index` | `admin.roles.view` | `Administration/Roles/Index`. Props `{roles, catalog}` |
| POST · PUT · DELETE | `/admin/roles[/{role}]` | `admin.roles.store/update/destroy` | `admin.roles.create/edit/delete` | `{name, description, permission_keys[]}` |
| POST | `/admin/roles/sync-catalog` | `admin.roles.sync` | `admin.roles.edit` | Same as `stagetrack:sync-permissions` |
| GET | `/admin/audit` | `admin.audit.index` | `admin.audit.view` | Filters: user, event, subject type, date |
| GET | `/admin/audit/export` | `admin.audit.export` | `admin.audit.export` | CSV |
| GET | `/admin/system/migrations` | `admin.migrations.index` | `admin.system.view` | `{ran:[{migration,batch}], pending:[...]}` |
| POST | `/admin/system/migrations/run` | `admin.migrations.run` | `admin.system.admin` | Needs password confirmation. Returns command output |
| POST | `/admin/system/migrations/rollback` | `admin.migrations.rollback` | `admin.system.admin` | Rolls back the last batch. Needs password confirmation |
| GET | `/admin/system/health` | `admin.health` | `admin.system.view` | `HealthChecker` results |
| GET | `/admin/system/storage` | `admin.storage` | `admin.system.view` | `StorageInspector` |
| POST | `/admin/system/cache/clear` | `admin.cache.clear` | `admin.system.admin` | `{targets: ["app","config","route","view"]}` |
| GET | `/admin/logs` | `admin.logs.index` | `admin.logs.view` | `?file=&level=&search=&page=` |
| GET | `/admin/logs/{file}/download` | `admin.logs.download` | `admin.logs.view` | |
| GET | `/admin/backups` | `admin.backups.index` | `admin.backups.view` | List `{name, size, created_at, type: manual \| scheduled}` |
| POST | `/admin/backups` | `admin.backups.store` | `admin.backups.create` | Create now |
| GET | `/admin/backups/{name}/download` | `admin.backups.download` | `admin.backups.view` | |
| DELETE | `/admin/backups/{name}` | `admin.backups.destroy` | `admin.backups.delete` | |
| POST | `/admin/backups/restore` | `admin.backups.restore` | `admin.backups.admin` | `{name}` or multipart `file`. Needs password confirmation |
| GET | `/admin/updates` | `admin.updates.index` | `admin.updates.view` | `{current, latest, notes, download_url}` from the feed |
| POST | `/admin/updates/install` | `admin.updates.install` | `admin.updates.admin` | `{source: "feed"}` or multipart `package`. Needs password confirmation |

Health response:

```json
{
  "status": "warning",
  "checks": [
    { "key": "php", "label": "PHP 8.3.9", "status": "ok" },
    { "key": "extensions", "label": "Required extensions", "status": "ok" },
    { "key": "database", "label": "MySQL 8.0.36 (12 ms)", "status": "ok" },
    { "key": "writable", "label": "storage/ and bootstrap/cache writable", "status": "ok" },
    { "key": "scheduler", "label": "Last scheduler run 14 minutes ago", "status": "warning", "hint": "Check the cron job" },
    { "key": "queue", "label": "0 pending / 0 failed jobs", "status": "ok" },
    { "key": "backup", "label": "Last backup 20 hours ago", "status": "ok" },
    { "key": "disk", "label": "1.2 GB free", "status": "ok" }
  ]
}
```

Migration run response:

```json
{ "ok": true, "output": "INFO  Running migrations.\n 2026_04_01_000000_add_kit_fields ... 18ms DONE", "pending": [] }
```

## Error format

| Status | When | Body (JSON requests) |
|---|---|---|
| 401 | Not authenticated | `{ "message": "Unauthenticated." }`. Page requests redirect to `/login` |
| 403 | Missing permission or failed policy | `{ "message": "This action is unauthorized." }` |
| 404 | Not found, `/setup` after install, bad cron token | `{ "message": "Not Found" }` |
| 409 | Stale update (`updated_at` mismatch on job/template edits) | `{ "message": "This record was changed by someone else." }` |
| 419 | CSRF token expired | Refresh the page and submit again |
| 422 | Validation, illegal stage transition, blocked overbooking | `{ "message": "…", "errors": { "field": ["…"] } }` |
| 423 | Account locked out | `{ "message": "Too many attempts. Try again in 15 minutes." }` |
| 429 | Throttled (login, 2FA, password reset) | `{ "message": "Too Many Attempts." }` |
| 503 | Maintenance mode (during update or restore) | Maintenance page |
