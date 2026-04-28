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
