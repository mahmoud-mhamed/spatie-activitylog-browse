<?php

/*
 * Regression tests for bugs found while writing the suite (all fixed). Each
 * comment names where the bug was and why it mattered.
 */

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Mhamed\SpatieActivitylogBrowse\Support\ColumnMigrator;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\TestModel;
use Mhamed\SpatieActivitylogBrowse\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Exceptions::fake();
});

// src/Support/ColumnMigrator.php:42 — the non-MySQL branch of ensureRequestIdColumn()
// `return`s before clearRequestIdFailure(), so after a successful retry on SQLite/PostgreSQL
// lastRequestIdFailure() keeps the stale error forever.
it('ensure-columns clears the stored failure once the column is added (non-MySQL)', function () {
    $migration = require dirname(__DIR__, 2) . '/database/migrations/' . TestCase::REQUEST_ID_MIGRATION . '.php';
    $migration->down();
    DB::table('migrations')->where('migration', TestCase::REQUEST_ID_MIGRATION)->delete();
    TestCase::setStatic(ActivityLogHelpers::class, 'hasRequestIdColumn', null);

    config(['activitylog.database_connection' => 'missing_connection']);
    Artisan::call('migrate', ['--force' => true]);
    expect(ColumnMigrator::lastRequestIdFailure())->not->toBeNull();

    config(['activitylog.database_connection' => null]);
    expect(Artisan::call('activitylog-browse:ensure-columns'))->toBe(0)
        ->and(ColumnMigrator::lastRequestIdFailure())->toBeNull();
})->group('regressions');

// src/Http/Controllers/ActivityLogController.php sameStoredValue(): numeric-looking strings
// are compared as floats, so '0501234567' and '501234567' (phone numbers, codes, IBAN parts)
// count as "the same" and restore silently skips them.
it('restores a string column whose old value only differs by a leading zero', function () {
    config(['activitylog-browse.browse.restore.enabled' => true]);
    $model = TestModel::create(['name' => 'A', 'phone' => '0501234567']);
    $created = lastActivity();
    TestModel::find($model->id)->update(['phone' => '501234567']);

    $this->getJson("/activity-log/{$created->id}/restore-preview")
        ->assertOk()
        ->assertJsonPath('rows.0.key', 'phone');
})->group('regressions');

// ActivityLogController::findSubject() uses class_exists($type) and ValuePresenter::castsFor()
// uses is_subclass_of($modelClass): with Relation::morphMap()/enforceMorphMap() subject_type is an
// alias ('test-model'), so restore 404s and enum/boolean/decimal labels disappear.
// Fix: resolve Relation::getMorphedModel($type) ?? $type first.
it('restores subjects stored under a morph map alias', function () {
    Relation::morphMap(['test-model' => TestModel::class]);
    config(['activitylog-browse.browse.restore.enabled' => true]);
    $model = TestModel::create(['name' => 'A']);
    $created = lastActivity();
    TestModel::find($model->id)->update(['name' => 'B']);

    expect($created->subject_type)->toBe('test-model');
    $this->getJson("/activity-log/{$created->id}/restore-preview")
        ->assertOk()
        ->assertJsonPath('rows.0.key', 'name');
})->group('regressions');

it('shows enum labels for subjects stored under a morph map alias', function () {
    Relation::morphMap(['test-model' => TestModel::class]);
    $model = TestModel::create(['name' => 'A', 'status' => 'pending']);
    $model->update(['status' => 'paid']);

    $this->getJson('/activity-log/' . lastActivity()->id . '/changes')
        ->assertOk()
        ->assertJsonPath('rows.0.new_display', 'Fully paid');
})->group('regressions');

// src/Observers/ActivityEnrichmentObserver.php:52 claims the body in `creating`, before the
// insert. When that first insert does not happen (another `creating` listener cancels it, or the
// INSERT throws and the caller catches it), the claim is never released and no later activity
// of the request stores the body. Fix: only mark the body claimed in a `created` hook.
it('keeps the request body when the first activity of the request is not saved', function () {
    Activity::creating(fn (Activity $activity) => $activity->description === 'skip me' ? false : null);
    simulateHttpRequest(postRequest('/orders', ['name' => 'Widget']));

    activity()->log('skip me');
    activity()->log('kept');

    expect(activities()->pluck('description')->all())->toBe(['kept'])
        ->and(requestBody(lastActivity()))->toBe(['name' => 'Widget']);
})->group('regressions');

// ActivityEnrichmentObserver::creating() merges every section into one `properties` JSON:
// invalid UTF-8 in a header value (User-Agent, Referer) or the URL makes the JSON cast throw,
// which is caught — and ALL enrichment of the row (request data, body, device data) is lost.
// The body path already uses mb_scrub(); the header/URL values are not scrubbed.
it('keeps the enrichment when a header contains invalid UTF-8', function () {
    simulateHttpRequest(Request::create('/orders', 'POST', ['name' => 'x'], [], [], ['HTTP_USER_AGENT' => "Bot\xB1/1.0"]));

    activity()->log('bad header');

    expect(activities())->toHaveCount(1)
        ->and(lastActivity()->properties->toArray())->toHaveKeys(['request_data', 'device_data']);
})->group('regressions');

// src/Helpers/ExecutionContextCollector.php caches source() and jobName() per PROCESS
// (static $cachedSource / $jobNameDetected). A queue worker runs many jobs: every activity
// then carries the first job's class as job_name, and rows logged outside a job after it are
// still tagged source=queue. Fix: don't cache job_name/source; derive them per activity
// (e.g. from the JobProcessing/JobProcessed scope the provider already tracks).
it('records the job class of the job that is actually running', function () {
    dispatch(new Mhamed\SpatieActivitylogBrowse\Tests\Support\LogActivityJob('first job'));
    dispatch(new Mhamed\SpatieActivitylogBrowse\Tests\Support\OtherJob());

    expect(Activity::query()->where('description', 'other job')->first()->properties['execution_context']['job_name'])
        ->toBe(Mhamed\SpatieActivitylogBrowse\Tests\Support\OtherJob::class);
})->group('regressions');

// ExecutionContextCollector::source(): the console kernel always binds Schedule, so
// app()->bound(Schedule::class) is true in every artisan process and plain commands
// (tinker, custom commands, migrations) are labelled "schedule"; "console" never appears.
it('labels a plain artisan command as console, not schedule', function () {
    app(Illuminate\Contracts\Console\Kernel::class)->registerCommand(new class extends Illuminate\Console\Command {
        protected $signature = 'demo:log';

        public function handle(): void
        {
            activity()->log('from command');
        }
    });

    Artisan::call('demo:log');

    expect(lastActivity()->properties['execution_context']['source'])->toBe('console');
})->group('regressions');
