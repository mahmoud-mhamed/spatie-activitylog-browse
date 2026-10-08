<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;
use Mhamed\SpatieActivitylogBrowse\Helpers\RuntimeContext;
use Mhamed\SpatieActivitylogBrowse\Tests\TestCase;
use Spatie\Activitylog\Models\Activity;

uses(TestCase::class)->in(__DIR__);

/**
 * Make the package believe it runs inside an HTTP request: bind the request into
 * the container (the Request facade and url() follow it) and flip RuntimeContext's
 * cached console flag, which is otherwise always true under the test runner.
 */
function simulateHttpRequest(Request $request): Request
{
    app()->instance('request', $request);
    Facade::clearResolvedInstance('request');

    RuntimeContext::resetCache();
    Closure::bind(function () {
        static::$isConsole = false;
    }, null, RuntimeContext::class)();

    return $request;
}

/** Back to "artisan / queue worker" mode, with the placeholder request a console process has. */
function simulateConsole(): void
{
    app()->instance('request', Request::create('http://localhost', 'GET'));
    Facade::clearResolvedInstance('request');
    RuntimeContext::resetCache();
}

function postRequest(string $uri = '/orders', array $input = [], array $files = [], array $server = []): Request
{
    return Request::create($uri, 'POST', $input, [], $files, $server);
}

function jsonRequest(string $method, string $uri, mixed $payload): Request
{
    return Request::create($uri, $method, [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
    ], is_string($payload) ? $payload : json_encode($payload));
}

/** All activity rows, oldest first, read fresh from the database. */
function activities(): \Illuminate\Support\Collection
{
    return Activity::query()->orderBy('id')->get();
}

function lastActivity(): ?Activity
{
    return Activity::query()->orderByDesc('id')->first();
}

function requestBody(?Activity $activity): mixed
{
    return $activity?->properties?->toArray()['request_data']['body'] ?? null;
}
