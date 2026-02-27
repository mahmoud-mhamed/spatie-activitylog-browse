<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable($table)) {
            return;
        }

        foreach (['subject_id', 'causer_id'] as $column) {
            if (! $schema->hasColumn($table, $column)) {
                continue;
            }

            $type = $schema->getColumnType($table, $column);

            if (in_array($type, ['string', 'text', 'guid'])) {
                continue;
            }

            $schema->table($table, function (Blueprint $t) use ($column) {
                $t->string($column, 36)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        $table = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection');
        $schema = Schema::connection($connection);

        if (! $schema->hasTable($table)) {
            return;
        }

        foreach (['subject_id', 'causer_id'] as $column) {
            if ($schema->hasColumn($table, $column)) {
                $schema->table($table, function (Blueprint $t) use ($column) {
                    $t->bigInteger($column)->unsigned()->nullable()->change();
                });
            }
        }
    }
};
