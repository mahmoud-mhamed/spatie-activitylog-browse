# Activity Log Browse

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mahmoud-mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mahmoud-mhamed/spatie-activitylog-browse)
[![License](https://img.shields.io/packagist/l/mahmoud-mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mahmoud-mhamed/spatie-activitylog-browse)
[![PHP Version](https://img.shields.io/packagist/php-v/mahmoud-mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mahmoud-mhamed/spatie-activitylog-browse)

A Laravel package that extends [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) v4 with **automatic model logging**, **rich contextual enrichment**, a **web-based log browser**, a **statistics dashboard**, **automatic retention/cleanup**, and a **deletion audit trail** — all with English/Arabic UI, dark mode, and an optional password gate.

> Arabic version: [README.ar.md](README.ar.md)

## Features

### Logging
- 🔁 **Auto-log all models** without the `LogsActivity` trait — opt-out via excluded list
- 📦 **Rich enrichment** — request, device, performance, app, session, and execution context attached to every log entry
- 🆔 **UUID-friendly** — morph ID columns automatically migrated to support UUIDs
- ⚡ **Performance-optimized** — per-class caching, request-scoped collectors, no per-event reflection

### Browsing & Analytics
- 🌐 **Browse UI** — filter, search, popovers, color-coded diffs, related-model navigation
- 📊 **Statistics dashboard** — charts for hourly/daily/monthly activity, peak times, top models/causers/attributes
- 🌍 **Localized** — English & Arabic with automatic RTL layout
- 🌙 **Dark mode** — system-aware with manual toggle, persisted in localStorage
- 📝 **Attribute translation** — uses Laravel's `validation.attributes`

### Cleanup & Audit
- 🧹 **Manual cleanup page** — preview-then-delete with model and date filters
- ⏱ **Automatic retention** — age + size limits, per-model overrides (incl. `'forever'`), Laravel-scheduler integration
- 📜 **Deletion history** — JSON audit log of every cleanup with row-level diff (before/after, duration, trigger, user)

### Security
- 🛡 **Optional password gate** with rate-limited login (5/min)
- 🚪 **Authorization gate** support for fine-grained permissions
- 🏢 **Multi-tenancy** aware (works out of the box with [stancl/tenancy](https://tenancyforlaravel.com/))

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick Start](#quick-start)
- [Configuration](#configuration)
  - [Auto-Log](#auto-log)
  - [Enrichment](#enrichment)
  - [Browse UI](#browse-ui-config)
  - [Password Gate](#password-gate)
  - [Retention / Auto-Cleanup](#retention--auto-cleanup)
  - [Deletion History](#deletion-history-config)
- [Usage](#usage)
- [Browse UI](#browse-ui)
- [Statistics Dashboard](#statistics-dashboard)
- [Deletion History Page](#deletion-history-page)
- [Artisan Commands](#artisan-commands)
- [Localization](#localization)
- [Multi-Tenancy](#multi-tenancy)
- [Performance Notes](#performance-notes)
- [Architecture](#architecture)
- [License](#license)

## Requirements

- PHP **8.1+**
- Laravel **10**, **11**, or **12**
- spatie/laravel-activitylog **^4.0**

## Installation

```bash
composer require mahmoud-mhamed/spatie-activitylog-browse
```

If auto-discovery doesn't work, register the provider manually in `bootstrap/providers.php` (Laravel 11+) or `config/app.php`:

```php
Mhamed\SpatieActivitylogBrowse\ActivitylogBrowseServiceProvider::class,
```

Then run the install command — publishes the spatie migration, the package config, runs migrations, fixes UUID-friendly morph columns, adds performance indexes, and prepares the deletion-history storage:

```bash
php artisan activitylog-browse:install
```

> Re-running `install` after upgrading the package will offer to refresh your config so new options (e.g. `retention`, `deletion_history`) are picked up.

### Publishing individual assets

```bash
# Spatie migration
php artisan vendor:publish --provider="Spatie\Activitylog\ActivitylogServiceProvider" --tag="activitylog-migrations"
php artisan migrate

# Package config
php artisan vendor:publish --tag=activitylog-browse-config

# Views (optional)
php artisan vendor:publish --tag=activitylog-browse-views

# Language files (optional)
php artisan vendor:publish --tag=activitylog-browse-lang

# Migrations (optional — for multi-tenancy setups)
php artisan vendor:publish --tag=activitylog-browse-migrations
```

> **Tip:** Use `--force` to overwrite previously published files after upgrading the package:
> ```bash
> php artisan vendor:publish --tag=activitylog-browse-config --force
> ```

### Local Development

To install as a local path repository, add the following to your Laravel app's `composer.json`:

```json
"repositories": [
    { "type": "path", "url": "../spatie-activitylog-browse" }
]
```

```bash
composer require mahmoud-mhamed/spatie-activitylog-browse:@dev
php artisan activitylog-browse:install
```

## Quick Start

After running `activitylog-browse:install`:

1. Open `/activity-log` in your browser — protected by `web` + `auth` middleware by default.
2. Trigger any model change — it'll appear with full enrichment.
3. (Optional) Enable retention in `config/activitylog-browse.php` so old logs prune themselves.
4. (Optional) Set `ACTIVITYLOG_BROWSE_PASSWORD` in `.env` to add a password gate.

## Configuration

The published config file is at `config/activitylog-browse.php`. Key sections below.

### Auto-Log

```php
'auto_log' => [
    'enabled' => true,
    'events' => ['created', 'updated', 'deleted'],
    'models' => '*',                // '*' = all models, or array of specific classes
    'excluded_models' => [],
    'log_name' => 'default',
    'log_only_dirty' => true,
    'excluded_attributes' => [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ],
    'submit_empty_logs' => false,
    'exclude_null_on_create' => false,
],
```

Models that already use the `LogsActivity` trait are automatically skipped to prevent duplicate entries.

### Enrichment

Each enrichment section can be enabled/disabled and has per-field toggles. Disabling an entire section removes all per-event overhead for that section.

<details>
<summary><strong>Show all enrichment options</strong></summary>

```php
'request_data' => [
    'enabled' => true,
    'fields' => [
        'url' => true, 'previous_url' => true,
        'method' => true, 'route_name' => true,
    ],
],

'device_data' => [
    'enabled' => true,
    'fields' => ['ip' => true, 'user_agent' => true, 'referrer' => true],
],

'performance_data' => [
    'enabled' => true,
    'fields' => [
        'request_duration' => true,  // ms since LARAVEL_START
        'memory_peak' => true,       // bytes
        'db_query_count' => true,    // requires DB::enableQueryLog() to be useful
    ],
],

'app_data' => [
    'enabled' => true,
    'fields' => [
        'environment' => true,
        'php_version' => true,
        'server_hostname' => true,
    ],
],

'session_data' => [
    'enabled' => true,
    'fields' => ['auth_guard' => true],
],

'execution_context' => [
    'enabled' => true,
    'fields' => [
        'source' => true,        // "web" | "console" | "queue" | "schedule"
        'job_name' => true,      // queue job class name
        'command_name' => true,  // artisan command name
    ],
],
```

All collectors gracefully return empty data when running outside their context (e.g. request data in console).

</details>

### Browse UI Config

```php
'browse' => [
    'enabled' => true,
    'prefix' => 'activity-log',
    'middleware' => ['web', 'auth'],
    'per_page' => 25,
    'gate' => null,                 // e.g. 'view-activity-log'
    'password' => env('ACTIVITYLOG_BROWSE_PASSWORD'),
    'available_locales' => ['en', 'ar'],
],
```

Set `gate` to a Laravel Gate name to restrict access; the package will call `Gate::authorize($name)` on every browse request.

### Password Gate

For environments where you want a shared password protecting the browse UI (in addition to whatever auth/middleware you've configured):

```bash
# .env
ACTIVITYLOG_BROWSE_PASSWORD=your-secret-here
```

When set, users hitting `/activity-log` are redirected to a login screen. The form is rate-limited to **5 attempts per minute per IP**. Authentication is stored in the session — a logout button appears in the navbar when a user is signed in via password.

Set the env variable to an empty value (or remove it) to disable the gate entirely.

### Retention / Auto-Cleanup

Automatically prune old activity log entries based on age and table size limits, with per-model overrides for sensitive data that should be kept longer (or forever).

```php
'retention' => [
    'enabled' => true,

    'default_days' => 90,           // catch-all age limit
    'max_rows'     => 1_000_000,    // null to disable
    'max_size_mb'  => 500,          // null to disable

    'per_model' => [
        App\Models\AuditLog::class => 'forever',
        App\Models\User::class     => 365,
    ],

    'per_log_name' => [
        'security' => 365,
    ],

    'chunk_size'     => 1000,
    'optimize_after' => true,

    'schedule'      => 'daily',     // 'daily' | 'weekly' | 'monthly' | null
    'schedule_time' => '03:00',     // 24-hour HH:MM
],
```

#### Priority hierarchy (strongest → weakest)

1. **`per_model` / `per_log_name`** — always win.
   - `'forever'` is fully protected from both age and size pruning.
   - An int day count protects records younger than the configured days from BOTH age and size pruning.
2. **`max_rows` / `max_size_mb`** — hard size caps. They win over `default_days`: when the table is over the cap, the oldest records (not protected by a per-model rule) are deleted *even if they are still inside the `default_days` window*.
3. **`default_days`** — the catch-all rule. Applies only to records not covered by a higher-priority rule.

#### What happens at the size limit?

| Per-model rule        | Age-based prune              | Size-based prune (when `max_rows` / `max_size_mb` is hit)                        |
|-----------------------|------------------------------|----------------------------------------------------------------------------------|
| Not configured        | Deleted after `default_days` | **Can be deleted** (oldest first)                                                |
| `365` (any int days)  | Deleted after 365 days       | **Protected** while younger than 365 days; older records can be deleted          |
| `'forever'`           | Never deleted                | **Never deleted** (fully protected)                                              |

> **TL;DR:** Per-model retention is the authoritative rule. A model set to `365` days will keep its rows for the full 365 days even if the table is over its size cap — they only become eligible for size pruning after their own retention window expires. The size cap is therefore **best-effort**: if every record is still inside its per-model retention window, nothing is deleted and the table stays over the cap until the protections expire. Set realistic per-model values to keep the size cap effective.

#### How it runs

| Trigger     | When |
|-------------|------|
| **Schedule** | Automatically at `schedule_time` (frequency = `daily`/`weekly`/`monthly`). Requires `schedule:work` or a cron entry calling `schedule:run`. |
| **CLI**      | `php artisan activitylog-browse:prune` — see [Artisan Commands](#artisan-commands) |
| **UI**       | A **Run Cleanup Now** button on the cleanup page. |

### Deletion History Config

Every cleanup operation (manual, scheduled, CLI, dry-run) is recorded in an append-only JSON file:

```php
'deletion_history' => [
    'enabled' => true,
    'path' => storage_path('activitylog-browse/deletion-history.json'),
    'max_entries' => 500,    // oldest are dropped first
    'max_size_mb' => 3,      // file is reset if exceeded
],
```

Each entry captures: timestamp, trigger (`schedule`/`cli`/`ui`/`manual`), operation type, deleted count + breakdown, duration, table state before/after (rows + size MB), config snapshot, and user/IP context. Empty operations (0 rows deleted) are skipped.

The package automatically creates the storage directory and a `.gitignore` to prevent committing the JSON file.

## Usage

### Auto-Logging

Once installed, all Eloquent model events are logged automatically:

```php
$user = User::create(['name' => 'John']);   // Logged
$user->update(['name' => 'Jane']);          // Logged
$user->delete();                            // Logged
```

To exclude specific models:

```php
'excluded_models' => [
    App\Models\TemporaryFile::class,
],
```

### Enrichment payload

Every activity log entry — whether from auto-logging, the `LogsActivity` trait, or manual `activity()` calls — is enriched with contextual data:

<details>
<summary><strong>Example enriched <code>properties</code></strong></summary>

```json
{
    "attributes": { "name": "Jane" },
    "old": { "name": "John" },
    "request_data": {
        "url": "https://example.com/users/1",
        "method": "PUT",
        "route_name": "users.update"
    },
    "device_data": {
        "ip": "192.168.1.1",
        "user_agent": "Mozilla/5.0 ..."
    },
    "performance_data": {
        "request_duration": 142,
        "memory_peak": 12582912,
        "db_query_count": 8
    },
    "app_data": {
        "environment": "production",
        "php_version": "8.3.0",
        "server_hostname": "web-01"
    },
    "session_data": { "auth_guard": "web" },
    "execution_context": {
        "source": "web",
        "job_name": null,
        "command_name": null
    }
}
```

</details>

## Browse UI

Visit `/activity-log` (or your configured prefix). Top navigation includes: **Activity Log**, **Statistics**, **Cleanup**, **Deletion History**, **About** — plus a language switcher, theme toggle, and (when password gate is on) logout.

The list view provides:

- **Filtering** — log name, event type, model type, model ID, causer, date range, description search
- **Changed-attribute filter** — select a model type, then filter by a specific attribute (e.g. only show logs where `name` changed)
- **Quick preview popover** — hover the info icon on a row to see the old/new diff inline
- **Current-attributes popover** — view the subject's live model data without leaving the list
- **Model info sidebar** — when a model type is selected, shows total logs, unique records, table name, table size, event-breakdown badges, and clickable attribute chips for quick filtering
- **Related model navigation** — jump to all logs for a related model instance
- **Detail view** — color-coded diff, request/device/performance/app/session/execution metadata, raw JSON

### Attribute translation

Attribute names (column names like `first_name`, `email_verified_at`) are auto-translated using `lang/{locale}/validation.php`:

- If `validation.attributes.{key}` exists → **"First Name" (first_name)**
- Otherwise → **"Email Verified At"** (auto-headlined) with the original key in small text

Define translations once and they appear everywhere in the UI:

```php
'attributes' => [
    'first_name' => 'First Name',
    'email' => 'Email Address',
    'created_at' => 'Creation Date',
],
```

## Statistics Dashboard

Visit `/activity-log/statistics`. Each section loads independently via AJAX with skeleton states for fast initial render.

A date-range filter at the top applies to all sections (cached for 60s when filtered, 120s for all-time).

**Sections:** Overview cards · Peak Hour chart · Daily Activity (30 days) · Activity by Day of Week · Peak Times · Monthly Activity · System vs User Actions · Events Breakdown · Log Names · Top Models · Top Causers · Most Changed Attributes (last 1000 updates).

## Deletion History Page

`/activity-log/deletion-history` — auditable record of every cleanup operation:

- **Stats cards** — total entries, file size, current path
- **Per-row** — when, trigger badge (color-coded: schedule/cli/ui/manual + dry-run flag), operation, deleted count + breakdown (age vs size), table size before → after with diff, duration in ms, user/IP/command
- **Expandable JSON** — click a row to see the full entry payload (config snapshot, table state, context)
- **Pagination** — 25 per page
- **Clear button** — wipes the JSON file (with confirmation)

## Artisan Commands

```bash
# Install / upgrade
php artisan activitylog-browse:install

# Retention / cleanup
php artisan activitylog-browse:prune                # full prune (age + size)
php artisan activitylog-browse:prune --dry-run      # report what would be deleted
php artisan activitylog-browse:prune --age          # age-based only
php artisan activitylog-browse:prune --size         # size-based only
```

The `prune` command is automatically registered with the Laravel scheduler when `retention.schedule` is set (and you have `schedule:work` or cron running).

## Localization

The package ships with English and Arabic translations. The UI auto-adapts to RTL when locale is `ar`.

```php
// config/app.php
'locale' => 'ar',
```

Or at runtime:

```php
App::setLocale('ar');
```

The browse UI also includes a language switcher button that saves the preference in the session.

To customize translations:

```bash
php artisan vendor:publish --tag=activitylog-browse-lang
# Use --force to overwrite previously published files
php artisan vendor:publish --tag=activitylog-browse-lang --force
```

This copies the files to `lang/vendor/activitylog-browse/` where you can edit them or add new languages — then update `available_locales` in the config.

## Multi-Tenancy

Works out of the box with [stancl/tenancy](https://tenancyforlaravel.com/) (multi-database tenancy):

- **Cache isolation** — keys are prefixed with the tenant ID (e.g. `activitylog-browse:t:1:stats:overview`).
- **Database connection** — queries use whatever connection your Activity model defines.
- **No hard dependency** — tenant detection uses `function_exists('tenant')`.

### Setup for multi-database tenancy

1. Disable automatic migrations so they don't run on the central DB:
   ```php
   'load_migrations' => false,
   ```
2. Publish migrations to your tenant migration path:
   ```bash
   php artisan vendor:publish --tag=activitylog-browse-migrations
   ```
   Then move them to `database/migrations/tenant/` (or wherever your tenant migrations live).
3. Add tenancy middleware to the browse routes:
   ```php
   'browse' => [
       'middleware' => ['web', 'auth', \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class],
   ],
   ```

Without tenancy, no extra setup is needed — everything works as expected.

## Performance Notes

The package is designed for low-overhead operation even on high-traffic apps with auto-logging enabled:

- **Per-class caching** in `GlobalModelLogger` — `LogsActivity` trait detection runs **once per model class**, not per event
- **Request-scoped collectors** — `debug_backtrace`, auth guard enumeration, and source detection run **once per request**, results cached as static properties
- **Disabled-aware enrichment** — the observer only invokes collectors that are enabled in config; disabled sections add **zero per-event overhead**
- **Console-only schedule registration** — HTTP requests skip scheduler binding entirely
- **Bulk-friendly delete chunks** — retention pruning runs in chunks of 1000 (configurable) with `set_time_limit(30)` to avoid table-lock storms

For best results on high-throughput apps:

- Add high-frequency models to `excluded_models`
- Disable `execution_context.fields.job_name` if you don't need queue tracking (skips a `debug_backtrace` per request)
- Use `Model::withoutEvents(...)` around bulk imports

## Architecture

| Component | Role |
|---|---|
| `ActivitylogBrowseServiceProvider` | Registers everything: listener, observer, routes, scheduler |
| `GlobalModelLogger` | Listens to global Eloquent events; logs activity for models without `LogsActivity` |
| `ActivityEnrichmentObserver` | Observes the Activity model's `creating` event; merges enrichment data into properties |
| `RequestDataCollector` / `DeviceDataCollector` / ... | Individual data collectors invoked by the observer |
| `RelationDiscovery` | Reflection-based auto-discovery of Eloquent relationships for related-model browsing |
| `RetentionPruner` | Implements the priority hierarchy — age, size, per-model, per-log-name pruning |
| `DeletionLogger` | Writes deletion entries to the JSON history file with size/count caps |
| `ActivityLogHelpers` | Shared helpers — connection name, table size, cache key prefix, stats cache invalidation |
| `ActivityLogController` | Handles the browse UI: filtering, AJAX endpoints, statistics API, attribute inspection, cleanup, deletion history |
| `RequirePassword` middleware | Enforces the optional password gate (rate-limited login) |
| `SetLocale` middleware | Applies the user's locale preference from the session |
| `InstallCommand` | `activitylog-browse:install` — publishes assets, fixes UUID columns, adds indexes |
| `PruneCommand` | `activitylog-browse:prune` — manual / scheduled retention runs |

## License

MIT — see [LICENSE](LICENSE).
