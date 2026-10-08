<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\TestModel;

const BODY_MASK = RequestDataCollector::MASK;

beforeEach(function () {
    Exceptions::fake();
    config(['activitylog-browse.request_data.fields.body' => true]);
});

it('masks sensitive keys at any depth, case-insensitively, with wildcards', function () {
    simulateHttpRequest(jsonRequest('POST', '/settings', [
        'name' => 'Alice',
        'Password' => 'p1',
        'password_confirmation' => 'p1',
        'user' => [
            'NEW_PASSWORD' => 'p2',
            'profile' => ['api_key' => 'k1', 'Stripe_API_KEY' => 'k2', 'city' => 'Riyadh'],
        ],
        'access_token' => 't1',
        'X-Refresh-Token' => 't2',
        'pin_code' => '1234',
        'otp_code' => '9999',
        'otp' => '1111',
        'sms_otp' => '2222',
        'pincode_hint' => 'kept',
        'opt_in' => true,
        'items' => [['sku' => 'A1', 'card_number' => '4111']],
    ]));

    activity()->log('saved');

    expect(requestBody(lastActivity()))->toBe([
        'name' => 'Alice',
        'Password' => BODY_MASK,
        'password_confirmation' => BODY_MASK,
        'user' => [
            'NEW_PASSWORD' => BODY_MASK,
            'profile' => ['api_key' => BODY_MASK, 'Stripe_API_KEY' => BODY_MASK, 'city' => 'Riyadh'],
        ],
        'access_token' => BODY_MASK,
        'X-Refresh-Token' => BODY_MASK,
        'pin_code' => BODY_MASK,
        'otp_code' => BODY_MASK,
        'otp' => BODY_MASK,
        'sms_otp' => BODY_MASK,
        'pincode_hint' => 'kept',
        'opt_in' => true,
        'items' => [['sku' => 'A1', 'card_number' => BODY_MASK]],
    ]);
});

it('masks a whole sensitive subtree, not only scalar values', function () {
    simulateHttpRequest(jsonRequest('POST', '/settings', ['secrets' => ['a' => 1, 'b' => 2], 'keep' => 'x']));

    activity()->log('saved');

    expect(requestBody(lastActivity()))->toBe(['secrets' => BODY_MASK, 'keep' => 'x']);
});

it('masks the value of key/value-shaped settings payloads', function () {
    simulateHttpRequest(jsonRequest('PUT', '/settings', [
        'settings' => [
            ['key' => 'whatsapp_token', 'value' => 'abc'],
            ['key' => 'company_name', 'value' => 'ACME'],
            ['name' => 'SMTP_PASSWORD', 'value' => ['nested' => 'x']],
            ['key' => 'pin_code', 'value' => 1234],
        ],
    ]));

    activity()->log('saved');

    expect(requestBody(lastActivity())['settings'])->toBe([
        ['key' => 'whatsapp_token', 'value' => BODY_MASK],
        ['key' => 'company_name', 'value' => 'ACME'],
        ['name' => 'SMTP_PASSWORD', 'value' => BODY_MASK],
        ['key' => 'pin_code', 'value' => BODY_MASK],
    ]);
});

it('honours a custom masked_keys list (replacing the defaults)', function () {
    config(['activitylog-browse.request_data.body.masked_keys' => ['iban', 'national_*']]);
    simulateHttpRequest(postRequest('/clients', ['IBAN' => 'SA00', 'national_id' => '1010', 'password' => 'visible-now']));

    activity()->log('saved');

    expect(requestBody(lastActivity()))->toBe(['IBAN' => BODY_MASK, 'national_id' => BODY_MASK, 'password' => 'visible-now']);
});

it('reduces uploaded files to name, size and mime', function () {
    $request = postRequest('/documents', ['title' => 'Contract'], [
        'contract' => UploadedFile::fake()->create('contract.pdf', 12, 'application/pdf'),
        'photos' => [
            UploadedFile::fake()->create('a.jpg', 3, 'image/jpeg'),
            UploadedFile::fake()->create('b.png', 4, 'image/png'),
        ],
    ]);
    simulateHttpRequest($request);

    activity()->log('uploaded');

    expect(requestBody(lastActivity()))->toBe([
        'title' => 'Contract',
        'contract' => ['file' => 'contract.pdf', 'size' => 12 * 1024, 'mime' => 'application/pdf'],
        'photos' => [
            ['file' => 'a.jpg', 'size' => 3 * 1024, 'mime' => 'image/jpeg'],
            ['file' => 'b.png', 'size' => 4 * 1024, 'mime' => 'image/png'],
        ],
    ]);
});

