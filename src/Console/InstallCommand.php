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

        // Publish our config (force-overwrite if it already exists so new
        // sections like `retention` and `deletion_history` get added).
        $this->info('Publishing activitylog-browse config...');
        $configPath = config_path('activitylog-browse.php');
        $configExists = file_exists($configPath);
        $force = false;

        if ($configExists) {
            $this->warn('  Config file already exists at: ' . $configPath);
            $force = $this->confirm(
                '  Overwrite with the latest config (recommended to get new options like retention / deletion_history)?',
                true
            );
        }

        $this->call('vendor:publish', array_filter([
            '--tag' => 'activitylog-browse-config',
            '--force' => $force ? true : null,
        ]));

        if ($configExists && ! $force) {
            $this->warn('  Config not overwritten. New options will fall back to package defaults.');
            $this->line('  To merge manually, compare your config with: vendor/mahmoud-mhamed/spatie-activitylog-browse/config/activitylog-browse.php');
        }

        // Ensure storage directory + .gitignore for deletion history
        $this->ensureDeletionHistoryStorage();

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

    protected function ensureDeletionHistoryStorage(): void
    {
        $this->info('Setting up deletion history storage...');

        $path = (string) config(
            'activitylog-browse.deletion_history.path',
            storage_path('activitylog-browse/deletion-history.json')
        );
        $dir = dirname($path);

        if (! is_dir($dir)) {
            if (@mkdir($dir, 0775, true) || is_dir($dir)) {
                $this->info("  Created directory: {$dir}");
            } else {
                $this->warn("  Could not create directory: {$dir}");
                return;
            }
        } else {
            $this->line("  Directory already exists: {$dir}");
        }

        $gitignore = $dir . DIRECTORY_SEPARATOR . '.gitignore';
        if (! is_file($gitignore)) {
            if (@file_put_contents($gitignore, "*\n!.gitignore\n") !== false) {
                $this->info('  Added .gitignore to ignore the deletion-history.json file.');
            }
        } else {
            $this->line('  .gitignore already present.');
        }
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
