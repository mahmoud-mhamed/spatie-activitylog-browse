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
    public function prune(bool $dryRun = false): array
    {
        $byAge  = $this->pruneByAge($dryRun);
        $bySize = $this->pruneBySize($dryRun);

        if (! $dryRun && ($byAge > 0 || $bySize > 0)) {
            ActivityLogHelpers::clearStatsCache($this->optimizeAfter);
        }

        return [
            'by_age'  => $byAge,
            'by_size' => $bySize,
            'total'   => $byAge + $bySize,
            'dry_run' => $dryRun,
        ];
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
     */
    public function pruneBySize(bool $dryRun = false): int
    {
        if ($this->maxRows === null && $this->maxSizeMb === null) {
            return 0;
        }

        $deleted = 0;
        $checkSizeEvery = 10; // re-measure table size every N chunks
        $chunkCounter = 0;

        while ($this->isOverLimit($chunkCounter % $checkSizeEvery === 0)) {
            $query = $this->buildSizePruneQuery();

            $ids = (clone $query)->limit($this->chunkSize)->pluck('id');
            if ($ids->isEmpty()) {
                // Either the table is empty, or every remaining record is
                // protected by a per-model / per-log-name rule. Stop.
                break;
            }

            if ($dryRun) {
                $deleted += $ids->count();
                // In dry-run we can't actually shrink the table, so stop after
                // estimating one chunk worth — otherwise infinite loop.
                break;
            }

            set_time_limit(30);
            $deleted += $this->newQuery()->whereIn('id', $ids)->delete();
            $chunkCounter++;
        }

        return $deleted;
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

    protected function isOverLimit(bool $remeasureSize): bool
    {
        if ($this->maxRows !== null) {
            $rows = $this->newQuery()->count();
            if ($rows > $this->maxRows) {
                return true;
            }
        }

        if ($this->maxSizeMb !== null && $remeasureSize) {
            $bytes = ActivityLogHelpers::tableSizeBytes();
            if ($bytes !== null && $bytes > $this->maxSizeMb * 1024 * 1024) {
                return true;
            }
        }

        return false;
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
