<?php

namespace Mhamed\SpatieActivitylogBrowse\Support;

use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\ActivitylogServiceProvider;

class RetentionPruner
{
    public const FOREVER = 'forever';

    /** @var array<string, int|string> */
    protected array $perModel;

    /** @var array<string, int|string> */
    protected array $perLogName;

    protected int $defaultDays;
    protected ?int $maxRows;
    protected ?int $maxSizeMb;
    protected int $chunkSize;
    protected bool $optimizeAfter;

    protected string $trigger = 'manual';

    public function setTrigger(string $trigger): self
    {
        $this->trigger = $trigger;

        return $this;
    }

    public function __construct()
    {
        $this->defaultDays   = (int) config('activitylog-browse.retention.default_days', 90);
        $this->maxRows       = config('activitylog-browse.retention.max_rows');
        $this->maxSizeMb     = config('activitylog-browse.retention.max_size_mb');
        $this->perModel      = (array) config('activitylog-browse.retention.per_model', []);
        $this->perLogName    = (array) config('activitylog-browse.retention.per_log_name', []);
        $this->chunkSize     = max(100, (int) config('activitylog-browse.retention.chunk_size', 1000));
        $this->optimizeAfter = (bool) config('activitylog-browse.retention.optimize_after', true);
    }

    /**
     * Run a full prune cycle (age + size) and return a breakdown.
     *
     * @return array{by_age:int, by_size:int, total:int, dry_run:bool}
     */
    public function prune(bool $dryRun = false, bool $skipAge = false, bool $skipSize = false): array
    {
        $start = microtime(true);
        $rowsBefore = $this->newQuery()->count();
        $sizeBefore = ActivityLogHelpers::tableSizeBytes();

        $byAge  = $skipAge  ? 0 : $this->pruneByAge($dryRun);
        $bySize = $skipSize ? 0 : $this->pruneBySize($dryRun);

        if (! $dryRun && ($byAge > 0 || $bySize > 0)) {
            ActivityLogHelpers::clearStatsCache($this->optimizeAfter);
        }

        $total = $byAge + $bySize;

        $operation = match (true) {
            $skipAge && ! $skipSize => 'retention_size',
            $skipSize && ! $skipAge => 'retention_age',
            default                 => 'retention',
        };

        $this->logDeletion([
            'operation'      => $operation,
            'deleted_count'  => $total,
            'breakdown'      => ['by_age' => $byAge, 'by_size' => $bySize],
            'duration_ms'    => round((microtime(true) - $start) * 1000, 2),
            'dry_run'        => $dryRun,
            'rows_before'    => $rowsBefore,
            'rows_after'     => $dryRun ? $rowsBefore : $this->newQuery()->count(),
            'size_mb_before' => $sizeBefore !== null ? round($sizeBefore / 1048576, 2) : null,
            'size_mb_after'  => $dryRun ? ($sizeBefore !== null ? round($sizeBefore / 1048576, 2) : null) : self::sizeMb(ActivityLogHelpers::tableSizeBytes()),
        ], $total);

        return [
            'by_age'  => $byAge,
            'by_size' => $bySize,
            'total'   => $total,
            'dry_run' => $dryRun,
        ];
    }

    protected function logDeletion(array $payload, int $total): void
    {
        if ($total === 0) {
            return;
        }

        DeletionLogger::record(array_merge([
            'trigger'         => $this->trigger,
            'config_snapshot' => [
                'default_days'    => $this->defaultDays,
                'max_rows'        => $this->maxRows,
                'max_size_mb'     => $this->maxSizeMb,
                'per_model_count' => count($this->perModel),
                'per_log_count'   => count($this->perLogName),
                'forever_models'  => count($this->foreverModels()),
            ],
            'context' => self::buildContext(),
        ], $payload));
    }

    protected static function buildContext(): array
    {
        $context = [];

        try {
            if (function_exists('auth') && auth()->check()) {
                $user = auth()->user();
                $context['user_id'] = $user->getAuthIdentifier();
                $context['user_name'] = $user->name ?? null;
            }
        } catch (\Throwable) {
        }

        try {
            if (function_exists('request') && request()->ip()) {
                $context['ip'] = request()->ip();
            }
        } catch (\Throwable) {
        }

        if (app()->runningInConsole() && isset($_SERVER['argv'][1])) {
            $context['command'] = $_SERVER['argv'][1];
        }

        return $context;
    }

    protected static function sizeMb(?int $bytes): ?float
    {
        return $bytes === null ? null : round($bytes / 1048576, 2);
    }

