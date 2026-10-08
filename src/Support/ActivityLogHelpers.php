<?php

namespace Mhamed\SpatieActivitylogBrowse\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Activitylog\ActivitylogServiceProvider;

class ActivityLogHelpers
{
    private static ?bool $hasRequestIdColumn = null;

    public static function cachePrefix(): string
    {
        if (function_exists('tenant') && tenant()) {
            return 'activitylog-browse:t:' . tenant()->getTenantKey();
        }

        return 'activitylog-browse';
    }

    public static function activityConnection(): string
    {
        $model = ActivitylogServiceProvider::determineActivityModel();

        return (new $model)->getConnectionName() ?? config('database.default');
    }

    public static function activityModel(): \Illuminate\Database\Eloquent\Model
    {
        $model = ActivitylogServiceProvider::determineActivityModel();

        return new $model;
    }

    public static function tableName(): string
    {
        return config('activitylog.table_name', 'activity_log');
    }

    /**
     * Whether the `request_id` migration has run. Writing the column before it
     * exists would make every activity insert (and the model save behind it) fail.
     */
    public static function hasRequestIdColumn(): bool
    {
        if (self::$hasRequestIdColumn !== null) {
            return self::$hasRequestIdColumn;
        }

        try {
            // The model's own table/connection: a custom activity model may override config.
            $model = self::activityModel();

            return self::$hasRequestIdColumn = Schema::connection($model->getConnectionName())
                ->hasColumn($model->getTable(), 'request_id');
        } catch (\Throwable $e) {
            // Not cached: a transient DB error shouldn't disable grouping for the whole process.
            report($e);

            return false;
        }
    }

    public static function clearStatsCache(bool $optimize = true): void
    {
        $prefix = self::cachePrefix();

        $sections = ['overview', 'events', 'log_names', 'models', 'causers', 'daily', 'hourly', 'weekday', 'system_user', 'attributes', 'monthly', 'peak_day'];

        foreach ($sections as $section) {
            Cache::forget("{$prefix}:stats:{$section}");
        }

        $filterColumns = ['log_name', 'event', 'subject_type', 'causer_type'];
        foreach ($filterColumns as $column) {
            Cache::forget("{$prefix}:{$column}");
        }

        if ($optimize) {
            self::optimizeTable();
        }
    }

    public static function optimizeTable(): void
    {
        try {
            DB::connection(self::activityConnection())
                ->statement('OPTIMIZE TABLE `' . self::tableName() . '`');
        } catch (\Throwable) {
        }
    }

    /**
     * Stripping JSON from rows frees space inside InnoDB pages but neither shrinks the
     * file nor the reported table size; only a rebuild (OPTIMIZE TABLE) does. A cleanup
     * marks it pending and the scheduler rebuilds at night instead of mid-day.
     */
    public static function markOptimizePending(): void
    {
        try {
            Cache::forever(self::cachePrefix() . ':optimize_pending', now()->toDateTimeString());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** When the pending rebuild was requested, or null when there is none. */
    public static function optimizePendingSince(): ?string
    {
        try {
            return Cache::get(self::cachePrefix() . ':optimize_pending');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public static function clearOptimizePending(): void
    {
        try {
            Cache::forget(self::cachePrefix() . ':optimize_pending');
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * Manual "reclaim space now" from the cleanup page: the rebuild runs after the
     * response is sent (no gateway timeout); the page polls rebuildState() meanwhile.
     *
     * @return array{running: bool, error: ?string}
     */
    public static function rebuildState(): array
    {
        try {
            return [
                'running' => (bool) Cache::get(self::cachePrefix() . ':rebuild_running'),
                'error' => Cache::get(self::cachePrefix() . ':rebuild_error'),
            ];
        } catch (\Throwable $e) {
            report($e);

            return ['running' => false, 'error' => null];
        }
    }

    public static function rebuildInBackground(): void
    {
        $prefix = self::cachePrefix();
        // TTL so a killed process can't leave the button disabled forever.
        Cache::put("{$prefix}:rebuild_running", true, now()->addHour());
        Cache::forget("{$prefix}:rebuild_error");

        app()->terminating(function () use ($prefix) {
            ignore_user_abort(true);
            set_time_limit(0);

            try {
                self::rebuildTable();
                self::clearOptimizePending();
            } catch (\Throwable $e) {
                report($e);
                Cache::put("{$prefix}:rebuild_error", Str::limit($e->getMessage(), 300), now()->addDay());
            } finally {
                Cache::forget("{$prefix}:rebuild_running");
            }
        });
    }

    /**
     * Rebuild the activity table to give freed space back (MySQL/MariaDB only; InnoDB
     * runs it as an online rebuild). Returns false when the driver has no equivalent.
     * Throws on failure so the caller can keep the request pending.
     */
    public static function rebuildTable(): bool
    {
        $model = self::activityModel();
        $connection = $model->getConnection();

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return false;
        }

        self::compactJsonDocuments($model);
        $connection->statement('OPTIMIZE TABLE `' . $connection->getTablePrefix() . $model->getTable() . '`');

        return true;
    }

    /**
     * MySQL keeps space freed by partial JSON updates inside each document
     * (JSON_STORAGE_FREE), and OPTIMIZE TABLE copies documents as they are. Rewrite
     * those documents so the rebuild can actually shrink the table. MySQL skips an
     * UPDATE whose JSON value is unchanged, so each batch adds then removes a marker
     * key with JSON_MERGE_PATCH (a full write, freshly serialized), in a transaction.
     * MariaDB stores JSON as text and has no partial updates: nothing to do there.
     */
    protected static function compactJsonDocuments(\Illuminate\Database\Eloquent\Model $model): void
    {
        $connection = $model->getConnection();
        if (str_contains(strtolower((string) $connection->getPdo()->getAttribute(\PDO::ATTR_SERVER_VERSION)), 'mariadb')) {
            return;
        }

        $table = $model->getTable();
        $marker = '__activitylog_compact';

        do {
            set_time_limit(0);
            $ids = $connection->table($table)
                ->whereRaw("JSON_TYPE(properties) = 'OBJECT' AND JSON_STORAGE_FREE(properties) > 0")
                ->orderBy('id')
                ->limit(2000)
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                $connection->transaction(function () use ($connection, $table, $ids, $marker) {
                    $connection->table($table)->whereIn('id', $ids)
                        ->update(['properties' => $connection->raw("JSON_MERGE_PATCH(properties, '{\"{$marker}\": 1}')")]);
                    $connection->table($table)->whereIn('id', $ids)
                        ->update(['properties' => $connection->raw("JSON_MERGE_PATCH(properties, '{\"{$marker}\": null}')")]);
                });
            }
        } while ($ids->count() === 2000);
    }

    public static function tableSizeBytes(): ?int
    {
        try {
            $conn = DB::connection(self::activityConnection());
            $table = self::tableName();
            $conn->statement("ANALYZE TABLE `{$table}`");
            try {
                // MySQL 8+ caches information_schema sizes for a day: read fresh values right after a rebuild.
                $conn->statement('SET SESSION information_schema_stats_expiry = 0');
            } catch (\Throwable) {
                // MariaDB / MySQL 5.7 have no such cache.
            }
            $result = $conn->selectOne(
                'SELECT (data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ? AND table_name = ?',
                [$conn->getDatabaseName(), $table]
            );

            return $result?->size ? (int) $result->size : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
