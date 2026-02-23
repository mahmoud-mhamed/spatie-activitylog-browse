<?php

namespace Mhamed\SpatieActivitylogBrowse\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mhamed\SpatieActivitylogBrowse\Support\ColumnMigrator;

class InstallCommand extends Command
{
    protected $signature = 'activitylog-browse:install';

    protected $description = 'Install the ActivityLog Browse package (publishes spatie migration + config)';

    public function handle(): int
    {
        $this->info('Installing ActivityLog Browse...');

        $connection = config('activitylog.database_connection');
        $tableName = config('activitylog.table_name', 'activity_log');
        $tableExists = Schema::connection($connection)->hasTable($tableName);

        if ($tableExists) {
            $this->info("Table '{$tableName}' already exists. Skipping spatie migrations.");
            $this->ensureMissingColumns($connection, $tableName);
        } else {
            // Publish spatie/laravel-activitylog migration only if table doesn't exist
            $this->info('Publishing spatie/laravel-activitylog migration...');
            $this->call('vendor:publish', [
                '--provider' => 'Spatie\Activitylog\ActivitylogServiceProvider',
                '--tag' => 'activitylog-migrations',
            ]);

            if ($this->confirm('Run migrations now?', true)) {
                $this->call('migrate');
            }
        }

        // Publish our config
        $this->info('Publishing activitylog-browse config...');
        $this->call('vendor:publish', [
            '--tag' => 'activitylog-browse-config',
        ]);

        // Fix morph ID columns to support UUIDs
        $this->info('Ensuring morph ID columns support UUID format...');
        if (ColumnMigrator::fixMorphIdColumns()) {
            $this->info('  Fixed subject_id and/or causer_id columns to support UUIDs.');
        } else {
            $this->line('  Morph ID columns already support UUIDs or table not found.');
        }

        // Add performance indexes
        if ($this->confirm('Add performance indexes to activity_log table?', true)) {
            $this->addIndexes();
        }

        $this->info('ActivityLog Browse installed successfully.');
        $this->info('Visit /' . config('activitylog-browse.browse.prefix', 'activity-log') . ' to browse your logs.');

        return self::SUCCESS;
    }

    protected function ensureMissingColumns(?string $connection, string $tableName): void
    {
        $requiredColumns = [
            'event' => function ($table) {
                $table->string('event')->nullable()->after('subject_type');
            },
            'batch_uuid' => function ($table) {
                $table->uuid('batch_uuid')->nullable()->after('properties');
            },
        ];

        foreach ($requiredColumns as $column => $definition) {
            if (Schema::connection($connection)->hasColumn($tableName, $column)) {
                $this->line("  Column '{$column}' already exists. Skipping.");
            } else {
                Schema::connection($connection)->table($tableName, $definition);
                $this->info("  Added column '{$column}'.");
            }
        }
    }

    protected function addIndexes(): void
    {
        $table = config('activitylog.table_name', 'activity_log');

        if (! Schema::hasTable($table)) {
            $this->warn("Table '{$table}' not found. Skipping indexes.");
            return;
        }

        $indexes = [
            'activity_log_subject_type_subject_id_index' => ['subject_type', 'subject_id'],
            'activity_log_causer_type_causer_id_index' => ['causer_type', 'causer_id'],
            'activity_log_log_name_index' => ['log_name'],
            'activity_log_event_index' => ['event'],
        ];

        $existing = collect(Schema::getIndexes($table))->pluck('name')->map(fn ($k) => strtolower($k));

        foreach ($indexes as $name => $columns) {
            if ($existing->contains(strtolower($name))) {
                $this->line("  Index '{$name}' already exists. Skipping.");
                continue;
            }

            Schema::table($table, function ($t) use ($columns, $name) {
                $t->index($columns, $name);
            });
            $this->info("  Added index '{$name}'.");
        }
    }
}
