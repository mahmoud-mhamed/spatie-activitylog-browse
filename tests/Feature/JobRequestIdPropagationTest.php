<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mhamed\SpatieActivitylogBrowse\Helpers\PerformanceDataCollector;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\LogActivityJob;
use Mhamed\SpatieActivitylogBrowse\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

function jobStack(): array
{
    return TestCase::getStatic(RequestDataCollector::class, 'jobRequestIds');
}

function activityByDescription(string $description): Activity
{
    return Activity::query()->where('description', $description)->firstOrFail();
}

function createJobsTable(): void
{
    Schema::create('jobs', function (Blueprint $table) {
        $table->id();
        $table->string('queue')->index();
        $table->longText('payload');
        $table->unsignedTinyInteger('attempts');
        $table->unsignedInteger('reserved_at')->nullable();
        $table->unsignedInteger('available_at');
        $table->unsignedInteger('created_at');
    });
}

beforeEach(function () {
    Exceptions::fake();
});

it('puts the request id in the payload of a job dispatched during a request (sync driver)', function () {
    $payloads = [];
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$payloads) {
        $payloads[] = $event->job->payload();
    });

    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));
    activity()->log('in request');
    dispatch(new LogActivityJob('in job'));

    $requestId = activityByDescription('in request')->request_id;
    expect($requestId)->not->toBeNull()
        ->and($payloads)->toHaveCount(1)
        ->and($payloads[0][RequestDataCollector::JOB_PAYLOAD_KEY])->toBe($requestId)
        ->and(activityByDescription('in job')->request_id)->toBe($requestId)
        ->and(jobStack())->toBe([]);
});

it('carries the request id through the database queue to a worker', function () {
    createJobsTable();

    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));
    activity()->log('in request');
    dispatch(new LogActivityJob('queued work'))->onConnection('database');
    $requestId = activityByDescription('in request')->request_id;

    $payload = json_decode(DB::table('jobs')->value('payload'), true);
    expect($payload[RequestDataCollector::JOB_PAYLOAD_KEY])->toBe($requestId);

    // Later, in a worker process (console, placeholder request).
    simulateConsole();
    $exit = Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);

    expect($exit)->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(activityByDescription('queued work')->request_id)->toBe($requestId)
        ->and(jobStack())->toBe([]);
});

it('gives a job dispatched from the console its own fresh id', function () {
    $payloads = [];
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$payloads) {
        $payloads[] = $event->job->payload();
    });

    dispatch(new LogActivityJob('first job', dispatchChild: false));
    dispatch(new LogActivityJob('second job'));
    activity()->log('console, outside jobs');

    $first = activityByDescription('first job')->request_id;
    $second = activityByDescription('second job')->request_id;

    expect($payloads[0])->not->toHaveKey(RequestDataCollector::JOB_PAYLOAD_KEY)
        ->and(Str::isUuid($first))->toBeTrue()
        ->and(Str::isUuid($second))->toBeTrue()
        ->and($first)->not->toBe($second)
        ->and(activityByDescription('console, outside jobs')->request_id)->toBeNull();
});

it('lets a job dispatched inside a job inherit the parent id', function () {
    dispatch(new LogActivityJob('parent', dispatchChild: true));

    $parent = activityByDescription('parent')->request_id;
    expect($parent)->not->toBeNull()
        ->and(activityByDescription('parent:child')->request_id)->toBe($parent)
        // The parent keeps its id after the child finished.
        ->and(activityByDescription('parent:after-child')->request_id)->toBe($parent)
        ->and(jobStack())->toBe([]);
});

it('lets a job dispatched inside a job of a request inherit the request id', function () {
    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));
    activity()->log('in request');
    dispatch(new LogActivityJob('parent', dispatchChild: true));

    $requestId = activityByDescription('in request')->request_id;
    expect(activityByDescription('parent')->request_id)->toBe($requestId)
        ->and(activityByDescription('parent:child')->request_id)->toBe($requestId);
});

it('cleans the job stack after a job fails, so the id does not leak', function () {
    try {
        dispatch(new LogActivityJob('failing job', fail: true));
    } catch (RuntimeException $e) {
        expect($e->getMessage())->toBe('job failing job failed');
    }

    expect(jobStack())->toBe([]);

    activity()->log('console, after failure');
    dispatch(new LogActivityJob('next job'));

    $failed = activityByDescription('failing job')->request_id;
    expect($failed)->not->toBeNull()
        ->and(activityByDescription('console, after failure')->request_id)->toBeNull()
        ->and(activityByDescription('next job')->request_id)->not->toBeNull()
        ->and(activityByDescription('next job')->request_id)->not->toBe($failed);
});

it('cleans the job stack after a failing job in a worker', function () {
    createJobsTable();
    dispatch(new LogActivityJob('worker failure', fail: true))->onConnection('database');
    dispatch(new LogActivityJob('worker next'))->onConnection('database');

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);
    expect(jobStack())->toBe([]);

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true]);
    expect(jobStack())->toBe([]);

    $failed = activityByDescription('worker failure')->request_id;
    $next = activityByDescription('worker next')->request_id;
    expect($failed)->not->toBeNull()
        ->and($next)->not->toBeNull()
        ->and($next)->not->toBe($failed);
});

it('adds no group id to job payloads when request grouping is switched off', function () {
    // Switched off at runtime: the payload hook stays registered but must add nothing.
    config(['activitylog-browse.request_data.fields.request_id' => false]);
    $payloads = [];
    Event::listen(JobProcessing::class, function (JobProcessing $event) use (&$payloads) {
        $payloads[] = $event->job->payload();
    });

    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));
    dispatch(new LogActivityJob('ungrouped job'));

    expect($payloads[0])->not->toHaveKey(RequestDataCollector::JOB_PAYLOAD_KEY);
});

it('measures performance inside a job from the job start, with its own query baseline', function () {
    ActivityLogHelpers::hasRequestIdColumn(); // prime the column cache: its schema query would skew the counts

    for ($i = 0; $i < 20; $i++) {
        DB::select('select 1');
    }
    activity()->log('console, before jobs');

    dispatch(new LogActivityJob('parent', dispatchChild: true, queries: 3, childQueries: 1));

    $outside = activityByDescription('console, before jobs')->properties['performance_data'];
    $parent = activityByDescription('parent')->properties['performance_data'];
    $child = activityByDescription('parent:child')->properties['performance_data'];
    $afterChild = activityByDescription('parent:after-child')->properties['performance_data'];

    // No LARAVEL_START under the test runner: only job activities get a duration.
    expect($outside)->not->toHaveKey('request_duration')
        ->and($outside['db_query_count'])->toBeGreaterThanOrEqual(20)
        ->and($parent['request_duration'])->toBeFloat()->toBeLessThan(5000)
        ->and($parent['db_query_count'])->toBe(3)
        ->and($child['db_query_count'])->toBe(1)
        // parent's 3 selects + its activity insert + child's select + child's activity insert
        ->and($afterChild['db_query_count'])->toBe(6)
        ->and($parent['memory_peak'])->toBeInt()->toBeGreaterThan(0)
        ->and(TestCase::getStatic(PerformanceDataCollector::class, 'jobScopes'))->toBe([]);
});

it('drops the job performance scope after a failed job', function () {
    try {
        dispatch(new LogActivityJob('failing job', fail: true, queries: 2));
    } catch (RuntimeException) {
    }

    expect(TestCase::getStatic(PerformanceDataCollector::class, 'jobScopes'))->toBe([]);

    activity()->log('console, after failure');
    expect(activityByDescription('console, after failure')->properties['performance_data'])->not->toHaveKey('request_duration');
});