    /**
     * Delete records older than their configured retention.
     */
    public function pruneByAge(bool $dryRun = false): int
    {
        $deleted = 0;

        // 1) Default rule for everything not explicitly overridden.
        $excluded = $this->modelsWithOverrides();
        $query = $this->newQuery()
            ->where('created_at', '<', now()->subDays($this->defaultDays));

        if (! empty($excluded)) {
            $query->whereNotIn('subject_type', $excluded);
        }

        // Skip log_names that have their own rule — handled separately.
        $logNamesWithRules = array_keys($this->perLogName);
        if (! empty($logNamesWithRules)) {
            $query->whereNotIn('log_name', $logNamesWithRules);
        }

        $deleted += $this->executeDelete($query, $dryRun);

        // 2) Per-model overrides.
        foreach ($this->perModel as $modelClass => $rule) {
            if ($this->isForever($rule)) {
                continue;
            }

            $days = (int) $rule;
            $modelQuery = $this->newQuery()
                ->where('subject_type', $modelClass)
                ->where('created_at', '<', now()->subDays($days));

            $deleted += $this->executeDelete($modelQuery, $dryRun);
        }

        // 3) Per-log-name overrides.
        foreach ($this->perLogName as $logName => $rule) {
            if ($this->isForever($rule)) {
                continue;
            }

            $days = (int) $rule;
            $logQuery = $this->newQuery()
                ->where('log_name', $logName)
                ->where('created_at', '<', now()->subDays($days));

            // Avoid double-counting models already pruned by per_model rule.
            $foreverModels = $this->foreverModels();
            if (! empty($foreverModels)) {
                $logQuery->whereNotIn('subject_type', $foreverModels);
            }

            $deleted += $this->executeDelete($logQuery, $dryRun);
        }

        return $deleted;
    }

    /**
     * Enforce max_rows / max_size_mb caps by deleting oldest records first.
     *
     * Per-model and per-log-name rules ALWAYS win over size limits:
     *   - 'forever'  : records are never deleted by size pruning
     *   - int days   : records younger than the configured days are protected,
     *                  even if the table is over its size cap
     *
     * If protected records cover the whole table, the size cap becomes
     * best-effort and nothing is deleted.
     *
     * Implementation note: we compute the target row count up front (using
     * avg bytes/row to translate max_size_mb into a row target) instead of
     * re-measuring table size during the loop. InnoDB does not reclaim
     * tablespace until OPTIMIZE TABLE runs, so information_schema would
     * keep reporting the old size mid-loop and we'd never converge.
     */
    public function pruneBySize(bool $dryRun = false): int
    {
        if ($this->maxRows === null && $this->maxSizeMb === null) {
            return 0;
        }

        $rowsToDelete = $this->estimateRowsToDelete();
        if ($rowsToDelete <= 0) {
            return 0;
        }

        // Dry-run: report the realistic count, capped by what's actually eligible.
        if ($dryRun) {
            $eligible = $this->buildSizePruneQuery()->count();

            return min($rowsToDelete, $eligible);
        }

        $deleted = 0;
        while ($deleted < $rowsToDelete) {
            $remaining = $rowsToDelete - $deleted;
            $thisChunk = min($this->chunkSize, $remaining);

            $query = $this->buildSizePruneQuery();
            $ids = (clone $query)->limit($thisChunk)->pluck('id');

            if ($ids->isEmpty()) {
                // Either nothing left, or every remaining record is protected
                // by a per-model / per-log-name rule. Stop — size cap becomes
                // best-effort.
                break;
            }

            set_time_limit(30);
            $deleted += $this->newQuery()->whereIn('id', $ids)->delete();
        }

        return $deleted;
    }

    /**
     * Compute how many rows to delete so the table fits both caps.
     */
    protected function estimateRowsToDelete(): int
    {
        $currentRows = $this->newQuery()->count();
        if ($currentRows <= 0) {
            return 0;
        }

        $targets = [];

        if ($this->maxRows !== null) {
            $targets[] = (int) $this->maxRows;
        }

        if ($this->maxSizeMb !== null) {
            $bytes = ActivityLogHelpers::tableSizeBytes();
            if ($bytes !== null && $bytes > 0) {
                $avgBytesPerRow = $bytes / $currentRows;
                $targetBytes = $this->maxSizeMb * 1024 * 1024;
                if ($avgBytesPerRow > 0) {
                    $targets[] = (int) floor($targetBytes / $avgBytesPerRow);
                }
            }
        }

        if (empty($targets)) {
            return 0;
        }

        // Stricter cap wins.
        $targetRows = max(0, min($targets));

        return max(0, $currentRows - $targetRows);
    }

