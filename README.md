# Activity Log Browse

[![Latest Version on Packagist](https://img.shields.io/packagist/v/mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mhamed/spatie-activitylog-browse)
[![License](https://img.shields.io/packagist/l/mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mhamed/spatie-activitylog-browse)
[![PHP Version](https://img.shields.io/packagist/php-v/mhamed/spatie-activitylog-browse.svg?style=flat-square)](https://packagist.org/packages/mhamed/spatie-activitylog-browse)

A Laravel package that extends [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) v4 with automatic model logging, rich contextual enrichment, a web-based log browser, and a statistics dashboard.

## Features

- **Auto-log all models** — Automatically log created/updated/deleted events for all Eloquent models without adding the `LogsActivity` trait
- **Rich enrichment** — Attach request, device, performance, app, session, and execution context data to every log entry
- **Browse UI** — Web interface to view, filter, search, and inspect activity logs with quick-preview popovers and color-coded diffs
- **Statistics dashboard** — Comprehensive analytics page with charts, breakdowns, and peak-time analysis
- **Related model browsing** — Navigate between related model logs via auto-discovered Eloquent relationships
- **Model info sidebar** — View model stats, table size, and clickable attribute chips to filter by changed attributes
- **Attribute translation** — Attribute names are translated using Laravel's `validation.attributes` lang file throughout the UI
- **Localization** — Built-in support for English and Arabic with RTL layout

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Browse UI](#browse-ui)
- [Statistics Dashboard](#statistics-dashboard)
- [Localization](#localization)
- [Multi-Tenancy](#multi-tenancy)
- [Architecture](#architecture)
- [License](#license)

## Requirements

- PHP 8.1+
- Laravel 10, 11, or 12
- spatie/laravel-activitylog ^4.0

## Installation

```bash
composer require mhamed/spatie-activitylog-browse
```

If auto-discovery doesn't work, register the provider manually in `bootstrap/providers.php` (Laravel 11+) or `config/app.php`:

```php
Mhamed\SpatieActivitylogBrowse\ActivitylogBrowseServiceProvider::class,
```

Then run the install command. This publishes the spatie migration, the package config, and runs migrations:

```bash
php artisan activitylog-browse:install
```

Or publish individually:

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

> **Tip:** Use `--force` to overwrite previously published files (e.g., after updating the package):
> ```bash
> php artisan vendor:publish --tag=activitylog-browse-config --force
> php artisan vendor:publish --tag=activitylog-browse-views --force
> php artisan vendor:publish --tag=activitylog-browse-lang --force
> ```

### Local Development

To install as a local path repository, add the following to your Laravel app's `composer.json`:

```json
"repositories": [
    {
        "type": "path",
        "url": "../spatie-activitylog-browse"
    }
]
```

Then require it:

```bash
composer require mhamed/spatie-activitylog-browse:@dev
```

Run the install command:

```bash
php artisan activitylog-browse:install
```

## Configuration

After publishing, the config file is located at `config/activitylog-browse.php`. It has the following sections:

### Auto-Log

```php
'auto_log' => [
    'enabled' => true,
    'events' => ['created', 'updated', 'deleted'],
    'models' => '*',              // '*' = all models, or array of specific classes
    'excluded_models' => [],
    'log_name' => 'default',
    'log_only_dirty' => true,
    'excluded_attributes' => ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
    'submit_empty_logs' => false,
],
```

Set `models` to `'*'` to log all models automatically, or pass an array to log only specific ones:

```php
'models' => [
    App\Models\User::class,
    App\Models\Order::class,
],
```

Models that already use the `LogsActivity` trait are automatically skipped to prevent duplicate entries.

### Request Data Enrichment

```php
'request_data' => [
    'enabled' => true,
    'fields' => [
        'url' => true,
        'previous_url' => true,
        'method' => true,
        'route_name' => true,
    ],
],
```

### Device Data Enrichment

```php
'device_data' => [
    'enabled' => true,
    'fields' => [
        'ip' => true,
        'user_agent' => true,
        'referrer' => true,
    ],
],
```

### Performance Data Enrichment

```php
'performance_data' => [
    'enabled' => true,
    'fields' => [
        'request_duration' => true,  // milliseconds since LARAVEL_START
        'memory_peak' => true,       // peak memory usage in bytes
        'db_query_count' => true,    // number of DB queries executed
    ],
],
```

### App Data Enrichment

```php
'app_data' => [
    'enabled' => true,
    'fields' => [
        'environment' => true,       // e.g. "local", "production"
        'php_version' => true,
        'server_hostname' => true,
    ],
],
```

### Session Data Enrichment

```php
'session_data' => [
    'enabled' => true,
    'fields' => [
        'auth_guard' => true,        // the guard used for authentication
    ],
],
```

### Execution Context Enrichment

```php
'execution_context' => [
    'enabled' => true,
    'fields' => [
        'source' => true,            // "web", "console", "queue", or "schedule"
        'job_name' => true,          // queue job class name
        'command_name' => true,      // artisan command name
    ],
],
```

All enrichment collectors gracefully return empty arrays when running in console/queue context where request data is unavailable.

### Browse UI

```php
'browse' => [
    'enabled' => true,
    'prefix' => 'activity-log',
    'middleware' => ['web', 'auth'],
    'per_page' => 25,
    'gate' => null,
    'available_locales' => ['en', 'ar'],
],
```

Set `gate` to a gate name to restrict access (e.g. `'gate' => 'view-activity-log'`).

## Usage

### Auto-Logging

Once installed, all Eloquent model events are logged automatically. No trait needed:

```php
$user = User::create(['name' => 'John']); // Logged
$user->update(['name' => 'Jane']);         // Logged
$user->delete();                           // Logged
```

To exclude specific models:

```php
'excluded_models' => [
    App\Models\TemporaryFile::class,
],
```

### Enrichment

Every activity log entry (including those from the `LogsActivity` trait or manual `activity()` calls) is automatically enriched with contextual data:

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
    "session_data": {
        "auth_guard": "web"
    },
    "execution_context": {
        "source": "web",
        "job_name": null,
        "command_name": null
    }
}
```

## Browse UI

Visit `/activity-log` (or your configured prefix) to browse logs. The UI provides:

- **Filtering** — Filter by log name, event type, model type, model ID, causer, date range, and description search
- **Changed attribute filter** — Select a model type, then filter by a specific attribute (e.g. only show logs where `name` changed)
- **Quick preview popover** — Hover on a row's info icon to see old/new value diff without leaving the list
- **Current attributes popover** — View the subject's live model data from the list
- **Model info sidebar** — When a model type is selected, a sidebar appears with model stats (total logs, unique records, table name, table size), event breakdown badges, and clickable attribute chips for quick filtering
- **Related model navigation** — Click through to view all logs for a related model instance
- **Detail view** — Color-coded old/new value diff, request data, device data, performance metrics (with Fast/Normal/Slow badges), app info, session info, execution context, and raw JSON view
- **Language switcher** — Toggle between available locales directly from the UI

### Attribute Translation

Throughout the UI, attribute names (database column names like `first_name`, `email_verified_at`) are automatically translated using Laravel's `validation.attributes` language file:

- If a translation exists in `validation.attributes.{key}` — displays the translated name with the original key in parentheses, e.g. **"First Name" (first_name)**
- If no translation exists — displays a "headline" version of the key, e.g. **"Email Verified At"** with the original key in small text

This applies to the changes table on the detail page, the model info sidebar attribute chips, and the statistics page's "Most Changed Attributes" section.

To add translations, define them in your `lang/{locale}/validation.php`:

```php
'attributes' => [
    'first_name' => 'First Name',
    'email' => 'Email Address',
    'created_at' => 'Creation Date',
],
```

## Statistics Dashboard

Visit `/activity-log/statistics` to access the statistics dashboard. The page loads each section independently via AJAX for fast initial rendering with skeleton loading states.

### Period Filter

A date range filter at the top applies to all sections. Select "From" and "To" dates and click "Apply" to filter. Click "Reset" to return to all-time data.

### Sections

The dashboard includes the following sections:

#### Overview Cards
Five summary cards showing:
- **Total Entries** — Total number of activity log records
- **Table Size** — Database table size (data + indexes)
- **Avg / Day** — Average number of entries per day
- **Oldest Entry** — Date of the first recorded activity
- **Latest Entry** — Date of the most recent activity

#### Peak Hour Chart
A 24-hour bar chart showing activity distribution by hour of day. The busiest hour is highlighted in orange. Hover over any bar to see the exact count.

#### Daily Activity
A bar chart showing activity over the last 30 days (or the selected date range). Hover for exact date and count.

#### Activity by Day of Week
A bar chart showing activity distribution across weekdays (Sunday through Saturday). The busiest day is highlighted. Uses localized weekday names.

#### Peak Times Summary
Three cards showing:
- **Busiest Hour** — The hour with the most activity (e.g. "2 PM")
- **Busiest Day** — The single date with the highest activity count
- **Busiest Month** — The month (YYYY-MM) with the most activity

#### Monthly Activity
A bar chart showing activity per month across all available data. The peak month is highlighted in orange.

#### System vs User Actions
A progress bar comparing activities performed by authenticated users vs system/automated actions (entries without a `causer_id`). Shows exact counts and percentages.

#### Events Breakdown
A ranked table showing each event type (`created`, `updated`, `deleted`, etc.) with count and a proportional bar. Events are color-coded with badges.

#### Log Names
A ranked table showing activity counts per log name (e.g. `default`, `auth`, `system`).

#### Top Models
A ranked table of the 10 most frequently logged model types (shown as class basenames).

#### Top Causers
A ranked table of the 10 most active causers. Resolves causer names from the database when possible (uses `name`, `email`, or `title` attributes).

#### Most Changed Attributes
A ranked table of the 30 most frequently changed attributes (from `updated` events). Scans the last 1000 update entries. The title shows the active search period or "All Time". Attribute names are translated using `validation.attributes` — showing the human-readable name with the original column name.

### Caching

Statistics responses are cached:
- **All-time queries**: cached for 120 seconds
- **Date-filtered queries**: cached for 60 seconds

Cache keys are namespaced per section and date range.

## Localization

The package ships with English and Arabic translations. The UI automatically adapts to RTL layout when the locale is `ar`.

Set the locale in your `config/app.php`:

```php
'locale' => 'ar',
```

Or switch at runtime:

```php
App::setLocale('ar');
```

The browse UI also includes a language switcher button that saves the preference in the session.

To customize translations, publish the language files:

```bash
php artisan vendor:publish --tag=activitylog-browse-lang
# Use --force to overwrite previously published files
php artisan vendor:publish --tag=activitylog-browse-lang --force
```

This copies the files to `lang/vendor/activitylog-browse/` where you can edit them or add new languages.

## Multi-Tenancy

This package works out of the box with [stancl/tenancy](https://tenancyforlaravel.com/) (multi-database tenancy). It automatically detects the active tenant and isolates cache keys, database connections, and migrations accordingly.

### How It Works

- **Cache isolation** — Cache keys are automatically prefixed with the tenant ID when a tenant is active (e.g. `activitylog-browse:t:1:stats:overview`). No shared cache between tenants.
- **Database connection** — All queries use the connection defined on your Activity model. In a multi-database tenancy setup, the tenant's connection is used automatically.
- **No extra dependencies** — Tenant detection uses `function_exists('tenant')`, so there's no hard dependency on any tenancy package.

### Setup for Multi-Database Tenancy

1. **Disable automatic migrations** so they don't run on the central database:

```php
// config/activitylog-browse.php
'load_migrations' => false,
```

2. **Publish migrations** to your tenant migration path:

```bash
php artisan vendor:publish --tag=activitylog-browse-migrations
```

Then move the published migration(s) to your tenant migrations directory (e.g. `database/migrations/tenant/`).

3. **Add tenancy middleware** to the browse UI routes:

```php
// config/activitylog-browse.php
'browse' => [
    'middleware' => ['web', 'auth', \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class],
],
```

### Without Tenancy

If you're not using multi-tenancy, everything works exactly as before — no configuration changes needed.

## Architecture

| Component | Role |
|---|---|
| `GlobalModelLogger` | Listens to global Eloquent events and logs activity for models without the `LogsActivity` trait |
| `ActivityEnrichmentObserver` | Observes the Activity model's `creating` event to merge all enrichment data into properties before save |
| `RequestDataCollector` | Gathers URL, method, route name, previous URL from the current request |
| `DeviceDataCollector` | Gathers IP, user agent, referrer from the current request |
| `PerformanceDataCollector` | Captures request duration, peak memory usage, and DB query count |
| `AppDataCollector` | Records environment, PHP version, and server hostname |
| `SessionDataCollector` | Identifies the authentication guard used |
| `ExecutionContextCollector` | Determines execution source (web/console/queue/schedule) and captures job/command names |
| `RelationDiscovery` | Uses reflection to auto-discover Eloquent relationships for related model browsing |
| `ActivityLogController` | Handles the browse UI with filtering, pagination, AJAX endpoints, statistics API, and attribute inspection |
| `SetLocale` | Middleware that applies the user's locale preference from the session |

## License

MIT
