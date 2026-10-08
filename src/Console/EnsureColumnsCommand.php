<?php

namespace Mhamed\SpatieActivitylogBrowse\Console;

use Illuminate\Console\Command;
use Mhamed\SpatieActivitylogBrowse\Support\ColumnMigrator;

class EnsureColumnsCommand extends Command
{
    protected $signature = 'activitylog-browse:ensure-columns';

    protected $description = 'Add the request_id column + index to the activity log table if missing (safe to re-run)';

    public function handle(): int
    {
        if (ColumnMigrator::tryEnsureRequestIdColumn()) {
            $this->info('request_id column and index are in place.');

            return self::SUCCESS;
        }

        $failure = ColumnMigrator::lastRequestIdFailure();
        $this->error('Could not add the request_id column: ' . ($failure['message'] ?? 'see the application log'));

        return self::FAILURE;
    }
}
