<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Mhamed\SpatieActivitylogBrowse\Support\ColumnMigrator;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\TestModel;
use Mhamed\SpatieActivitylogBrowse\Tests\TestCase;

function requestIdMigration(): object
{
    return require dirname(__DIR__, 2) . '/database/migrations/' . TestCase::REQUEST_ID_MIGRATION . '.php';
}

function requestIdIndexes(): array
{
    return collect(Schema::getIndexes('activity_log'))
        ->filter(fn ($index) => $index['columns'] === ['request_id'])
        ->pluck('name')
        ->all();
}

/** Undo what setUp's migrate did, so the next `migrate` runs the request_id migration again. */
function rollBackRequestIdMigration(): void
{
    requestIdMigration()->down();
    DB::table('migrations')->where('migration', TestCase::REQUEST_ID_MIGRATION)->delete();
    TestCase::setStatic(ActivityLogHelpers::class, 'hasRequestIdColumn', null);
}

beforeEach(function () {
    Exceptions::fake();
});

it('adds the column and its index on migrate', function () {
    expect(Schema::hasColumn('activity_log', 'request_id'))->toBeTrue()
        ->and(requestIdIndexes())->toBe(['activity_log_request_id_index'])
        ->and(DB::table('migrations')->where('migration', TestCase::REQUEST_ID_MIGRATION)->exists())->toBeTrue();
});

it('drops the column and index on down()', function () {
    requestIdMigration()->down();

    expect(Schema::hasColumn('activity_log', 'request_id'))->toBeFalse()
        ->and(requestIdIndexes())->toBe([]);

    // Idempotent: a second down() is a no-op.
    requestIdMigration()->down();
    expect(Schema::hasColumn('activity_log', 'request_id'))->toBeFalse();
});

it('down() drops the column when up() added it without the index', function () {
    requestIdMigration()->down();
    Schema::table('activity_log', fn ($table) => $table->string('request_id', 36)->nullable());

    requestIdMigration()->down();

    expect(Schema::hasColumn('activity_log', 'request_id'))->toBeFalse();
});

it('never fails migrate when adding the column fails, and remembers why', function () {
    rollBackRequestIdMigration();
    expect(ColumnMigrator::lastRequestIdFailure())->toBeNull();

    // The activity model now points at a connection that does not exist.
    config(['activitylog.database_connection' => 'missing_connection']);

    $exit = Artisan::call('migrate', ['--force' => true]);

    expect($exit)->toBe(0)
        // Recorded as run: the deploy goes on, the nightly retry adds the column later.
        ->and(DB::table('migrations')->where('migration', TestCase::REQUEST_ID_MIGRATION)->exists())->toBeTrue()
        ->and(Schema::hasColumn('activity_log', 'request_id'))->toBeFalse();

    $failure = ColumnMigrator::lastRequestIdFailure();
    expect($failure)->toBeArray()
        ->and($failure['message'])->toContain('missing_connection')
        ->and($failure['at'])->toBeString();
    Exceptions::assertReported(InvalidArgumentException::class);
});

it('keeps logging working while the column is missing after a failed migration', function () {
    rollBackRequestIdMigration();
    config(['activitylog.database_connection' => 'missing_connection']);
    Artisan::call('migrate', ['--force' => true]);
    config(['activitylog.database_connection' => null]);

    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));
    TestModel::create(['name' => 'still logged']);

    expect(activities())->toHaveCount(1)
        ->and(requestBody(lastActivity()))->toBe(['name' => 'x']);
});

it('ensure-columns reports the failure while the problem persists', function () {
    rollBackRequestIdMigration();
    config(['activitylog.database_connection' => 'missing_connection']);

    $exit = Artisan::call('activitylog-browse:ensure-columns');
    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('Could not add the request_id column')
        ->and($output)->toContain('missing_connection')
        ->and(ColumnMigrator::lastRequestIdFailure())->not->toBeNull();
});

it('ensure-columns adds the column + index later; re-running is a no-op', function () {
    rollBackRequestIdMigration();
    config(['activitylog.database_connection' => 'missing_connection']);
    Artisan::call('migrate', ['--force' => true]);
    expect(ColumnMigrator::lastRequestIdFailure())->not->toBeNull();

    config(['activitylog.database_connection' => null]);
    $exit = Artisan::call('activitylog-browse:ensure-columns');

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('request_id column and index are in place')
        ->and(Schema::hasColumn('activity_log', 'request_id'))->toBeTrue()
        ->and(requestIdIndexes())->toBe(['activity_log_request_id_index']);

    $columns = Schema::getColumnListing('activity_log');
    expect(Artisan::call('activitylog-browse:ensure-columns'))->toBe(0)
        ->and(Schema::getColumnListing('activity_log'))->toBe($columns)
        ->and(requestIdIndexes())->toBe(['activity_log_request_id_index']);

    // Grouping works again once the column is back.
    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));
    activity()->log('grouped again');
    expect(lastActivity()->request_id)->not->toBeNull();
});

it('skips quietly (no failure stored) when the activity table does not exist yet', function () {
    Schema::drop('activity_log');

    expect(ColumnMigrator::tryEnsureRequestIdColumn())->toBeTrue()
        ->and(ColumnMigrator::lastRequestIdFailure())->toBeNull()
        ->and(Schema::hasTable('activity_log'))->toBeFalse();

    requestIdMigration()->up();
    requestIdMigration()->down();
    Exceptions::assertNothingReported();
});

it('uses the table of a custom activity table name', function () {
    Schema::rename('activity_log', 'audit_trail');
    config(['activitylog.table_name' => 'audit_trail']);
    Schema::table('audit_trail', function ($table) {
        $table->dropIndex('activity_log_request_id_index');
        $table->dropColumn('request_id');
    });

    expect(ColumnMigrator::tryEnsureRequestIdColumn())->toBeTrue()
        ->and(Schema::hasColumn('audit_trail', 'request_id'))->toBeTrue()
        ->and(collect(Schema::getIndexes('audit_trail'))->pluck('name'))->toContain('audit_trail_request_id_index');
});
