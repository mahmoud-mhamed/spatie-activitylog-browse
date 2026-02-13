# Activity Log Browse

A Laravel package that extends [spatie/laravel-activitylog](https://github.com/spatie/laravel-activitylog) v4 with:

- **Auto-log all models** — Automatically log created/updated/deleted events for ALL Eloquent models without adding the `LogsActivity` trait
- **Request & device enrichment** — Attach URL, IP, user agent, referrer, and more to every activity log entry
- **Browse UI** — Web interface to view, filter, and inspect activity logs
- **Localization** — Built-in support for English and Arabic (RTL)

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
```

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

The config file `config/activitylog-browse.php` has four sections:

### Auto-Log

```php
'auto_log' => [
    'enabled' => true,
    'events' => ['created', 'updated', 'deleted'],
    'models' => '*',              // '*' = all models, or array of specific classes
    'excluded_models' => [],
    'log_name' => 'default',
    'log_only_dirty' => true,
    'excluded_attributes' => ['password', 'remember_token'],
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

Both collectors gracefully return empty arrays when running in console/queue context.

### Browse UI

```php
'browse' => [
    'enabled' => true,
    'prefix' => 'activity-log',
    'middleware' => ['web', 'auth'],
    'per_page' => 25,
    'gate' => null,
],
```

Set `gate` to a gate name to restrict access (e.g. `'gate' => 'view-activity-log'`).

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

To customize translations, publish the language files:

```bash
php artisan vendor:publish --tag=activitylog-browse-lang
```

This copies the files to `lang/vendor/activitylog-browse/` where you can edit them or add new languages.

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

Every activity log entry (including those from the `LogsActivity` trait or manual `activity()` calls) is enriched with request and device data:

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
    }
}
```

### Browse UI

Visit `/activity-log` (or your configured prefix) to browse logs. The UI provides:

- Filterable list by log name, event type, model, model ID, causer, date range, and description search
- Changed attribute filter — select a model, then filter by specific attribute (e.g. only show logs where `name` changed)
- Quick preview popover on each row showing old/new value diff
- Current attributes popover on each subject showing live model data
- Link to view all logs for a specific model instance
- Detail view with color-coded old/new value diff
- Request and device data sections
- Raw JSON view

## How It Works

| Component                    | Role                                                                                                       |
|------------------------------|------------------------------------------------------------------------------------------------------------|
| `GlobalModelLogger`          | Listens to global Eloquent events and logs activity for models without the `LogsActivity` trait             |
| `ActivityEnrichmentObserver` | Observes the Activity model's `creating` event to merge request/device data into properties before save     |
| `RequestDataCollector`       | Gathers URL, method, route name from the current request                                                   |
| `DeviceDataCollector`        | Gathers IP, user agent, referrer from the current request                                                  |
| `ActivityLogController`      | Handles the browse UI with filtering, pagination, and attribute AJAX endpoint                               |

## License

MIT
