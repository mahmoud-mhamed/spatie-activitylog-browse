<?php

namespace Mhamed\SpatieActivitylogBrowse\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ColumnMigrator
{
    /**
     * Fix subject_id and causer_id columns to support UUIDs (VARCHAR instead of BIGINT).
     * Safe to run multiple times — only modifies columns that need changing.
     */
    public static function fixMorphIdColumns(): bool
    {
        $table = config('activitylog.table_name', 'activity_log');

        if (! Schema::hasTable($table)) {
            return false;
        }

        $changed = false;

        foreach (['subject_id', 'causer_id'] as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $type = Schema::getColumnType($table, $column);

            if (in_array($type, ['string', 'text', 'guid'])) {
                continue;
            }

            $driver = Schema::getConnection()->getDriverName();

            if (in_array($driver, ['mysql', 'mariadb'])) {
                DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(36) NULL");
            } else {
                Schema::table($table, function ($t) use ($column) {
                    $t->string($column, 36)->nullable()->change();
                });
            }

            $changed = true;
        }

        return $changed;
    }
}
