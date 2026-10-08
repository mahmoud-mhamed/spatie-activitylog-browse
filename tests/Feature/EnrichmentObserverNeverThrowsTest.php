<?php

use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Str;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\TestModel;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\ThrowingRequest;

beforeEach(function () {
    Exceptions::fake();
    ThrowingRequest::$throwOn = [];
});

afterEach(function () {
    ThrowingRequest::$throwOn = [];
});

it('still saves the model and inserts the activity when a collector throws', function (string $method, string $brokenSection, string $intactSection) {
    ThrowingRequest::$throwOn = [$method];
    simulateHttpRequest(ThrowingRequest::create('/orders', 'POST', ['name' => 'x']));

    $model = TestModel::create(['name' => 'Widget']);

    expect($model->exists)->toBeTrue()
        ->and(activities())->toHaveCount(1);

    $properties = lastActivity()->properties->toArray();
    expect($properties)->not->toHaveKey($brokenSection)
        ->and($properties)->toHaveKey($intactSection)
        ->and($properties['attributes']['name'])->toBe('Widget');

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === "{$method} exploded");
})->with([
    'request collector (fullUrl)' => ['fullUrl', 'request_data', 'device_data'],
    'device collector (ip)' => ['ip', 'device_data', 'request_data'],
]);

it('keeps the rest of request_data when only the body sanitizer throws', function () {
    ThrowingRequest::$throwOn = ['isJson'];
    simulateHttpRequest(ThrowingRequest::create('/orders', 'POST', ['name' => 'x']));

    activity()->log('with broken body');

    $requestData = lastActivity()->properties['request_data'];
    expect($requestData)->toHaveKey('url')
        ->and($requestData)->not->toHaveKey('body');
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'isJson exploded');
});

it('still inserts the activity when generating the request id throws', function () {
    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));
    Str::createUuidsUsing(fn () => throw new RuntimeException('uuid generator down'));

    activity()->log('no uuid');

    expect(activities())->toHaveCount(1)
        ->and(lastActivity()->request_id)->toBeNull()
        ->and(lastActivity()->properties['request_data']['url'])->toBe('http://localhost/orders');
    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'uuid generator down');
});

it('logs activities with malformed request_data config', function (string $key, mixed $value) {
    config(["activitylog-browse.{$key}" => $value]);
    simulateHttpRequest(postRequest('/orders', ['name' => 'x', 'password' => 'secret']));

    $model = TestModel::create(['name' => 'Widget']);
    $model->update(['name' => 'Gadget']);
    activity()->log('manual');

    expect(activities())->toHaveCount(3)
        ->and(TestModel::query()->value('name'))->toBe('Gadget');
})->with([
    'body options as a string' => ['request_data.body', 'yes please'],
    'body options as null' => ['request_data.body', null],
    'fields as a string' => ['request_data.fields', 'url,method'],
    'fields as an int' => ['request_data.fields', 1],
    'whole request_data as a string' => ['request_data', 'on'],
    'masked_keys as a string' => ['request_data.body.masked_keys', '*password*'],
    'masked_keys with a nested array' => ['request_data.body.masked_keys', [['*password*']]],
    'masked_keys with null entries' => ['request_data.body.masked_keys', [null, 123]],
    'max_value_length as an array' => ['request_data.body.max_value_length', ['x']],
    'max_length as a string' => ['request_data.body.max_length', 'big'],
    'retention as a string' => ['request_data.body.retention', 'daily'],
]);

it('falls back to the default body options when request_data.body is not an array', function () {
    config(['activitylog-browse.request_data.body' => 'yes please']);
    simulateHttpRequest(postRequest('/login', ['email' => 'a@b.c', 'password' => 'secret']));

    activity()->log('login');

    expect(requestBody(lastActivity()))->toBe(['email' => 'a@b.c', 'password' => RequestDataCollector::MASK]);
});

it('reports a malformed masked_keys entry instead of failing the insert', function () {
    config(['activitylog-browse.request_data.body.masked_keys' => [['*password*']]]);
    simulateHttpRequest(postRequest('/login', ['password' => 'secret']));

    activity()->log('login');

    expect(activities())->toHaveCount(1)
        ->and(requestBody(lastActivity()))->toBeNull();
    Exceptions::assertReported(TypeError::class);
});

it('does not log or throw anything when enrichment is switched off at runtime', function () {
    config([
        'activitylog-browse.request_data.enabled' => false,
        'activitylog-browse.device_data.enabled' => false,
        'activitylog-browse.performance_data.enabled' => false,
        'activitylog-browse.app_data.enabled' => false,
        'activitylog-browse.session_data.enabled' => false,
        'activitylog-browse.execution_context.enabled' => false,
    ]);
    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));

    activity()->withProperties(['own' => 1])->log('plain');

    expect(lastActivity()->properties->toArray())->toBe(['own' => 1])
        ->and(lastActivity()->request_id)->toBeNull();
    Exceptions::assertNothingReported();
});