    /**
     * Build the query used by size-based pruning, applying per-model and
     * per-log-name protections so they always win over size limits.
     */
    protected function buildSizePruneQuery(): Builder
    {
        $query = $this->newQuery()->orderBy('created_at')->orderBy('id');

        // Per-model protections.
        foreach ($this->perModel as $modelClass => $rule) {
            if ($this->isForever($rule)) {
                $query->where(function (Builder $q) use ($modelClass) {
                    $q->where('subject_type', '!=', $modelClass)
                        ->orWhereNull('subject_type');
                });
                continue;
            }

            $cutoff = now()->subDays((int) $rule);
            // For this model, only records older than the cutoff are eligible.
            $query->where(function (Builder $q) use ($modelClass, $cutoff) {
                $q->where('subject_type', '!=', $modelClass)
                    ->orWhereNull('subject_type')
                    ->orWhere('created_at', '<', $cutoff);
            });
        }

        // Per-log-name protections.
        foreach ($this->perLogName as $logName => $rule) {
            if ($this->isForever($rule)) {
                $query->where(function (Builder $q) use ($logName) {
                    $q->where('log_name', '!=', $logName)
                        ->orWhereNull('log_name');
                });
                continue;
            }

            $cutoff = now()->subDays((int) $rule);
            $query->where(function (Builder $q) use ($logName, $cutoff) {
                $q->where('log_name', '!=', $logName)
                    ->orWhereNull('log_name')
                    ->orWhere('created_at', '<', $cutoff);
            });
        }

        return $query;
    }

    protected function executeDelete(Builder $query, bool $dryRun): int
    {
        if ($dryRun) {
            return $query->count();
        }

        $deleted = 0;
        do {
            set_time_limit(30);
            $ids = (clone $query)->limit($this->chunkSize)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted += $this->newQuery()->whereIn('id', $ids)->delete();
        } while (true);

        return $deleted;
    }

    /**
     * Strip `request_data.body` from activities older than
     * `request_data.body.retention.days`, keeping the rows themselves: the body is
     * by far the largest part of an enriched row. Returns how many rows were (or,
     * for a dry run, would be) stripped. Disabled unless the retention is enabled.
     */
    public function pruneRequestBodies(bool $dryRun = false): int
    {
        $config = config('activitylog-browse.request_data.body.retention');
        $days = (int) (is_array($config) ? ($config['days'] ?? 0) : 0);

        if (! is_array($config) || ! ($config['enabled'] ?? false) || $days <= 0) {
            return 0;
        }

        if ($dryRun) {
            return $this->stripRequestBodies($days, PHP_INT_MAX, true);
        }

        $stripped = 0;
        do {
            set_time_limit(30);
            $batch = $this->stripRequestBodies($days, $this->chunkSize);
            $stripped += $batch;
        } while ($batch === $this->chunkSize);

        if ($stripped > 0) {
            ActivityLogHelpers::markOptimizePending();
        }

        return $stripped;
    }

    /**
     * Strip `request_data.body` from at most $limit rows older than $days (0 = all).
     * Stripped rows stop matching, so callers repeat until it returns less than $limit.
     */
    public function stripRequestBodies(int $days, int $limit, bool $dryRun = false): int
    {
        return $this->stripProperties(
            fn (Builder $query) => $days > 0 ? $query->where('created_at', '<', now()->subDays($days)) : $query,
            ['$.request_data.body'],
            ['$.request_data.body'],
            $limit,
            $dryRun
        );
    }

    /** Total size in bytes of the stored bodies older than $days (0 = all); null if the driver can't tell. */
    public function requestBodyBytes(int $days): ?int
    {
        $query = $this->newQuery();
        $driver = $query->getConnection()->getDriverName();
        $body = match ($driver) {
            'mysql', 'mariadb' => "JSON_EXTRACT(properties, '$.request_data.body')",
            'sqlite' => "json_extract(properties, '$.request_data.body')",
            'pgsql' => "(properties::jsonb #> '{request_data,body}')::text",
            default => null,
        };

        if (! $body) {
            return null;
        }

        if ($days > 0) {
            $query->where('created_at', '<', now()->subDays($days));
        }

        return (int) $query->toBase()->sum($query->getConnection()->raw("LENGTH({$body})"));
    }

