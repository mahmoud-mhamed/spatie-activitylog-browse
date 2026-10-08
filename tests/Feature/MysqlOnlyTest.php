<?php

/*
 * Index filters, the request-details body sibling lookup and the MySQL ALTER path use
 * MySQL-only SQL (JSON_UNQUOTE, JSON_CONTAINS_PATH, information_schema, lock_wait_timeout).
 * Run against a throwaway database whose name contains "test":
 *
 *   DB_CONNECTION=mysql DB_DATABASE=activitylog_browse_test DB_USERNAME=root vendor/bin/pest --group=mysql
 */

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mhamed\SpatieActivitylogBrowse\Helpers\ExecutionContextCollector;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Mhamed\SpatieActivitylogBrowse\Support\ColumnMigrator;
use Mhamed\SpatieActivitylogBrowse\Support\RetentionPruner;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\TestModel;
use Mhamed\SpatieActivitylogBrowse\Tests\TestCase;

beforeEach(function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL only: run with DB_CONNECTION=mysql (see the file header).');
    }
});

/** Log one activity as if made by an HTTP request with the given method/uri/ip and source=web. */
function logWebActivity(string $description, string $method, string $uri, string $ip, array $input = []): void
{
    simulateHttpRequest(Request::create($uri, $method, $input, [], [], ['REMOTE_ADDR' => $ip]));
    // The runner is a console process; tag the row the way a real web request would be.
    TestCase::setStatic(ExecutionContextCollector::class, 'cachedSource', 'web');
    activity()->log($description);
    simulateConsole();
    TestCase::setStatic(ExecutionContextCollector::class, 'cachedSource', null);
}

it('filters the index by method, url, ip, source and body', function () {
    logWebActivity('web post orders', 'POST', '/Orders/7', '10.1.1.1', ['note' => 'Urgent Delivery']);
    logWebActivity('web get reports', 'GET', '/reports', '10.2.2.2');
    activity()->log('console row');

    $this->get('/activity-log?method=post')->assertOk()->assertSee('web post orders')->assertDontSee('web get reports')->assertDontSee('console row');
    $this->get('/activity-log?url=orders')->assertOk()->assertSee('web post orders')->assertDontSee('web get reports');
    $this->get('/activity-log?ip=10.2.')->assertOk()->assertSee('web get reports')->assertDontSee('web post orders');
    $this->get('/activity-log?source=web')->assertOk()->assertSee('web get reports')->assertDontSee('console row');
    $this->get('/activity-log?body_search=urgent')->assertOk()->assertSee('web post orders')->assertDontSee('web get reports');
})->group('mysql');

it('finds every activity of a request by its body, even rows that do not carry it', function () {
    simulateHttpRequest(Request::create('/orders', 'POST', ['note' => 'needle-123']));
    activity()->log('first of request');
    activity()->log('second of request');
    simulateConsole();
    activity()->log('unrelated');

    $this->get('/activity-log?body_search=NEEDLE-123')
        ->assertOk()
        ->assertSee('first of request')
        ->assertSee('second of request')
        ->assertDontSee('unrelated');
})->group('mysql');

it('filters by changed attribute', function () {
    $model = TestModel::create(['name' => 'A']);
    $model->update(['notes' => 'n']);

    $this->get('/activity-log?changed_attribute=notes')->assertOk()->assertSee('updated TestModel')->assertDontSee('created TestModel');
})->group('mysql');

it('reads the body from the first activity of the request in request-details', function () {
    simulateHttpRequest(Request::create('/orders', 'POST', ['name' => 'Widget']));
    activity()->log('first');
    activity()->log('second');
    simulateConsole();
    [$first, $second] = activities()->all();

    $body = collect($this->getJson("/activity-log/{$second->id}/request-details")->assertOk()->json('sections'))->firstWhere('title', 'Body');

    expect($body)->not->toBeNull()
        ->and($body['note'])->toContain("#{$first->id}")
        ->and(collect($body['rows'])->pluck('value', 'key')->all())->toBe(['name' => 'Widget']);
})->group('mysql');

