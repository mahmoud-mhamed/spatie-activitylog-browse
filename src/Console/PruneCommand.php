<?php

namespace Mhamed\SpatieActivitylogBrowse\Console;

use Illuminate\Console\Command;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Mhamed\SpatieActivitylogBrowse\Support\RetentionPruner;

class PruneCommand extends Command
{
    protected $signature = 'activitylog-browse:prune
        {--dry-run : Report what would be deleted without making changes}
        {--age : Run only the age-based prune}
        {--size : Run only the size-based prune}
        {--bodies : Only strip request bodies older than request_data.body.retention.days (rows are kept)}
        {--optimize : Only rebuild the table (OPTIMIZE TABLE) if a cleanup left reclaimable space}';

    protected $description = 'Prune activity log entries based on retention config (age + size limits)';

    public function handle(RetentionPruner $pruner): int
    {
        if ($this->option('bodies')) {
            return $this->pruneBodies($pruner);
        }

        if ($this->option('optimize')) {
            return $this->optimizePending();
        }

        if (! config('activitylog-browse.retention.enabled', false)) {
            $this->warn('Retention is disabled in config (activitylog-browse.retention.enabled).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $onlyAge = (bool) $this->option('age');
        $onlySize = (bool) $this->option('size');

        $isScheduled = app()->bound(\Illuminate\Console\Scheduling\Schedule::class)
            && in_array('schedule:run', (array) ($_SERVER['argv'] ?? []), true);
        $pruner->setTrigger($isScheduled ? 'schedule' : 'cli');

        $this->info($dryRun ? 'Dry run — no rows will be deleted.' : 'Pruning activity log...');

        // skipAge => --size only ; skipSize => --age only
        $result = $pruner->prune($dryRun, skipAge: $onlySize, skipSize: $onlyAge);

        if (! $onlySize) {
            $this->line("  By age:  {$result['by_age']} rows");
        }
        if (! $onlyAge) {
            $this->line("  By size: {$result['by_size']} rows");
        }

        $this->info("Done. Total: {$result['total']} rows" . ($dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }

    protected function optimizePending(): int
    {
        $since = ActivityLogHelpers::optimizePendingSince();
        if ($since === null) {
            $this->info('No cleanup is waiting for a table rebuild.');

            return self::SUCCESS;
        }

        $this->info("Rebuilding the activity table (cleanup at {$since})...");

        try {
            $rebuilt = ActivityLogHelpers::rebuildTable();
        } catch (\Throwable $e) {
            // Keep it pending: the next scheduled run retries.
            report($e);
            $this->error('OPTIMIZE TABLE failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        ActivityLogHelpers::clearOptimizePending();
        $this->info($rebuilt ? 'Done: freed space returned to the database.' : 'Skipped: this database driver has no OPTIMIZE TABLE.');

        return self::SUCCESS;
    }

    protected function pruneBodies(RetentionPruner $pruner): int
    {
        if (! (config('activitylog-browse.request_data.body.retention.enabled') ?? false)) {
            $this->warn('Request body retention is disabled (activitylog-browse.request_data.body.retention.enabled).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $stripped = $pruner->pruneRequestBodies($dryRun);
        $this->info("Request bodies stripped: {$stripped} rows" . ($dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }
}