    /**
     * Remove the placeholder request/device data that queue jobs, scheduled tasks and
     * commands recorded before request/device enrichment became HTTP-only (APP_URL, GET,
     * 127.0.0.1, "Symfony"); queue rows also lose their worker-wide performance numbers.
     * Marker: request_data/device_data on a non-web row, so rows logged after the fix
     * (which carry correct per-job performance) are never touched. At most $limit rows.
     */
    public function stripPlaceholderData(int $limit, bool $dryRun = false): int
    {
        $markers = ['$.request_data', '$.device_data'];
        $source = fn (string $driver, string $condition) => fn (Builder $query) => $query->whereRaw($this->jsonText($driver, '$.execution_context.source') . $condition);
        $driver = $this->newQuery()->getConnection()->getDriverName();

        $queue = fn (int $max, bool $dry) => $this->stripProperties($source($driver, " = 'queue'"), $markers, [...$markers, '$.performance_data'], $max, $dry);
        $commands = fn (int $max, bool $dry) => $this->stripProperties($source($driver, " IN ('schedule', 'console')"), $markers, $markers, $max, $dry);

        if ($dryRun) {
            return $queue(PHP_INT_MAX, true) + $commands(PHP_INT_MAX, true);
        }

        $stripped = $queue($limit, false);

        return $stripped < $limit ? $stripped + $commands($limit - $stripped, false) : $stripped;
    }

    /**
     * Remove JSON paths from `properties` (rows kept, model events bypassed) on at most
     * $limit rows matching $scope that contain any of $markerPaths. A dry run counts all
     * matching rows. Paths are fixed internal literals, never user input.
     */
    protected function stripProperties(callable $scope, array $markerPaths, array $removePaths, int $limit, bool $dryRun): int
    {
        $query = $this->newQuery();
        $connection = $query->getConnection();
        $driver = $connection->getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb', 'sqlite', 'pgsql'], true)) {
            return 0;
        }

        $scope($query);
        $query->where(function (Builder $q) use ($driver, $markerPaths) {
            foreach ($markerPaths as $path) {
                $q->orWhereRaw($this->jsonHas($driver, $path));
            }
        });

        if ($dryRun) {
            return $query->count();
        }

        $ids = $query->orderBy('id')->limit($limit)->pluck('id');
        if ($ids->isEmpty()) {
            return 0;
        }

        return $this->newQuery()->whereIn('id', $ids)->toBase()
            ->update(['properties' => $connection->raw($this->jsonRemove($driver, $removePaths))]);
    }

    protected function jsonHas(string $driver, string $path): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => "JSON_CONTAINS_PATH(properties, 'one', '{$path}')",
            'sqlite' => "json_extract(properties, '{$path}') IS NOT NULL",
            'pgsql' => '(properties::jsonb #> ' . $this->pgPath($path) . ') IS NOT NULL',
        };
    }

    protected function jsonText(string $driver, string $path): string
    {
        return match ($driver) {
            'mysql', 'mariadb' => "JSON_UNQUOTE(JSON_EXTRACT(properties, '{$path}'))",
            'sqlite' => "json_extract(properties, '{$path}')",
            'pgsql' => '(properties::jsonb #>> ' . $this->pgPath($path) . ')',
            default => 'NULL',
        };
    }

    protected function jsonRemove(string $driver, array $paths): string
    {
        $quoted = implode(', ', array_map(fn ($path) => "'{$path}'", $paths));

        return match ($driver) {
            // A bare `col = JSON_REMOVE(col, ...)` is a MySQL partial in-place update: the
            // document keeps its old size (JSON_STORAGE_FREE) and no rebuild reclaims it.
            // Wrapping it makes MySQL write a freshly serialized, compact document.
            'mysql', 'mariadb' => "JSON_MERGE_PATCH(JSON_REMOVE(properties, {$quoted}), '{}')",
            'sqlite' => "json_remove(properties, {$quoted})",
            'pgsql' => '(properties::jsonb ' . implode(' ', array_map(fn ($path) => '#- ' . $this->pgPath($path), $paths)) . ')::json',
        };
    }

    /** '$.request_data.body' → '{request_data,body}' */
    protected function pgPath(string $path): string
    {
        return "'{" . str_replace('.', ',', substr($path, 2)) . "}'";
    }

    protected function newQuery(): Builder
    {
        $model = ActivitylogServiceProvider::determineActivityModel();

        return $model::query();
    }

    /** @return array<int, string> */
    protected function modelsWithOverrides(): array
    {
        return array_keys($this->perModel);
    }

    /** @return array<int, string> */
    protected function foreverModels(): array
    {
        return array_keys(array_filter(
            $this->perModel,
            fn($rule) => $this->isForever($rule)
        ));
    }

    protected function isForever(int|string $rule): bool
    {
        return is_string($rule) && strtolower($rule) === self::FOREVER;
    }
}
