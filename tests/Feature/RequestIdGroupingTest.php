<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\TestModel;
use Mhamed\SpatieActivitylogBrowse\Tests\TestCase;

beforeEach(function () {
    Exceptions::fake();
});

function dropRequestIdColumn(): void
{
    Schema::table('activity_log', function (Blueprint $table) {
        $table->dropIndex(['request_id']);
        $table->dropColumn('request_id');
    });
    TestCase::setStatic(ActivityLogHelpers::class, 'hasRequestIdColumn', null);
}

it('gives every activity of one HTTP request the same request id', function () {
    simulateHttpRequest(postRequest('/orders', ['name' => 'Widget']));

    $model = TestModel::create(['name' => 'Widget']);
    $model->update(['name' => 'Gadget']);
    activity()->log('manual');

    $ids = activities()->pluck('request_id');
    expect($ids)->toHaveCount(3)
        ->and($ids->unique())->toHaveCount(1)
        ->and(Str::isUuid($ids->first()))->toBeTrue()
        ->and(RequestDataCollector::requestId())->toBe($ids->first());
});

it('gives different HTTP requests different request ids', function () {
    simulateHttpRequest(postRequest('/a', ['x' => 1]));
    activity()->log('first request');

    simulateHttpRequest(postRequest('/b', ['x' => 2]));
    activity()->log('second request');

    [$first, $second] = activities()->pluck('request_id')->all();
    expect($first)->not->toBeNull()
        ->and($second)->not->toBeNull()
        ->and($first)->not->toBe($second);
});

it('keeps an explicitly set request_id', function () {
    simulateHttpRequest(postRequest('/a', ['x' => 1]));

    activity()->tap(fn ($activity) => $activity->request_id = 'custom-group')->log('tagged');

    expect(lastActivity()->request_id)->toBe('custom-group');
});

it('stores the body only on the first activity of the request', function () {
    simulateHttpRequest(postRequest('/orders', ['name' => 'Widget', 'password' => 'secret']));

    $model = TestModel::create(['name' => 'Widget']);
    $model->update(['name' => 'Gadget']);
    activity()->log('manual');

    $rows = activities();
    expect(requestBody($rows[0]))->toBe(['name' => 'Widget', 'password' => RequestDataCollector::MASK])
        ->and(requestBody($rows[1]))->toBeNull()
        ->and(requestBody($rows[2]))->toBeNull()
        // The rest of request_data stays on every row.
        ->and($rows[2]->properties['request_data']['url'])->toBe('http://localhost/orders');
});

it('stores the body again after a transaction rollback removed the activity that carried it', function () {
    simulateHttpRequest(postRequest('/orders', ['name' => 'Widget']));

    DB::beginTransaction();
    TestModel::create(['name' => 'rolled back']);
    DB::rollBack();

    expect(activities())->toHaveCount(0);

    TestModel::create(['name' => 'kept']);
    activity()->log('after');

    $rows = activities();
    expect($rows)->toHaveCount(2)
        ->and(requestBody($rows[0]))->toBe(['name' => 'Widget'])
        ->and(requestBody($rows[1]))->toBeNull();
});

it('keeps the body when a nested transaction (savepoint) is rolled back', function () {
    simulateHttpRequest(postRequest('/orders', ['name' => 'Widget']));

    DB::transaction(function () {
        activity()->log('outer, kept');

        try {
            DB::transaction(function () {
                activity()->log('inner, rolled back');
                throw new RuntimeException('inner fails');
            });
        } catch (RuntimeException) {
        }

        activity()->log('outer, after savepoint rollback');
    });

    $rows = activities();
    expect($rows->pluck('description')->all())->toBe(['outer, kept', 'outer, after savepoint rollback'])
        ->and(requestBody($rows[0]))->toBe(['name' => 'Widget']);
});

it('writes no request_id and stores the body on every activity when the column is missing', function () {
    dropRequestIdColumn();
    expect(ActivityLogHelpers::hasRequestIdColumn())->toBeFalse();

    simulateHttpRequest(postRequest('/orders', ['name' => 'Widget']));

    $model = TestModel::create(['name' => 'Widget']);
    $model->update(['name' => 'Gadget']);
    activity()->log('manual');

    $rows = activities();
    expect($rows)->toHaveCount(3);
    foreach ($rows as $row) {
        expect($row->getAttributes())->not->toHaveKey('request_id')
            ->and(requestBody($row))->toBe(['name' => 'Widget']);
    }
    Exceptions::assertNothingReported();
});

it('does not group console activities (no job running)', function () {
    $model = TestModel::create(['name' => 'Widget']);
    $model->update(['name' => 'Gadget']);
    activity()->log('from artisan');

    expect(RequestDataCollector::requestId())->toBeNull()
        ->and(activities()->pluck('request_id')->filter())->toBeEmpty();
});

it('does not group when fields.request_id is disabled', function () {
    config(['activitylog-browse.request_data.fields.request_id' => false]);
    simulateHttpRequest(postRequest('/orders', ['name' => 'Widget']));

    activity()->log('one');
    activity()->log('two');

    $rows = activities();
    expect($rows->pluck('request_id')->filter())->toBeEmpty()
        // Without a group id the body is kept on every row.
        ->and(requestBody($rows[0]))->toBe(['name' => 'Widget'])
        ->and(requestBody($rows[1]))->toBe(['name' => 'Widget']);
});
