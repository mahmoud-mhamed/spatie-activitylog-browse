<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Mhamed\SpatieActivitylogBrowse\Support\RetentionPruner;

/** Insert raw rows (bypassing the observer) so properties are exactly what we set. */
function insertActivity(array $properties, int $daysAgo, string $description = 'row'): int
{
    $at = now()->subDays($daysAgo)->toDateTimeString();

    return DB::table('activity_log')->insertGetId([
        'log_name' => 'default',
        'description' => $description,
        'properties' => json_encode($properties),
        'created_at' => $at,
        'updated_at' => $at,
    ]);
}

function storedProperties(int $id): array
{
    return json_decode(DB::table('activity_log')->where('id', $id)->value('properties'), true);
}

function enableBodyRetention(int $days = 30): void
{
    config([
        'activitylog-browse.request_data.body.retention.enabled' => true,
        'activitylog-browse.request_data.body.retention.days' => $days,
    ]);
}

beforeEach(function () {
    $this->withBody = fn (string $tag) => [
        'attributes' => ['name' => $tag],
        'request_data' => ['url' => "http://localhost/{$tag}", 'method' => 'POST', 'body' => ['name' => $tag, 'items' => [1, 2]]],
        'device_data' => ['ip' => '10.0.0.1'],
    ];
});

it('strips request_data.body only from rows older than the retention days', function () {
    enableBodyRetention(30);
    $old = insertActivity(($this->withBody)('old'), 31);
    $recent = insertActivity(($this->withBody)('recent'), 5);
    $oldWithoutBody = insertActivity(['attributes' => ['name' => 'plain'], 'request_data' => ['url' => 'http://localhost/x']], 40);

    $exit = Artisan::call('activitylog-browse:prune', ['--bodies' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Request bodies stripped: 1 rows')
        ->and(DB::table('activity_log')->count())->toBe(3);

    expect(storedProperties($old))->toBe([
        'attributes' => ['name' => 'old'],
        'request_data' => ['url' => 'http://localhost/old', 'method' => 'POST'],
        'device_data' => ['ip' => '10.0.0.1'],
    ])
        ->and(storedProperties($recent))->toBe(($this->withBody)('recent'))
        ->and(storedProperties($oldWithoutBody))->toBe(['attributes' => ['name' => 'plain'], 'request_data' => ['url' => 'http://localhost/x']]);
});

it('strips clipped (string) bodies too', function () {
    enableBodyRetention(10);
    $id = insertActivity(['request_data' => ['url' => 'u', 'body' => '{"a":"clipped…']], 11);

    expect((new RetentionPruner)->pruneRequestBodies())->toBe(1)
        ->and(storedProperties($id))->toBe(['request_data' => ['url' => 'u']]);
});

it('processes more rows than one chunk', function () {
    enableBodyRetention(30);
    config(['activitylog-browse.retention.chunk_size' => 100]);
    for ($i = 0; $i < 250; $i++) {
        insertActivity(($this->withBody)("old{$i}"), 60);
    }
    insertActivity(($this->withBody)('recent'), 1);

    expect((new RetentionPruner)->pruneRequestBodies())->toBe(250)
        ->and(DB::table('activity_log')->where('properties', 'like', '%"body"%')->count())->toBe(1)
        ->and(DB::table('activity_log')->count())->toBe(251);
});

it('is idempotent', function () {
    enableBodyRetention(30);
    insertActivity(($this->withBody)('old'), 31);

    expect((new RetentionPruner)->pruneRequestBodies())->toBe(1)
        ->and((new RetentionPruner)->pruneRequestBodies())->toBe(0);
});

it('does nothing when body retention is disabled', function () {
    config(['activitylog-browse.request_data.body.retention.enabled' => false, 'activitylog-browse.request_data.body.retention.days' => 1]);
    $old = insertActivity(($this->withBody)('old'), 365);

    $exit = Artisan::call('activitylog-browse:prune', ['--bodies' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Request body retention is disabled')
        ->and((new RetentionPruner)->pruneRequestBodies())->toBe(0)
        ->and(storedProperties($old))->toBe(($this->withBody)('old'));
});

it('does nothing with malformed retention config', function (mixed $retention) {
    config(['activitylog-browse.request_data.body.retention' => $retention]);
    $old = insertActivity(($this->withBody)('old'), 365);

    expect((new RetentionPruner)->pruneRequestBodies())->toBe(0)
        ->and(storedProperties($old))->toBe(($this->withBody)('old'));
})->with([
    'days 0' => [['enabled' => true, 'days' => 0]],
    'negative days' => [['enabled' => true, 'days' => -5]],
    'missing days' => [['enabled' => true]],
    'a string' => ['daily'],
    'null' => [null],
]);

it('reports counts without changing anything on --dry-run', function () {
    enableBodyRetention(30);
    $old = insertActivity(($this->withBody)('old'), 31);
    insertActivity(($this->withBody)('older'), 90);
    insertActivity(($this->withBody)('recent'), 1);

    $exit = Artisan::call('activitylog-browse:prune', ['--bodies' => true, '--dry-run' => true]);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('Request bodies stripped: 2 rows (dry run)')
        ->and(storedProperties($old))->toBe(($this->withBody)('old'))
        ->and(DB::table('activity_log')->where('properties', 'like', '%"body"%')->count())->toBe(3);
});

it('does not run the age/size prune when --bodies is given', function () {
    enableBodyRetention(30);
    config(['activitylog-browse.retention.enabled' => true, 'activitylog-browse.retention.default_days' => 1]);
    insertActivity(($this->withBody)('old'), 31);

    Artisan::call('activitylog-browse:prune', ['--bodies' => true]);

    expect(DB::table('activity_log')->count())->toBe(1);
});

it('uses json_remove on SQLite (the stored JSON stays valid)', function () {
    expect(DB::connection()->getDriverName())->toBe('sqlite');
    enableBodyRetention(30);
    $id = insertActivity(['request_data' => ['body' => ['a' => 1]], 'old' => ['x' => 1]], 31);

    (new RetentionPruner)->pruneRequestBodies();

    $raw = DB::table('activity_log')->where('id', $id)->value('properties');
    expect(json_validate($raw))->toBeTrue()
        ->and(json_decode($raw, true))->toBe(['request_data' => [], 'old' => ['x' => 1]]);
});
