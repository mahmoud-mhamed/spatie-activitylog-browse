<?php

use Illuminate\Support\Facades\DB;
use Mhamed\SpatieActivitylogBrowse\Support\RetentionPruner;

/** Raw row (observer bypassed) so `properties` is exactly what the test sets. */
function cleanupRow(array $properties, int $daysAgo = 0): int
{
    $at = now()->subDays($daysAgo)->toDateTimeString();

    return DB::table('activity_log')->insertGetId([
        'log_name' => 'default',
        'description' => 'row',
        'properties' => json_encode($properties),
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

function cleanupProps(int $id): array
{
    return json_decode(DB::table('activity_log')->where('id', $id)->value('properties'), true);
}

beforeEach(function () {
    $placeholder = ['url' => 'http://localhost', 'method' => 'GET'];
    $device = ['ip' => '127.0.0.1', 'user_agent' => 'Symfony'];
    $perf = ['request_duration' => 9043145, 'db_query_count' => 31320];

    $this->queueBefore = cleanupRow(['attributes' => ['a' => 1], 'request_data' => $placeholder, 'device_data' => $device, 'performance_data' => $perf, 'execution_context' => ['source' => 'queue']]);
    $this->queueAfter = cleanupRow(['attributes' => ['a' => 2], 'performance_data' => ['request_duration' => 12], 'execution_context' => ['source' => 'queue']]);
    $this->scheduleBefore = cleanupRow(['request_data' => $placeholder, 'device_data' => $device, 'performance_data' => ['request_duration' => 3070], 'execution_context' => ['source' => 'schedule']]);
    $this->web = cleanupRow(['request_data' => ['url' => 'https://app.test/x', 'method' => 'POST'], 'device_data' => ['ip' => '10.0.0.1'], 'execution_context' => ['source' => 'web']]);
});

it('strips placeholder request/device data from job and scheduled rows only', function () {
    $pruner = app(RetentionPruner::class);

    expect($pruner->stripPlaceholderData(PHP_INT_MAX, true))->toBe(2);

    $pruner->stripPlaceholderData(100);

    // Queue row from before the fix: placeholder request/device data and worker-wide performance gone.
    expect(cleanupProps($this->queueBefore))->toBe(['attributes' => ['a' => 1], 'execution_context' => ['source' => 'queue']])
        // Queue row from after the fix keeps its (correct, per-job) performance numbers.
        ->and(cleanupProps($this->queueAfter))->toHaveKey('performance_data')
        // Scheduled rows only lose request/device data.
        ->and(cleanupProps($this->scheduleBefore))->toHaveKeys(['performance_data', 'execution_context'])
        ->and(cleanupProps($this->scheduleBefore))->not->toHaveKeys(['request_data', 'device_data'])
        // Real web requests are never touched.
        ->and(cleanupProps($this->web))->toHaveKeys(['request_data', 'device_data'])
        ->and($pruner->stripPlaceholderData(PHP_INT_MAX, true))->toBe(0);
});

it('strips placeholder data in batches of at most the given limit', function () {
    $pruner = app(RetentionPruner::class);

    expect($pruner->stripPlaceholderData(1))->toBe(1)
        ->and($pruner->stripPlaceholderData(1))->toBe(1)
        ->and($pruner->stripPlaceholderData(1))->toBe(0);
});

it('serves the placeholder cleanup as preview + batched endpoint', function () {
    $this->getJson('/activity-log/cleanup/placeholder/preview')->assertOk()->assertJson(['count' => 2]);

    $this->postJson('/activity-log/cleanup/placeholder')->assertOk()->assertJson(['processed' => 2, 'done' => true]);

    $this->getJson('/activity-log/cleanup/placeholder/preview')->assertOk()->assertJson(['count' => 0]);
});

it('strips request bodies older than the given days through the cleanup endpoints', function () {
    $old = cleanupRow(['request_data' => ['url' => 'https://app.test/a', 'body' => ['name' => 'old']], 'execution_context' => ['source' => 'web']], 40);
    $recent = cleanupRow(['request_data' => ['url' => 'https://app.test/b', 'body' => ['name' => 'new']], 'execution_context' => ['source' => 'web']], 1);

    $preview = $this->getJson('/activity-log/cleanup/bodies/preview?days=30')->assertOk();
    expect($preview->json('count'))->toBe(1)
        ->and($preview->json('bytes'))->toBeGreaterThan(0);

    $this->postJson('/activity-log/cleanup/bodies', ['days' => 30])->assertOk()->assertJson(['processed' => 1, 'done' => true]);

    expect(cleanupProps($old)['request_data'])->toBe(['url' => 'https://app.test/a'])
        ->and(cleanupProps($recent)['request_data']['body'])->toBe(['name' => 'new']);
});

it('renders the cleanup page with the request_id status and the new sections', function () {
    $this->get('/activity-log/cleanup')
        ->assertOk()
        ->assertSee(__('activitylog-browse::messages.cleanup_bodies_title'))
        ->assertSee(__('activitylog-browse::messages.cleanup_placeholder_title'))
        ->assertSee(__('activitylog-browse::messages.request_id_card_title'));
});

it('marks a table rebuild as pending when a cleanup changed rows', function () {
    expect(Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers::optimizePendingSince())->toBeNull();

    $this->postJson('/activity-log/cleanup/placeholder')->assertOk();

    expect(Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers::optimizePendingSince())->not->toBeNull();
});

it('does not mark a rebuild when a cleanup changed nothing', function () {
    app(RetentionPruner::class)->stripPlaceholderData(100);
    Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers::clearOptimizePending();

    $this->postJson('/activity-log/cleanup/placeholder')->assertOk()->assertJson(['processed' => 0]);

    expect(Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers::optimizePendingSince())->toBeNull();
});

it('runs the pending rebuild from prune --optimize and clears it', function () {
    $helpers = Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers::class;

    $this->artisan('activitylog-browse:prune --optimize')->expectsOutputToContain('No cleanup is waiting')->assertSuccessful();

    $helpers::markOptimizePending();
    // SQLite has no OPTIMIZE TABLE: the run is skipped but the request is settled.
    $this->artisan('activitylog-browse:prune --optimize')->expectsOutputToContain('Skipped')->assertSuccessful();

    expect($helpers::optimizePendingSince())->toBeNull();
});

it('rebuilds once the reclaim response is sent and reports progress through table-size', function () {
    $helpers = Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers::class;
    $helpers::markOptimizePending();

    // The rebuild is a terminating callback: the test client terminates the app after each
    // request, just like php-fpm after sending the response, so it has run by the next call.
    $this->postJson('/activity-log/cleanup/reclaim-space')->assertOk()->assertJson(['started' => true]);

    $this->getJson('/activity-log/cleanup/table-size')->assertOk()->assertJson(['running' => false, 'error' => null, 'pending_since' => null]);
});

it('reports a running rebuild without recomputing the size', function () {
    Illuminate\Support\Facades\Cache::put(Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers::cachePrefix() . ':rebuild_running', true, 60);

    $this->getJson('/activity-log/cleanup/table-size')->assertOk()->assertJson(['running' => true, 'size' => null]);
});
