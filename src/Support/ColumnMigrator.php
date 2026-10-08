<?php

namespace Mhamed\SpatieActivitylogBrowse\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ColumnMigrator
{
    /** Seconds an ALTER may wait for a metadata lock before giving up (MySQL/MariaDB). */
    private const LOCK_WAIT_SECONDS = 3;

    /**
     * Add the `request_id` column and its index if they are missing. Idempotent.
     *
     * On MySQL a short lock_wait_timeout makes a busy table fail fast instead of
     * queueing every activity insert (and the app saves behind them) behind the
     * ALTER. Throws on failure; callers decide whether that is fatal.
     */
    public static function ensureRequestIdColumn(): void
    {
        // Same table/connection the observer writes to (a custom activity model may override config).
        $model = ActivityLogHelpers::activityModel();
        $table = $model->getTable();
        $schema = Schema::connection($model->getConnectionName());

        if (! $schema->hasTable($table)) {
            return;
        }

        $connection = $schema->getConnection();
        $prefixed = $connection->getTablePrefix() . $table;
        $index = "{$table}_request_id_index";

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            if (! $schema->hasColumn($table, 'request_id')) {
                $schema->table($table, fn ($t) => $t->string('request_id', 36)->nullable()->index());
            }
            self::clearRequestIdFailure();

            return;
        }

        $previousWait = $connection->selectOne('SELECT @@SESSION.lock_wait_timeout AS value')->value;
        $connection->statement('SET SESSION lock_wait_timeout = ' . self::LOCK_WAIT_SECONDS);

        try {
            if (! $schema->hasColumn($table, 'request_id')) {
                $connection->statement("ALTER TABLE `{$prefixed}` ADD COLUMN `request_id` VARCHAR(36) NULL");
            }

            $hasIndex = $connection->selectOne(
                'SELECT COUNT(*) AS total FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
                [$prefixed, $index]
            )->total > 0;

            if (! $hasIndex) {
                // Online index build: reads and writes continue while it runs.
                $connection->statement("ALTER TABLE `{$prefixed}` ADD INDEX `{$index}` (`request_id`), ALGORITHM=INPLACE, LOCK=NONE");
            }
        } finally {
            $connection->statement('SET SESSION lock_wait_timeout = ' . (int) $previousWait);
        }

        self::clearRequestIdFailure();
    }

    /**
     * ensureRequestIdColumn() that never throws: used by the migration and the
     * scheduled retry so a failure can't abort a deploy or the scheduler. The
     * error is reported and kept for the alert on the browse page.
     */
    public static function tryEnsureRequestIdColumn(): bool
    {
        try {
            self::ensureRequestIdColumn();

            return true;
        } catch (\Throwable $e) {
            report($e);

            try {
                Cache::forever(self::failureCacheKey(), [
                    'message' => Str::limit($e->getMessage(), 500),
                    'at' => now()->toDateTimeString(),
                ]);
            } catch (\Throwable $cacheError) {
                report($cacheError);
            }

            return false;
        }
    }

    /**
     * For the cleanup page: does the column exist, is it indexed (null when the
     * driver can't tell), and the last failed attempt to add it.
     *
     * @return array{column: bool, index: ?bool, failure: ?array}
     */
    public static function requestIdStatus(): array
    {
        try {
            $model = ActivityLogHelpers::activityModel();
            $table = $model->getTable();
            $schema = Schema::connection($model->getConnectionName());
            $column = $schema->hasColumn($table, 'request_id');
            $index = $column && method_exists($schema, 'getIndexes')
                ? collect($schema->getIndexes($table))->contains(fn ($i) => in_array('request_id', (array) ($i['columns'] ?? []), true))
                : null;
        } catch (\Throwable $e) {
            report($e);
            $column = false;
            $index = null;
        }

        return ['column' => $column, 'index' => $index, 'failure' => self::lastRequestIdFailure()];
    }

    /** @return array{message: string, at: string}|null */
    public static function lastRequestIdFailure(): ?array
    {
        try {
            return Cache::get(self::failureCacheKey());
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private static function clearRequestIdFailure(): void
    {
        try {
            Cache::forget(self::failureCacheKey());
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private static function failureCacheKey(): string
    {
        return ActivityLogHelpers::cachePrefix() . ':request_id_column_failure';
    }

    /**
     * Fix subject_id and causer_id columns to support UUIDs (VARCHAR instead of BIGINT).
     * Safe to run multiple times — only modifies columns that need changing.
     */
    public static function fixMorphIdColumns(): bool
    {
        $table = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable($table)) {
            return false;
        }

        $changed = false;

        foreach (['subject_id', 'causer_id'] as $column) {
            if (! $schema->hasColumn($table, $column)) {
                continue;
            }

            $type = $schema->getColumnType($table, $column);

            if (in_array($type, ['string', 'text', 'guid'])) {
                continue;
            }

            $driver = $schema->getConnection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'])) {
                DB::connection($connection)->statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(36) NULL");
            } else {
                $schema->table($table, function ($t) use ($column) {
                    $t->string($column, 36)->nullable()->change();
                });
            }

            $changed = true;
        }

        return $changed;
    }
}
