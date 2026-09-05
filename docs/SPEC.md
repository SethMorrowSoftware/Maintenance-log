# RideLog — Build Specification (authoritative contract)

**Product:** RideLog — a maintenance log & dashboard for amusement rides, go-karts and
attractions. Built for **Castle Fun Center**, but all branding is configurable at runtime.

This file is the **single source of truth**. Every file in this project must obey it.
If something here conflicts with your instincts, follow this file.

---

## 1. Target environment (NON-NEGOTIABLE)

| Constraint | Rule |
|---|---|
| Hosting | cPanel shared hosting. Upload via File Manager / FTP and it must run. |
| PHP | Must run on **PHP 8.0 → 8.4**. No enums, no `readonly`, no `never`, no first-class callable syntax, no `#[Override]`, no intersection types. Constructor promotion, `match`, nullsafe `?->`, named args, union types, typed properties are all OK. |
| Dependencies | **ZERO.** No Composer, no npm, no CDN, no external fonts, no external JS/CSS. Everything ships in-repo. |
| Build step | **NONE.** No transpilation, no bundler, no SASS. Ship what runs. |
| Database | MySQL 5.7+ / MariaDB 10.3+. **No** CTEs, **no** window functions, **no** `utf8mb4_0900_*` collations, **no** functional indexes, **no** `CHECK` constraints. |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci` everywhere. |
| Rewrite rules | mod_rewrite may NOT exist. All links are real `.php` URLs. `.htaccess` is used only for hardening, and every directive must be wrapped in `<IfModule>` so a host without that module doesn't 500. |
| Shell/cron | Not guaranteed. Scheduled work must also run opportunistically on page load. |
| JS | Vanilla ES2019. No frameworks, no modules/`import`, no optional chaining in `.js`? — optional chaining IS allowed (ES2020, universally supported). No build tooling. |
| Writable dirs | Only `config/` and `storage/` need to be writable. |

---

## 2. Directory layout

```
/                          <- upload this whole folder to public_html (or a subfolder)
├─ index.php               Dashboard
├─ login.php  logout.php  forgot-password.php  reset-password.php
├─ profile.php
├─ assets.php  asset-view.php  asset-edit.php
├─ logs.php    log-view.php    log-edit.php
├─ schedules.php  schedule-edit.php
├─ checklists.php checklist-edit.php
├─ inspections.php inspection-run.php inspection-view.php
├─ workorders.php workorder-view.php workorder-edit.php
├─ parts.php  part-edit.php  part-view.php
├─ reports.php
├─ users.php  user-edit.php
├─ settings.php
├─ categories.php          Asset categories + locations admin (one page, two tabs)
├─ audit.php
├─ notifications.php
├─ search.php
├─ labels.php              Printable sheet of QR asset labels
├─ qr.php                  Where a scanned label lands; sends the scanner onward
├─ file.php                Authenticated attachment download/inline view
├─ cron.php                Token-protected scheduled tasks
├─ error.php               Rendered for Apache's own 403/404/500 (see .htaccess)
├─ api/
│  ├─ index.php            JSON API front controller
│  └─ .htaccess            (allows everything; here only to override parent denies if needed)
├─ app/
│  ├─ .htaccess            DENY ALL
│  ├─ bootstrap.php        Autoloader + session + config + helpers. Every entry point requires this FIRST.
│  ├─ helpers.php
│  ├─ Config.php  Database.php  Auth.php  Acl.php  Csrf.php  Flash.php
│  ├─ Request.php  Response.php  Validator.php  Settings.php  Audit.php
│  ├─ Paginator.php  Csv.php  Mailer.php  Uploader.php  Dates.php  Str.php
│  ├─ View.php  Icon.php  Qr.php  Reports.php  Notifier.php  Scheduler.php
│  ├─ Models/   Asset.php  MaintenanceLog.php  User.php  ... (one per domain)
│  ├─ Api/      AssetsController.php  LogsController.php  ... (one per domain)
│  └─ Views/    layout.php  partials/*.php  <domain>/<page>.php
├─ assets/
│  ├─ css/app.css  css/print.css
│  ├─ js/core.js  js/charts.js  js/<domain>.js
│  └─ img/logo.svg
├─ install/
│  ├─ index.php            Multi-step web installer
│  ├─ upgrade.php          Migration runner for existing installs
│  ├─ schema.sql           Full DDL (uses {table} placeholders)
│  ├─ seed.sql             Reference data — ALWAYS installed
│  ├─ demo.sql             Sample karts/rides/logs — OPTIONAL checkbox
│  └─ migrations/          NNN_description.sql
├─ config/                 .htaccess DENY ALL. config.php written by installer. installed.lock
├─ storage/                .htaccess DENY ALL
│  ├─ uploads/  logs/  cache/
├─ docs/  SPEC.md  INSTALL.md  USER-GUIDE.md  DATA-MODEL.md
├─ .htaccess
└─ README.md
```

**Never** put a secret or a view file where the browser can fetch it. `app/`, `config/`,
`storage/` and `install/` (post-install) all get a deny-all `.htaccess` containing BOTH
Apache 2.2 and 2.4 syntax:

```apache
<IfModule mod_authz_core.c>
  Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
  Order allow,deny
  Deny from all
</IfModule>
```

---

## 3. PHP conventions

### 3.1 Namespaces & autoloading
- `app/Foo.php` → `App\Foo`; `app/Models/Foo.php` → `App\Models\Foo`; `app/Api/FooController.php` → `App\Api\FooController`.
- `app/bootstrap.php` registers an `spl_autoload_register` mapping `App\…` → `app/….php`. No Composer.
- Every PHP file starts with `<?php` and `declare(strict_types=1);` **except** view files (`app/Views/**`), which start with `<?php` only (they are included inside a function scope).
- Never use a closing `?>` at the end of a pure-PHP file.

### 3.2 Entry-point boilerplate
Every root-level page begins exactly like this:

```php
<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

use App\Acl;
use App\Auth;
use App\View;

Auth::requireLogin();
Acl::requirePermission('assets.view');
```

Then it handles POST (if any) using the POST-Redirect-Get pattern, then calls
`View::render('assets/index', [...])`. Root pages stay thin — real work lives in models.

### 3.3 SQL: `{table}` placeholders — MANDATORY
The installer supports a **table prefix**. Therefore **every** table name in **every**
SQL string — PHP and `.sql` files alike — is written in curly braces and expanded by the
DB layer / installer:

```php
$rows = db()->all("SELECT a.*, c.name AS category_name
                   FROM {assets} a
                   LEFT JOIN {asset_categories} c ON c.id = a.category_id
                   WHERE a.deleted_at IS NULL AND a.status = ?", [$status]);
```

Writing a bare table name (e.g. `FROM assets`) is a **bug**. There are no exceptions.

### 3.4 Database API (`App\Database`, reachable via the global `db()`)
```php
db()->all(string $sql, array $params = []): array          // list of assoc arrays
db()->one(string $sql, array $params = []): ?array         // first row or null
db()->value(string $sql, array $params = [], $default = null): mixed  // first column of first row
db()->column(string $sql, array $params = []): array       // flat list of first column
db()->run(string $sql, array $params = []): PDOStatement   // execute, returns statement
db()->insert(string $table, array $data): int              // table WITHOUT braces, returns insert id
db()->update(string $table, array $data, array $where): int// returns affected rows
db()->delete(string $table, array $where): int
db()->count(string $sql, array $params = []): int
db()->exists(string $table, array $where): bool
db()->transaction(callable $fn): mixed                     // auto commit/rollback
db()->table(string $name): string                          // prefixed table name
db()->pdo(): PDO
```
- **Always** bind values with `?` placeholders or `:named`. Never interpolate user input.
- `ORDER BY` / `ASC|DESC` must come from a hard-coded whitelist array, never from raw input.
- Connection runs `SET time_zone = '+00:00'` (inside try/catch — some hosts forbid it).
- `PDO::ATTR_ERRMODE = ERRMODE_EXCEPTION`, `ATTR_DEFAULT_FETCH_MODE = FETCH_ASSOC`,
  `ATTR_EMULATE_PREPARES = false`, `MYSQL_ATTR_INIT_COMMAND` sets `NAMES utf8mb4`.

### 3.5 Time & dates — READ THIS TWICE
- **All datetimes are stored in UTC.** `date_default_timezone_set('UTC')` is set in bootstrap.
- The display timezone comes from the `timezone` setting (default `America/New_York`).
- Conversions go through `App\Dates`:
  - `Dates::nowUtc(): string` → `'Y-m-d H:i:s'` in UTC (use this instead of MySQL `NOW()`)
  - `Dates::toUtc(?string $localInput, ?string $tz = null): ?string` — takes what a user typed
    (`datetime-local`, `Y-m-d H:i`, `Y-m-d`) and returns a UTC `Y-m-d H:i:s` or null
  - `Dates::toLocal(?string $utc, ?string $tz = null): ?DateTimeImmutable`
  - `Dates::datetime(?string $utc): string` — human display, e.g. `Sep 4, 2026 3:12 PM`
  - `Dates::date(?string $utc): string` — `Sep 4, 2026`
  - `Dates::inputDatetime(?string $utc): string` — value for `<input type="datetime-local">`
  - `Dates::inputDate(?string $utc): string` — value for `<input type="date">`
  - `Dates::ago(?string $utc): string` — `3 hours ago`
  - `Dates::today(): string` — local calendar date `Y-m-d` (for due-date math)
- `DATE` columns (due dates, purchase dates) hold **local calendar dates**, not UTC instants.
  Never timezone-shift a `DATE`.

### 3.6 Output escaping
- `e($v): string` → `htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`.
- **Every** interpolation of dynamic data into HTML uses `e()`. No exceptions, including
  values you "know" are safe.
- `attr()` for attribute values; `js(...)` → `json_encode` with `JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT`.

### 3.7 Global helpers (defined in `app/helpers.php`)
```php
db(): App\Database          setting(string $k, $default = null): mixed
e($v): string               attr($v): string           js($v): string
url(string $path = '', array $q = []): string   // absolute app URL
asset_url(string $path): string
user(): ?array              // current user row, or null
can(string $permission): bool
csrf_field(): string        csrf_token(): string
flash(string $type, string $msg): void
old(string $key, $default = ''): mixed     // repopulate form after validation failure
redirect(string $to): never-returns        // sends Location + exit
money($v): string           num($v, int $dec = 0): string
str_limit(string $s, int $n): string
icon(string $name, string $class = ''): string   // inline SVG
badge(string $type, string $label): string
config(string $key, $default = null): mixed
is_post(): bool             input(string $key, $default = null): mixed
abort(int $code, string $msg = ''): never-returns
log_error(string $msg, array $ctx = []): void
```

### 3.8 Views
- `View::render(string $view, array $data = [], ?string $layout = 'layout'): void`
- `View::partial(string $name, array $data = []): void` — renders `app/Views/partials/<name>.php`
- Data keys become local variables inside the view (`extract`).
- Every view sets `$title` via the `$data` array; the layout reads `$title`, `$activeNav`,
  `$breadcrumbs` (array of `['label' =>, 'url' =>]`), `$pageActions` (HTML string), `$bodyClass`.
- Views output HTML only. No queries in views (small formatting lookups are fine).

### 3.9 JSON API
- Single front controller: `api/index.php?r=<resource>.<action>`
- Route maps to `App\Api\<Studly(resource)>Controller::<camelAction>()`.
  `assets.list` → `App\Api\AssetsController::list()`. `work_orders.update_status` →
  `App\Api\WorkOrdersController::updateStatus()`.
- Auth: session cookie. Requires login unless the controller declares the action public.
- **CSRF:** every non-GET API request must send `X-CSRF-Token`. `api/index.php` enforces this.
- Response envelope, always:
  ```json
  { "ok": true,  "data": {...}, "meta": {...} }
  { "ok": false, "error": "Human readable message", "code": "validation_failed", "errors": {"field": "msg"} }
  ```
- Use `App\Response::json($data, $meta)`, `Response::error($msg, $code, $status, $errors)`.
- Controllers are plain classes with static methods returning arrays; the router wraps them.
- HTTP status: 200 ok, 400 bad request, 401 unauthenticated, 403 forbidden, 404 not found,
  422 validation, 429 rate limited, 500 server error.

### 3.10 Validation
`App\Validator` — fluent, returns errors keyed by field:
```php
$v = Validator::make($_POST, [
    'name'       => 'required|string|max:150',
    'asset_tag'  => 'required|string|max:60|unique:assets,asset_tag,' . $id,
    'category_id'=> 'required|int|exists:asset_categories,id',
    'status'     => 'required|in:in_service,out_of_service,maintenance,retired',
    'purchase_cost' => 'nullable|decimal|min:0',
    'email'      => 'nullable|email|max:190',
    'performed_at' => 'required|datetime',
]);
if ($v->fails()) { flash_errors($v->errors()); redirect(url('asset-edit.php', ['id' => $id])); }
$data = $v->validated();
```
Supported rules: `required, nullable, string, int, numeric, decimal, bool, email, url, date,
datetime, min:n, max:n, between:a,b, in:a,b,c, unique:table,column[,ignoreId], exists:table,column,
confirmed, regex:/…/, array, file, image, same:field, different:field, password` (password =
min 8 chars).

### 3.11 Errors
- No PHP notices/warnings on screen in production. `display_errors=0` when
  `config('app.debug')` is false; all errors go to `storage/logs/error-YYYY-MM-DD.log`.
- Uncaught exceptions render a friendly error page (or a JSON envelope for `/api/`).

---

## 4. Security requirements

1. **Passwords:** `password_hash($p, PASSWORD_DEFAULT)`; `password_verify`; rehash on login
   if `password_needs_rehash`. Minimum 8 chars, enforced server-side.
2. **Sessions:** custom name `ridelog_session`; cookie `httponly`, `samesite=Lax`,
   `secure` when HTTPS; `session_regenerate_id(true)` on login and on privilege change;
   idle timeout (`session_timeout_minutes` setting, default 480) and absolute 30-day cap;
   the session stores `user_id`, `login_time`, `last_activity`, `ip_hash`, `ua_hash`.
   A change in `ip_hash`/`ua_hash` invalidates the session.
3. **CSRF:** one token per session. `csrf_field()` in **every** `<form method="post">`.
   Verified on every POST/PUT/DELETE, web and API, using `hash_equals`.
4. **Rate limiting:** `login_attempts` table. After 5 failures for a username within 15 min,
   lock that account for 15 min (`locked_until`). After 20 failures from one IP in 15 min,
   throttle the IP. Successful login clears the counters.
5. **Remember me:** selector/validator pattern in `remember_tokens`
   (store `hash('sha256', $validator)`, never the raw validator). 30-day expiry, rotated on use.
6. **Password reset:** selector + `hash('sha256',$token)` in `password_resets`, 60-minute
   expiry, single use, invalidated on password change. Never reveal whether an email exists.
7. **Uploads:** `App\Uploader`. Whitelist extensions
   (`jpg jpeg png gif webp pdf doc docx xls xlsx csv txt heic`), verify MIME with `finfo`,
   cap size at `max_upload_mb` (default 8), store as
   `storage/uploads/YYYY/MM/<32-hex>.<ext>` with a random name, never trust the client name,
   record the original name in the DB. Images are re-encoded/downscaled through GD when
   possible (strips EXIF + any embedded payload). Serve **only** through `file.php`, which
   checks login + permission and sends `Content-Disposition` plus
   `X-Content-Type-Options: nosniff`.
8. **Headers** (sent by bootstrap): `X-Content-Type-Options: nosniff`,
   `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`,
   `Permissions-Policy: geolocation=(), microphone=(), camera=()`, and a CSP of
   `default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self'`.
   (Inline styles are permitted; **inline `<script>` is not** — put JS in `assets/js/*.js`
   and pass server data via `data-*` attributes or a `<script type="application/json">` block.)
9. **`config/config.php`** starts with `<?php if (!defined('RIDELOG')) { die('No direct access'); }`.
10. **Installer** refuses to run once `config/installed.lock` exists, and tells the user to
    delete the `install/` folder. Post-install it also writes `install/.htaccess` deny-all.
11. **Audit:** every create/update/delete of a domain record writes an `audit_log` row via
    `Audit::record()`. Login, logout, failed login, password change, permission change too.
12. **Soft deletes** (`deleted_at`) for assets, logs, work orders, users, parts. Every query
    that lists them must filter `deleted_at IS NULL`.
13. No secrets, credentials, or absolute server paths in anything sent to the browser.

---

## 5. Roles & permissions (`App\Acl`)

Four roles: `admin`, `manager`, `technician`, `viewer`.

```
assets.view assets.create assets.edit assets.delete assets.meter
logs.view logs.create logs.edit_own logs.edit_any logs.delete
schedules.view schedules.manage
checklists.view checklists.manage
inspections.view inspections.perform inspections.delete
workorders.view workorders.create workorders.edit workorders.assign workorders.close workorders.delete
parts.view parts.adjust parts.manage
reports.view reports.export
users.view users.manage
settings.manage
audit.view
costs.view
```

| Role | Grants |
|---|---|
| `viewer` | all `*.view` + `reports.view` |
| `technician` | viewer + `logs.create`, `logs.edit_own`, `assets.meter`, `inspections.perform`, `workorders.create`, `workorders.edit`, `parts.adjust` |
| `manager` | technician + `assets.*`, `logs.edit_any`, `logs.delete`, `schedules.manage`, `checklists.manage`, `inspections.delete`, `workorders.*`, `parts.manage`, `reports.export`, `audit.view` |
| `admin` | everything, including `users.manage`, `settings.manage` and `costs.view` |

**Those are the defaults, not the law.** `Acl::defaults()` is the table above;
`Acl::matrix()` lays the `role_permissions` setting (JSON `{role: [permissions]}`,
edited on `roles.php`, permission `users.manage`) over it for `viewer`, `technician`
and `manager`. `admin` is never overridden. `Acl::normalise()` keeps only catalogue
permissions and adds `<module>.view` whenever anything else in that module is
granted. Saving a matrix identical to the defaults stores nothing, so
`Acl::isCustomised()` is honest. Role descriptions are static text and are flagged
as "a guide" once the matrix is customised.

**Feature switches come first.** `Acl::can()` returns `false` for any permission
whose module is off (`Features::forPermission()` maps the permission prefix to a
switch), and `requirePermission()` answers 404 "switched off" rather than 403, so
every button and page behind a switched-off module disappears together.

**Money is admin-only.** `costs.view` is granted to `admin` and nobody else. Every
place a price, cost, rate, spend or stock value could appear — views, list columns,
CSV exports, reports (`Reports::withoutMoney()` strips `format: money` columns and
series; the cost report is not offered at all), the JSON API, the audit diff — checks
`costs_visible()` first. Forms for people without it carry no money fields; the server
ignores any that arrive and prices parts from the shelf price and labour from
`default_labor_rate`, so the administrator's figures stay right.

**Vocabulary.** The code, URLs, database and permission names say `assets` and
`schedules`; every label a person reads says **Scheduled Service** and, for assets,
whatever `asset_noun_singular` / `asset_noun_plural` (Settings → General, default
Machine / Machines) say — always through `asset_word($plural, $capital)` and
`an_asset()`, never a literal. Constants that carry the word (`Acl::CATALOGUE`,
`Reports::CATALOGUE`, `Status::MAP`) are reworded on the way out. Do not rename the
identifiers.

**Slack** (`App\Slack`). `chat.postMessage` with a bot token; every event method is
gated by its own `slack_on_*` setting, a channel override, the `slack_min_criticality`
floor and, for problems, a priority floor; `slack_on_safety` overrides the floor. The
work-order, inspection, asset-status, log and part models call it after their own
side effects; `cron.php` posts the due digest and the morning report. Never throws;
`config app.slack_api` can point it at a stand-in for testing.

**Starting data.** `install/fleet.sql` (real Castle Fun Center fleet, no history) or
`install/demo.sql` (fictional, with history), chosen in the installer; both are
`INSERT IGNORE` and are followed by `Scheduler::recomputeAll()`.

API:
```php
Acl::can(string $permission, ?array $user = null): bool
Acl::requirePermission(string $permission): void   // 403 page / JSON 403
Acl::roles(): array                                // ['admin' => 'Administrator', ...]
Acl::permissionsFor(string $role): array
Acl::canEditLog(array $log): bool                  // logs.edit_any OR (logs.edit_own AND own)
```
Navigation and buttons must be hidden when the user lacks the permission — **and** the
server must still enforce it. Hiding alone is not security.

---

## 6. Database schema

Full DDL lives in `install/schema.sql`. Common conventions:

- `id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY`
- `created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`
- `updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`
- `created_by INT UNSIGNED NULL`, `updated_by INT UNSIGNED NULL` on domain tables
- `deleted_at DATETIME NULL` on soft-deletable tables
- `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci`
- Any **unique-indexed** VARCHAR is at most `VARCHAR(191)`
- Money: `DECIMAL(12,2)`. Meters/quantities: `DECIMAL(12,2)`. Hours: `DECIMAL(8,2)`.
- Foreign keys are declared. Use `ON DELETE SET NULL` for optional references,
  `ON DELETE CASCADE` for child rows that cannot exist alone, `ON DELETE RESTRICT` otherwise.
- Use `ENUM(...)` for the status/type vocabularies listed below so the DB documents itself.
- `assets.custom_data TEXT NULL` holds the administrator-defined extra fields as one
  JSON object (`{key: value}`), so adding a field never touches the schema. Added by
  `install/migrations/001_asset_custom_data.sql` for databases that predate it; the
  installer records every migration file as applied on a fresh install.

### Tables (24)

`users, remember_tokens, password_resets, login_attempts, locations, asset_categories,
assets, meter_readings, maintenance_logs, maintenance_log_parts, parts, part_transactions,
maintenance_schedules, checklists, checklist_items, inspections, inspection_items,
work_orders, work_order_comments, attachments, audit_log, settings, notifications,
saved_reports`

### Controlled vocabularies (use these exact string values everywhere)

```
asset.status         : in_service | out_of_service | maintenance | retired
asset.criticality    : low | medium | high | critical
asset.meter_type     : none | hours | miles | cycles | laps
log.log_type         : preventive | corrective | repair | inspection | daily_check |
                       cleaning | modification | safety | other
schedule.frequency_type : daily | weekly | monthly | quarterly | semiannual | annual |
                          days | weeks | months | meter
checklist.frequency  : daily | weekly | monthly | quarterly | annual | preseason | adhoc
checklist_item.response_type : pass_fail | pass_fail_na | yes_no | text | number | meter
inspection.status    : in_progress | passed | failed | completed
inspection_item.response : pass | fail | na | yes | no | (empty)
workorder.status     : open | assigned | in_progress | on_hold | completed | cancelled
workorder.priority   : low | normal | high | urgent
workorder.source     : operator_report | inspection | preventive | breakdown | other
part_transaction.type: in | out | adjust
user.role            : admin | manager | technician | viewer
attachment.entity_type : asset | maintenance_log | work_order | inspection | part | user | setting
notification.type    : pm_due | pm_overdue | wo_assigned | wo_updated | inspection_failed |
                       low_stock | system
```

### Settings keys seeded by the installer
```
site_name, organization_name, timezone, date_format, time_format, currency_symbol,
items_per_page, session_timeout_minutes, max_upload_mb, allow_registration,
theme_default, primary_color, logo_path, mail_enabled, mail_from_name, mail_from_email,
mail_transport, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure,
notify_pm_due_days, cron_token, schema_version, low_stock_alerts, require_meter_on_log,
inspection_signature_required, week_start, app_installed_at,
asset_noun_singular, asset_noun_plural,
feature_work_orders, feature_schedules, feature_inspections, feature_parts, feature_meters,
feature_downtime, feature_costs, feature_photos, feature_labels, feature_reports,
feature_notifications, feature_audit, feature_drafts,
asset_custom_fields (hidden, JSON), role_permissions (hidden, JSON),
applied_migrations (hidden, JSON), slack_* (see App\Slack)
```

---

## 7. Front-end contract

### 7.1 Design tokens (defined once in `assets/css/app.css` on `:root`)
```
--brand-50 … --brand-900          indigo/blue primary ramp
--accent                           amber (Castle Fun Center accent)
--bg, --bg-elev, --bg-sunken, --surface, --border, --border-strong
--text, --text-muted, --text-subtle, --text-inverse
--ok, --ok-bg, --warn, --warn-bg, --danger, --danger-bg, --info, --info-bg
--radius-sm/md/lg/xl, --shadow-sm/md/lg
--space-1 … --space-12  (4px scale)
--font-sans (system stack), --font-mono
--header-h, --sidebar-w
```
Dark mode: `[data-theme="dark"]` on `<html>` overrides the tokens. Respect
`prefers-color-scheme` when the user has made no choice. The toggle persists to
`localStorage` **and** to the user's `theme` column.

### 7.2 Component classes (write these, use these — do not invent parallel names)
```
.app-shell .app-sidebar .app-main .app-header .app-content
.nav-group .nav-link .nav-link.is-active
.card .card-header .card-title .card-body .card-footer
.btn .btn-primary .btn-secondary .btn-ghost .btn-danger .btn-sm .btn-lg .btn-icon .btn-block
.form-group .form-label .form-input .form-select .form-textarea .form-check
.form-hint .form-error .input-group .required
.table .table-wrap .table-sortable  (mobile: rows collapse into cards via data-label attrs)
.badge .badge-ok .badge-warn .badge-danger .badge-info .badge-muted
.stat-card .stat-value .stat-label .stat-trend
.modal .modal-dialog .modal-header .modal-body .modal-footer .modal-backdrop
.toast .toast-success .toast-error .toast-info .toast-warning
.tabs .tab .tab.is-active .tab-panel
.pagination .page-link
.empty-state .empty-icon
.chip .chip-close  .avatar .avatar-sm  .progress .progress-bar
.timeline .timeline-item
.grid .grid-2 .grid-3 .grid-4  (responsive auto-collapse)
.alert .alert-success .alert-error .alert-warning .alert-info
.skeleton  .spinner  .divider  .kbd  .sr-only  .text-muted .text-right .text-center
.no-print .print-only
```
Status colour mapping — use everywhere, consistently:
`in_service`→ok, `maintenance`→warn, `out_of_service`→danger, `retired`→muted.
`open/assigned`→info, `in_progress`→warn, `on_hold`→muted, `completed`→ok, `cancelled`→muted.
`low`→muted, `normal`→info, `high`→warn, `urgent`→danger.

### 7.3 JavaScript (`assets/js/core.js`, global `RL` namespace)
No inline scripts (CSP forbids them). `core.js` is loaded on every page with `defer` and
provides:
```js
RL.api(route, {method, body, params})   // fetch wrapper -> resolves data, rejects Error with .errors
RL.toast(message, type)                 // type: success|error|info|warning
RL.confirm({title, message, confirmText, danger}) -> Promise<bool>
RL.modal.open(elOrHtml, opts) / RL.modal.close()
RL.on(selector, event, handler)         // delegated listener
RL.qs / RL.qsa / RL.el(tag, attrs, children)
RL.fmt.money(n) RL.fmt.number(n, dec) RL.fmt.date(iso) RL.fmt.datetime(iso)
RL.debounce(fn, ms)
RL.serialize(formEl) -> object
RL.theme.toggle() / RL.theme.set(name)
```
Auto-wired behaviours (no per-page JS needed):
- `[data-confirm="message"]` on a link/button → confirmation dialog before proceeding
- `.table-sortable th[data-sort]` → client-side column sort
- `[data-filter-target="#tableId"]` on an input → live row filter
- `[data-toggle="tab"]` → tab switching
- `[data-dismiss="alert"]` → dismiss
- `[data-autosubmit]` → submit the form on change
- `[data-mask="money"|"number"]` → input formatting
- `form[data-validate]` → client-side required/format hints (server still validates)
- `[data-counter]` → textarea character counters
- `[data-copy="text"]` → copy to clipboard
- `[data-print]` → window.print()
- Flash messages rendered server-side into `#toast-root` are picked up and shown as toasts
- Marks the current nav link active from `<body data-nav="...">`

`assets/js/charts.js` exposes `RL.chart.bar(el, data, opts)`, `RL.chart.line(...)`,
`RL.chart.donut(...)` — hand-rolled inline SVG, no library, theme-aware, with tooltips.
Data comes from a `<script type="application/json" id="...">` block or `data-chart` attribute.

### 7.4 Accessibility & mobile
- Technicians use phones in the shop: everything must work at 360px wide.
  Tap targets ≥ 44px. Tables collapse to cards. The sidebar becomes a slide-over drawer.
- Semantic HTML, `<label for>` on every input, `aria-*` on modals/toasts/nav,
  visible `:focus-visible` outlines, colour never the sole signal (pair with text/icon).
- `print.css` gives clean printable log sheets, inspection reports and asset labels.

---

## 8. Feature requirements

### Dashboard (`index.php`)
Managers and administrators: KPI tiles (total assets, in service, down, overdue PMs, open
work orders, inspections due today) · overdue & upcoming maintenance table · open work
orders · recent activity feed · 12-month maintenance-count bar chart · asset-status donut ·
cost trend line (admin only) · inspections-due-today list · quick-action buttons.
Technicians and viewers get `dashboard-technician`: three action tiles, their own jobs,
machines down, machines still to check today, service due, open problems, recent work.
Everything respects permissions.

### Assets (shown as "Machines")
List with search, filter (category/location/status/criticality), sort, pagination,
card & table views, CSV export. Profile page with tabs: Overview · History (one searchable
timeline of jobs, inspections, work orders raised and closed, status changes and manual
meter readings — `Asset::timeline()`) · Jobs · Schedules · Inspections · Work Orders ·
Files · Meter · Changes (audit). The same timeline, six events deep, renders beside the
log and work-order forms (`partials/asset-context`, refreshed via `assets.history`).
Create/edit with photo upload. Status change with reason (writes an audit + optional log).
Quick meter update. Soft delete. Printable QR label — pointing a phone at it opens the
asset, or drops straight into a new log or inspection, depending on how the sheet was
printed. No 1D barcode: the scanner in this setting is a phone, and adding a second
symbology nobody has a reader for is scope for its own sake.

### Maintenance logs
The core feature. List with rich filters (asset, category, location, technician, type, date
range, cost range) + CSV export + print. Create/edit with: asset picker (searchable),
`performed_at` date **and** time (defaults to now, editable), technician (defaults to the
logged-in user; managers may log on behalf of another), type, title, description,
work performed, labor hours, parts used (repeatable rows, optionally pulled from inventory,
decrementing stock), parts/labor/total cost (auto-calculated, overridable), meter reading
(updates the asset + writes a `meter_readings` row), downtime minutes, status before/after
(changing it updates the asset), follow-up flag + notes (auto-creates a work order when set),
link to a PM schedule (marks it complete and rolls the next due date), attachments.
Detail view shows the full record, attachments, and an audit trail.

### PM schedules (shown as "Scheduled Service")
Per-asset recurring plans by calendar interval **or** meter interval. Computes
`next_due_date` / `next_due_meter`, shows overdue/due-soon/ok, links a checklist template,
optional assignee, "Log this now" shortcut that pre-fills a maintenance log. Recomputes on
log completion and on meter update.

### Checklists & inspections
Admin-managed templates with sections and typed items (`pass_fail`, `pass_fail_na`,
`yes_no`, `text`, `number`, `meter`), each flagged required/critical. Templates apply to all
assets, a category, or one asset. Running an inspection is a mobile-first page: item-by-item
responses, per-item notes and photos, meter capture, running fail count, signature name,
save-as-draft and complete. A failed **critical** item automatically opens a high-priority
work order and can set the asset out of service. Inspection reports are printable.

### Work orders
Auto-numbered (`WO-000123`). Report an issue → triage → assign → work → complete.
Comments thread, priority, due date, downtime tracking, source, linked asset/inspection,
attachments. Completing one can create the maintenance log. Kanban-ish status board plus a
table view. Notifies the assignee.

### Parts inventory
Catalog with part number, supplier, cost, quantity on hand, reorder level, bin location.
Transactions log every movement. Consumption from a maintenance log decrements stock and
writes an `out` transaction. Low-stock dashboard alert and report.

### Reports (`reports.php`)
Tabbed: Maintenance History · Cost Analysis (by asset/category/month) · Downtime ·
Inspection Compliance · Asset Inventory · Parts Usage · Technician Activity.
Every report: filter form, results table, chart where meaningful, CSV export, print view.

### Feature switches (`App\Features`)
Thirteen modules, each a `feature_*` bool setting in the `features` group: work orders,
schedules, inspections (with checklists), parts, meters, downtime, costs, photos, labels,
reports (with CSV exports), notifications (the bell), audit, drafts. `Features::on()`,
`feature_on()` and `require_feature()` (404 with a "switched off" message). Off means the
nav item, the page, the form fields, the machine-page tab, the dashboard stat, the report,
the cron step and the Slack line all go together; nothing is deleted. `costs` off hides
money from everybody, administrators included (`costs_visible()`).

### Roles (`roles.php`)
Permission `users.manage`. One grid: a row per catalogue permission, a column per role,
headcount per role, admin column fixed. Rows for a switched-off module are dimmed and kept.
Cells that differ from the defaults are marked; "Reset to defaults" behind a confirm. See §5.

### Custom fields (`App\CustomFields`, Settings → Fields)
Definitions in the `asset_custom_fields` setting: `{key, label, type, options, list, hint}`,
types `text | number | date | yesno | choice`, at most 30. The key is slugged from the first
label and then fixed, so renaming keeps the data. Values are one JSON object in
`assets.custom_data`; a value for a field since removed is carried across untouched.
Rendered on the machine form (`cf_<key>` inputs, validated per type), the overview tab
(filled-in only), the list (fields flagged `list`), the CSV export, and searched by
`LIKE` on the JSON. The editor is a repeater on `settings.php?tab=fields`
(action `save_fields`); a rejected save re-renders what was typed.

### Quick expansion
Any form with a machine picker shows "Not in the list? Add it" to anyone with
`assets.create`; the link carries `return=<current path>` (validated by
`Request::safeRedirect()`) and the new machine's save redirects back with `asset_id`.
Drafts never blank a select that the page opened with a value. `asset-edit.php?copy_from=ID`
pre-fills a new machine from an existing one (batch-alike fields only; not serials, VIN,
meter reading or photo) with `Asset::nextName()` and `Asset::suggestTag()`; "Save and add
another like it" chains it.

### Users, settings, audit
User CRUD with role assignment, activate/deactivate, force password change, reset password,
avatar. Self-service profile + password change. Settings page grouped into tabs
(General · Features · Fields · Localization · Maintenance · Uploads · Email · Slack ·
Security · Branding · System) with a "send test email" button. The System tab (`App\Health`) is a plain-language health
report — PHP, extensions, database, writable folders, setup files, HTTPS, nightly job,
error log, disk, labour rate, email — plus counts and a one-click full export
(`export.php`: one CSV per table in a ZIP, or a single JSON file without `zip`; secrets
and photos excluded). Audit log with filters and detail diff view (money rows hidden
without `costs.view`).

### Drafts
Forms marked `data-draft="key"` (log and work-order forms) keep a snapshot of every
field in `localStorage` as it is typed (`initDrafts` in core.js) and offer it back on
return. Only the server knows a save succeeded: it calls `Flash::clearDraft($key)`, the
layout passes the keys in `RL.config.clearDrafts`, and the browser forgets them.

### Notifications
In-app bell with unread count, `notifications.php` list, mark read/all read. Generated for
PM due/overdue, work order assignment/update, failed inspection, low stock. Optional email
via `App\Mailer` (PHP `mail()` or SMTP over raw sockets — no external library).

### Installer (`install/index.php`)
Steps: Welcome → Requirements → Database → Administrator → Site settings → Install → Done.
Session-backed, back/forward navigation, inline validation, live DB connection test,
optional demo data, writes `config/config.php` (0640 where possible) + `config/installed.lock`,
generates a random `cron_token` and app key, records every file in `install/migrations/`
as already applied (schema.sql is always current), and shows post-install security advice
(delete `install/`, chmod, HTTPS). `install/upgrade.php` re-applies schema.sql and
seed.sql, runs the migration files not yet listed in the `applied_migrations` setting,
and updates the `schema_version` setting.

### Cron (`cron.php?token=…`)
Recompute PM due dates, raise due/overdue notifications, expire old sessions & reset tokens,
prune audit rows past `audit_retention_days`, low-stock alerts, optional digest email.
Also invoked opportunistically (max once/hour, tracked in `storage/cache/`) on dashboard load
so the app still works with no cron configured.

---

## 9. Definition of done for every file you write

- [ ] `php -l` clean on PHP 8.4, and syntax valid for PHP 8.0 (no 8.1+ features)
- [ ] Every table name in SQL is wrapped in `{braces}`
- [ ] Every SQL value is a bound parameter
- [ ] Every dynamic value printed to HTML goes through `e()`
- [ ] Every POST form contains `csrf_field()`
- [ ] Every page calls `Auth::requireLogin()` and the right `Acl::requirePermission()`
- [ ] Every list query filters `deleted_at IS NULL`
- [ ] Every mutation calls `Audit::record()`
- [ ] Datetimes stored via `Dates::toUtc()` / `Dates::nowUtc()`, displayed via `Dates::*`
- [ ] No inline `<script>`; no external CDN references
- [ ] Uses only the CSS component classes listed in §7.2
- [ ] Works at 360px wide
- [ ] Empty states, loading states and error states are all handled
