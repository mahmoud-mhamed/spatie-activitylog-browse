<?php

use Illuminate\Http\Request;
use Mhamed\SpatieActivitylogBrowse\Helpers\RuntimeContext;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\LogActivityJob;

it('stores no request or device data for console activities (placeholder request)', function () {
    activity()->log('from artisan');

    $properties = lastActivity()->properties->toArray();
    expect($properties)->not->toHaveKey('request_data')
        ->and($properties)->not->toHaveKey('device_data')
        ->and($properties)->toHaveKeys(['performance_data', 'app_data', 'execution_context'])
        ->and($properties['execution_context']['source'])->not->toBe('web');
});

it('stores no request or device data for queued jobs', function () {
    dispatch(new LogActivityJob('in job'));

    $properties = lastActivity()->properties->toArray();
    expect($properties)->not->toHaveKey('request_data')
        ->and($properties)->not->toHaveKey('device_data')
        ->and($properties['execution_context']['source'])->toBe('queue')
        ->and($properties['execution_context']['job_name'])->not->toBeEmpty();
});

it('stores request and device data for an HTTP request', function () {
    simulateHttpRequest(Request::create('/orders?page=2', 'POST', ['name' => 'x'], [], [], [
        'REMOTE_ADDR' => '10.1.2.3',
        'HTTP_USER_AGENT' => 'Browser/1.0',
        'HTTP_REFERER' => 'http://localhost/orders',
    ]));

    activity()->log('from web');

    $properties = lastActivity()->properties->toArray();
    expect($properties['request_data'])->toMatchArray([
        'url' => 'http://localhost/orders?page=2',
        'method' => 'POST',
        'body' => ['name' => 'x'],
    ])->and($properties['device_data'])->toBe([
        'ip' => '10.1.2.3',
        'user_agent' => 'Browser/1.0',
        'referrer' => 'http://localhost/orders',
    ]);
});

it('treats an Octane worker (CLI process) as an HTTP request', function () {
    $_SERVER['LARAVEL_OCTANE'] = '1';
    RuntimeContext::resetCache();
    app()->instance('request', Request::create('/octane', 'POST', ['name' => 'x']));
    Illuminate\Support\Facades\Facade::clearResolvedInstance('request');

    expect(RuntimeContext::isConsole())->toBeTrue()
        ->and(RuntimeContext::isHttpRequest())->toBeTrue();

    activity()->log('octane');

    expect(lastActivity()->properties['request_data']['url'])->toBe('http://localhost/octane')
        ->and(lastActivity()->properties)->toHaveKey('device_data')
        ->and(lastActivity()->request_id)->not->toBeNull();
});