it('keeps the row and scrubs invalid UTF-8 in values, keys and file names', function () {
    $request = postRequest('/documents', [
        'note' => "caf\xE9 au lait",
        "bad\xB1key" => 'value',
        'nested' => ['deep' => "\xC3\x28 broken"],
    ], [
        'scan' => UploadedFile::fake()->create("scan\xFF.pdf", 1, 'application/pdf'),
    ]);
    simulateHttpRequest($request);

    $model = TestModel::create(['name' => 'with bad bytes']);

    expect($model->exists)->toBeTrue()
        ->and(activities())->toHaveCount(1);

    $body = requestBody(lastActivity());
    expect($body)->toBeArray()
        ->and(mb_check_encoding(json_encode($body), 'UTF-8'))->toBeTrue()
        ->and($body['note'])->toBe('caf? au lait')
        ->and($body)->toHaveKey('bad?key')
        ->and($body['nested']['deep'])->toBe('?( broken')
        ->and($body['scan']['file'])->toBe('scan?.pdf');
    Exceptions::assertNothingReported();
});

it('clips long values to max_value_length', function () {
    config(['activitylog-browse.request_data.body.max_value_length' => 20]);
    simulateHttpRequest(postRequest('/notes', ['text' => str_repeat('a', 50), 'short' => 'ok', 'arabic' => str_repeat('ب', 30)]));

    activity()->log('saved');

    $body = requestBody(lastActivity());
    expect($body['text'])->toBe(str_repeat('a', 20) . '...')
        ->and($body['short'])->toBe('ok')
        ->and($body['arabic'])->toBe(str_repeat('ب', 20) . '...');
});

it('does not clip values when max_value_length is 0', function () {
    config(['activitylog-browse.request_data.body.max_value_length' => 0, 'activitylog-browse.request_data.body.max_length' => 0]);
    simulateHttpRequest(postRequest('/notes', ['text' => str_repeat('a', 5000)]));

    activity()->log('saved');

    expect(requestBody(lastActivity())['text'])->toHaveLength(5000);
});

it('stores an oversize body as a clipped JSON string', function () {
    config(['activitylog-browse.request_data.body.max_length' => 200]);
    $input = [];
    for ($i = 0; $i < 50; $i++) {
        $input["field_{$i}"] = "value number {$i} ب";
    }
    simulateHttpRequest(postRequest('/bulk', $input));

    activity()->log('bulk');

    $body = requestBody(lastActivity());
    expect($body)->toBeString()
        ->and($body)->toStartWith('{"field_0":"value number 0 ب"')
        ->and($body)->toEndWith('…')
        ->and(strlen($body))->toBeLessThanOrEqual(200 + strlen('…'))
        ->and(mb_check_encoding($body, 'UTF-8'))->toBeTrue();
});

it('stores no body for GET and HEAD requests', function (string $method) {
    simulateHttpRequest(Request::create('/orders?page=2&password=x', $method));

    activity()->log('read');

    expect(lastActivity()->properties['request_data'])->not->toHaveKey('body')
        ->and(lastActivity()->properties['request_data']['method'])->toBe($method);
})->with(['GET', 'HEAD']);

it('stores no body when the POST is empty', function () {
    simulateHttpRequest(postRequest('/ping'));

    activity()->log('ping');

    expect(lastActivity()->properties['request_data'])->not->toHaveKey('body');
});

it('captures the body of a method-spoofed form (POST with _method=PUT)', function () {
    simulateHttpRequest(postRequest('/orders/1', ['_method' => 'PUT', 'name' => 'x']));

    activity()->log('updated');

    expect(requestBody(lastActivity()))->toBe(['_method' => 'PUT', 'name' => 'x']);
});

it('does not capture the body when fields.body is off', function () {
    config(['activitylog-browse.request_data.fields.body' => false]);
    simulateHttpRequest(postRequest('/orders', ['name' => 'x']));

    activity()->log('saved');

    expect(lastActivity()->properties['request_data'])->not->toHaveKey('body')
        ->and(lastActivity()->properties['request_data']['url'])->toBe('http://localhost/orders');
});

it('stores no body for console activities', function () {
    activity()->log('from artisan');

    expect(lastActivity()->properties['request_data'] ?? [])->not->toHaveKey('body');
});
