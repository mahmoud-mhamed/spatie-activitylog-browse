<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Mhamed\SpatieActivitylogBrowse\Support\ColumnMigrator;

/**
 * Groups every activity logged during one HTTP request, so the browse UI can
 * list "all changes made by this request" and store the request body once.
 *
 * Never throws: on a busy production table the ALTER can time out, and a failed
 * migration would abort `migrate` (and whatever the deploy runs after it). The
 * package works without the column; the failure is reported, shown as an alert
 * on the browse page, and retried nightly by `activitylog-browse:ensure-columns`.
 */
return new class extends Migration
{
    public function up(): void
    {
        ColumnMigrator::tryEnsureRequestIdColumn();
    }

    public function down(): void
    {
        $model = ActivityLogHelpers::activityModel();
        $table = $model->getTable();
        $schema = Schema::connection($model->getConnectionName());

        if (! $schema->hasTable($table) || ! $schema->hasColumn($table, 'request_id')) {
            return;
        }

        // up() may have added the column but not the index (e.g. it timed out in between).
        $hasIndex = ! method_exists($schema, 'getIndexes')
            || collect($schema->getIndexes($table))->contains('name', "{$table}_request_id_index");

        $schema->table($table, function (Blueprint $t) use ($hasIndex) {
            if ($hasIndex) {
                $t->dropIndex(['request_id']);
            }
            $t->dropColumn('request_id');
        });
    }
};
