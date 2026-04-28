<?php

namespace Mhamed\SpatieActivitylogBrowse\Console;

use Illuminate\Console\Command;
use Mhamed\SpatieActivitylogBrowse\Support\RetentionPruner;

class PruneCommand extends Command
{
    protected $signature = 'activitylog-browse:prune
        {--dry-run : Report what would be deleted without making changes}
        {--age : Run only the age-based prune}
        {--size : Run only the size-based prune}';

    protected $description = 'Prune activity log entries based on retention config (age + size limits)';

    public function handle(RetentionPruner $pruner): int
    {
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

        $byAge = 0;
        $bySize = 0;

        if (! $onlySize) {
            $byAge = $pruner->pruneByAge($dryRun);
            $this->line("  By age:  {$byAge} rows");
        }

        if (! $onlyAge) {
            $bySize = $pruner->pruneBySize($dryRun);
            $this->line("  By size: {$bySize} rows");
        }

        $total = $byAge + $bySize;

        if (! $dryRun && $total > 0) {
            \Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers::clearStatsCache(
                (bool) config('activitylog-browse.retention.optimize_after', true)
            );
        }

        $this->info("Done. Total: {$total} rows" . ($dryRun ? ' (dry run)' : ''));

        return self::SUCCESS;
    }
}