it('adds the column + index with the online ALTER and clears the stored failure', function () {
    requestIdMigrationFile()->down();
    DB::table('migrations')->where('migration', TestCase::REQUEST_ID_MIGRATION)->delete();
    TestCase::setStatic(ActivityLogHelpers::class, 'hasRequestIdColumn', null);

    config(['activitylog.database_connection' => 'missing_connection']);
    expect(Artisan::call('migrate', ['--force' => true]))->toBe(0)
        ->and(ColumnMigrator::lastRequestIdFailure())->not->toBeNull();

    config(['activitylog.database_connection' => null]);
    $before = DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS value')->value;

    expect(Artisan::call('activitylog-browse:ensure-columns'))->toBe(0)
        ->and(Schema::hasColumn('activity_log', 'request_id'))->toBeTrue()
        ->and(collect(Schema::getIndexes('activity_log'))->pluck('name'))->toContain('activity_log_request_id_index')
        ->and(ColumnMigrator::lastRequestIdFailure())->toBeNull()
        // The short lock wait is restored afterwards.
        ->and(DB::selectOne('SELECT @@SESSION.lock_wait_timeout AS value')->value)->toBe($before)
        ->and(Artisan::call('activitylog-browse:ensure-columns'))->toBe(0);
})->group('mysql');

it('strips old bodies with JSON_REMOVE', function () {
    config([
        'activitylog-browse.request_data.body.retention.enabled' => true,
        'activitylog-browse.request_data.body.retention.days' => 30,
    ]);
    $old = DB::table('activity_log')->insertGetId([
        'description' => 'old', 'properties' => json_encode(['request_data' => ['url' => 'u', 'body' => ['a' => 1]]]),
        'created_at' => now()->subDays(40), 'updated_at' => now()->subDays(40),
    ]);

    expect((new RetentionPruner)->pruneRequestBodies(dryRun: true))->toBe(1)
        ->and((new RetentionPruner)->pruneRequestBodies())->toBe(1)
        ->and(json_decode(DB::table('activity_log')->where('id', $old)->value('properties'), true))->toBe(['request_data' => ['url' => 'u']]);
})->group('mysql');

function requestIdMigrationFile(): object
{
    return require dirname(__DIR__, 2) . '/database/migrations/' . TestCase::REQUEST_ID_MIGRATION . '.php';
}

it('rebuilds the table with OPTIMIZE TABLE from prune --optimize', function () {
    ActivityLogHelpers::markOptimizePending();

    $this->artisan('activitylog-browse:prune --optimize')->expectsOutputToContain('Done')->assertSuccessful();

    expect(ActivityLogHelpers::optimizePendingSince())->toBeNull();
})->group('mysql');

it('strips placeholder data into compact documents (no space trapped by partial updates)', function () {
    $id = DB::table('activity_log')->insertGetId([
        'log_name' => 'default', 'description' => 'job row', 'created_at' => now(), 'updated_at' => now(),
        'properties' => json_encode(['attributes' => ['a' => 1], 'request_data' => ['url' => str_repeat('x', 200)], 'device_data' => ['ip' => '127.0.0.1'], 'execution_context' => ['source' => 'queue']]),
    ]);

    app(RetentionPruner::class)->stripPlaceholderData(100);

    $row = DB::selectOne('select properties p, JSON_STORAGE_FREE(properties) free from activity_log where id = ?', [$id]);
    expect((int) $row->free)->toBe(0)
        ->and(json_decode($row->p, true))->toBe(['attributes' => ['a' => 1], 'execution_context' => ['source' => 'queue']]);
})->group('mysql');

it('compacts documents with trapped free space before OPTIMIZE TABLE, keeping their content', function () {
    $doc = ['attributes' => ['a' => 1], 'request_data' => ['url' => str_repeat('x', 200)], 'execution_context' => ['source' => 'queue']];
    $id = DB::table('activity_log')->insertGetId(['log_name' => 'default', 'description' => 'row', 'created_at' => now(), 'updated_at' => now(), 'properties' => json_encode($doc)]);
    // A plain JSON_REMOVE is a partial in-place update: the freed bytes stay inside the document.
    DB::update("update activity_log set properties = JSON_REMOVE(properties, '$.request_data') where id = ?", [$id]);
    expect((int) DB::selectOne('select JSON_STORAGE_FREE(properties) f from activity_log where id = ?', [$id])->f)->toBeGreaterThan(0);

    ActivityLogHelpers::markOptimizePending();
    $this->artisan('activitylog-browse:prune --optimize')->assertSuccessful();

    $row = DB::selectOne('select properties p, JSON_STORAGE_FREE(properties) free from activity_log where id = ?', [$id]);
    expect((int) $row->free)->toBe(0)
        ->and(json_decode($row->p, true))->toBe(['attributes' => ['a' => 1], 'execution_context' => ['source' => 'queue']]);
})->group('mysql');
