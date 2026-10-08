<?php

namespace Mhamed\SpatieActivitylogBrowse\Tests;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Queue;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mhamed\SpatieActivitylogBrowse\ActivitylogBrowseServiceProvider;
use Mhamed\SpatieActivitylogBrowse\Helpers\AppDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\ExecutionContextCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\PerformanceDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\QueryCounter;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\RuntimeContext;
use Mhamed\SpatieActivitylogBrowse\Helpers\SessionDataCollector;
use Mhamed\SpatieActivitylogBrowse\Observers\ActivityEnrichmentObserver;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\Activitylog\ActivitylogServiceProvider;

abstract class TestCase extends Orchestra
{
    public const REQUEST_ID_MIGRATION = '2026_10_08_000001_add_request_id_to_activity_log_table';

    protected function setUp(): void
    {
        // Statics survive between tests (one PHP process); start every test clean.
        self::resetPackageStatics();

        parent::setUp();

        $this->createSchema();

        // The package's own migrations (morph-id fix + request_id), like `php artisan migrate` in an app.
        Artisan::call('migrate', ['--force' => true]);

        self::resetPackageStatics();
    }

    protected function tearDown(): void
    {
        self::resetPackageStatics();

        // Registered statically on every provider boot; flush so callbacks don't pile up across tests.
        Queue::createPayloadUsing(null);
        Str::createUuidsNormally();
        Relation::morphMap([], false);
        putenv('LARAVEL_OCTANE');
        unset($_SERVER['LARAVEL_OCTANE']);

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            ActivitylogServiceProvider::class,
            ActivitylogBrowseServiceProvider::class,
        ];
    }

    /**
     * Runs after the providers registered (configs merged) and before they boot,
     * so everything the service provider wires at boot sees these values.
     */
    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        if (env('DB_CONNECTION') === 'mysql') {
            // setUp() drops and recreates tables: never point this at a real database.
            $database = (string) $config->get('database.connections.mysql.database');
            if (! str_contains(strtolower($database), 'test')) {
                throw new \RuntimeException("Refusing to run the suite against MySQL database [{$database}]: its name must contain 'test'.");
            }
            $config->set('database.default', 'mysql');
        } else {
            $config->set('database.default', 'testing');
            $config->set('database.connections.testing', [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
        }

        $config->set('cache.default', 'array');
        $config->set('session.driver', 'array');
        $config->set('queue.default', 'sync');
        $config->set('queue.failed.driver', 'null');
        $config->set('logging.default', 'null');

        // Body capture on at boot, so the TransactionRolledBack listener is registered.
        $config->set('activitylog-browse.request_data.fields.body', true);
        $config->set('activitylog-browse.browse.middleware', ['web']);
        $config->set('activitylog-browse.browse.password', null);
        $config->set('activitylog-browse.deletion_history.enabled', false);
        $config->set('activitylog-browse.retention.enabled', false);
    }

    protected function createSchema(): void
    {
        $schema = Schema::connection(null);

        foreach (['activity_log', 'test_models', 'jobs', 'migrations'] as $table) {
            $schema->dropIfExists($table);
        }

        // Spatie ships its table as publishable stubs: run them as the app would.
        $stubs = dirname((new \ReflectionClass(ActivitylogServiceProvider::class))->getFileName(), 2) . '/database/migrations';
        require_once $stubs . '/create_activity_log_table.php.stub';
        require_once $stubs . '/add_event_column_to_activity_log_table.php.stub';
        require_once $stubs . '/add_batch_uuid_column_to_activity_log_table.php.stub';

        (new \CreateActivityLogTable)->up();
        (new \AddEventColumnToActivityLogTable)->up();
        (new \AddBatchUuidColumnToActivityLogTable)->up();

        $schema->create('test_models', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->nullable();
            $table->unsignedTinyInteger('priority')->nullable();
            $table->boolean('is_active')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->json('meta')->nullable();
            $table->text('notes')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });
    }

    public static function resetPackageStatics(): void
    {
        RuntimeContext::resetCache();
        ActivityEnrichmentObserver::resetCache();
        AppDataCollector::resetCache();
        SessionDataCollector::resetCache();
        ExecutionContextCollector::resetCache();
        QueryCounter::reset();

        // Registers its DB listener once per process: let every test app get its own.
        self::setStatic(QueryCounter::class, 'registered', false);
        self::setStatic(PerformanceDataCollector::class, 'jobScopes', []);
        self::setStatic(ActivityLogHelpers::class, 'hasRequestIdColumn', null);
        self::setStatic(RequestDataCollector::class, 'jobRequestIds', []);
        self::setStatic(RequestDataCollector::class, 'bodyCache', null);
        self::setStatic(RequestDataCollector::class, 'requestIds', null);
        self::setStatic(RequestDataCollector::class, 'bodyClaimed', null);
    }

    public static function getStatic(string $class, string $property): mixed
    {
        $reflection = new \ReflectionProperty($class, $property);

        return $reflection->getValue();
    }

    /** Private statics are reset by reflection; skipped if src renamed/removed them. */
    public static function setStatic(string $class, string $property, mixed $value): void
    {
        if (! property_exists($class, $property)) {
            return;
        }

        (new \ReflectionProperty($class, $property))->setValue(null, $value);
    }
}
