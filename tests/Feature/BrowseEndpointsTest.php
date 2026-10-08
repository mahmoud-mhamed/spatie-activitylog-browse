<?php

use Illuminate\Support\Facades\Exceptions;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\TestModel;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Exceptions::fake();
});

/** created → name B → name C + status paid; returns [model, created, second, third activities]. */
function modelWithHistory(): array
{
    $model = TestModel::create(['name' => 'A', 'status' => 'pending', 'price' => '10.00', 'password' => 'hashed']);
    $model->update(['name' => 'B']);
    $model->update(['name' => 'C', 'status' => 'paid']);

    return [$model->fresh(), ...activities()->all()];
}

function enableRestore(): void
{
    config(['activitylog-browse.browse.restore.enabled' => true]);
}

describe('changes', function () {
    it('returns old/new rows with enum labels', function () {
        [, , , $third] = modelWithHistory();

        $this->getJson("/activity-log/{$third->id}/changes")
            ->assertOk()
            ->assertJsonPath('has_old', true)
            ->assertJsonPath('has_new', true)
            ->assertJsonPath('rows.0.key', 'name')
            ->assertJsonPath('rows.0.old', 'B')
            ->assertJsonPath('rows.0.new', 'C')
            ->assertJsonPath('rows.1.key', 'status')
            ->assertJsonPath('rows.1.old_display', 'Waiting for payment')
            ->assertJsonPath('rows.1.new_display', 'Fully paid');
    });

    it('returns no rows for an activity without changes', function () {
        activity()->log('plain');

        $this->getJson('/activity-log/' . lastActivity()->id . '/changes')
            ->assertOk()
            ->assertExactJson(['has_old' => false, 'has_new' => false, 'rows' => []]);
    });

    it('returns 404 for an unknown activity', function () {
        $this->getJson('/activity-log/999/changes')->assertNotFound();
    });
});

describe('subject attributes', function () {
    it('returns the live subject attributes without excluded ones', function () {
        [, $created] = modelWithHistory();

        $rows = collect($this->getJson("/activity-log/{$created->id}/attributes")->assertOk()->json('rows'));

        expect($rows->pluck('key'))->not->toContain('password')
            ->and($rows->firstWhere('key', 'name')['value'])->toBe('C')
            ->and($rows->firstWhere('key', 'status'))->toMatchArray(['value' => 'paid', 'display' => 'Fully paid'])
            // SQLite hands the decimal back as '10'; the decimal:2 cast gives the display form.
            ->and($rows->firstWhere('key', 'price'))->toMatchArray(['value' => '10', 'display' => '10.00']);
    });

    it('returns no rows when the subject is gone', function () {
        [$model, $created] = modelWithHistory();
        $model->delete();

        $this->getJson("/activity-log/{$created->id}/attributes")->assertOk()->assertExactJson(['rows' => []]);
    });

    it('returns causer attributes', function () {
        $causer = TestModel::create(['name' => 'Causer']);
        activity()->causedBy($causer)->log('by causer');

        $rows = collect($this->getJson('/activity-log/' . lastActivity()->id . '/causer-attributes')->assertOk()->json('rows'));
        expect($rows->firstWhere('key', 'name')['value'])->toBe('Causer');
    });
});

describe('request details', function () {
    it('shows the stored request data and body of the activity that carries it', function () {
        simulateHttpRequest(postRequest('/orders', ['name' => 'Widget', 'password' => 'secret'], [], ['REMOTE_ADDR' => '10.0.0.9']));
        TestModel::create(['name' => 'Widget']);
        activity()->log('second in request');
        $first = activities()->first();
        simulateConsole();

        $json = $this->getJson("/activity-log/{$first->id}/request-details")->assertOk()->json();

        expect($json['id'])->toBe($first->id)
            ->and($json['request_id'])->toBe($first->request_id)
            ->and($json['request_count'])->toBe(2)
            ->and($json['request_logs_url'])->toContain('request_id=' . $first->request_id);

        $sections = collect($json['sections'])->keyBy('title');
        $request = collect($sections['Request Data']['rows'])->keyBy('key');
        $body = $sections['Body'];

        expect($request['method'])->toMatchArray(['kind' => 'badge', 'value' => 'POST'])
            ->and($request['url']['filter'])->toBe(['key' => 'url', 'value' => 'http://localhost/orders'])
            ->and($request)->not->toHaveKey('body')
            ->and($body['truncated'])->toBeFalse()
            ->and($body['note'])->toBeNull()
            ->and(collect($body['rows'])->pluck('value', 'key')->all())->toBe(['name' => 'Widget', 'password' => RequestDataCollector::MASK])
            // A masked value is not offered as a filter link.
            ->and(collect($body['rows'])->firstWhere('key', 'password'))->not->toHaveKey('filter')
            ->and(collect($sections['Device Data']['rows'])->firstWhere('key', 'ip')['value'])->toBe('10.0.0.9');
    });

    it('works for console activities without a request id', function () {
        activity()->withProperties(['custom' => 'value'])->log('console');

        $json = $this->getJson('/activity-log/' . lastActivity()->id . '/request-details')->assertOk()->json();

        expect($json['request_id'])->toBeNull()
            ->and($json['request_count'])->toBe(0)
            ->and(collect($json['sections'])->pluck('title'))->toContain('Other Data');
    });

    it('marks a clipped body as truncated', function () {
        config(['activitylog-browse.request_data.body.max_length' => 50]);
        simulateHttpRequest(postRequest('/bulk', ['a' => str_repeat('x', 200)]));
        activity()->log('bulk');
        simulateConsole();

        $body = collect($this->getJson('/activity-log/' . lastActivity()->id . '/request-details')->json('sections'))->firstWhere('title', 'Body');

        expect($body['truncated'])->toBeTrue()
            ->and($body['rows'][0]['kind'])->toBe('pre');
    });
});

describe('pages', function () {
    it('renders the index', function () {
        modelWithHistory();

        $this->get('/activity-log')->assertOk()->assertSee('created TestModel');
    });

    it('filters the index by request id', function () {
        simulateHttpRequest(postRequest('/a', ['x' => 1]));
        activity()->log('in request A');
        $requestId = lastActivity()->request_id;
        simulateConsole();
        activity()->log('outside any request');

        $this->get('/activity-log?request_id=' . $requestId)
            ->assertOk()
            ->assertSee('in request A')
            ->assertDontSee('outside any request');
    });

    it('renders the show page', function () {
        [, , , $third] = modelWithHistory();

        $this->get("/activity-log/{$third->id}")->assertOk()->assertSee('Fully paid');
    });

    it('renders the timeline of a subject', function () {
        [$model] = modelWithHistory();

        $this->get('/activity-log/timeline?subject_type=' . urlencode(TestModel::class) . '&subject_id=' . $model->id)
            ->assertOk()
            ->assertSee('Fully paid');
    });

    it('returns 404 for a timeline without a subject', function () {
        $this->get('/activity-log/timeline')->assertNotFound();
    });

    it('asks for the password when the password gate is on', function () {
        config(['activitylog-browse.browse.password' => 'let-me-in']);

        $this->get('/activity-log')->assertRedirect('/activity-log/login');
        $this->getJson('/activity-log/1/changes')->assertRedirect('/activity-log/login');
    });
});

describe('restore', function () {
    it('is forbidden unless enabled', function () {
        [, , $second] = modelWithHistory();

        $this->getJson("/activity-log/{$second->id}/restore-preview")->assertForbidden();
        $this->post("/activity-log/{$second->id}/restore")->assertForbidden();
        expect(TestModel::query()->value('name'))->toBe('C');
    });

    it('is forbidden when the restore gate denies', function () {
        enableRestore();
        config(['activitylog-browse.browse.restore.gate' => 'restore-activity']);
        Illuminate\Support\Facades\Gate::define('restore-activity', fn ($user = null) => false);
        [, , $second] = modelWithHistory();

        $this->getJson("/activity-log/{$second->id}/restore-preview")->assertForbidden();
    });

    it('previews the values that restoring would write', function () {
        enableRestore();
        [, , $second] = modelWithHistory();

        $rows = collect($this->getJson("/activity-log/{$second->id}/restore-preview")->assertOk()->json('rows'))->keyBy('key');

        expect($rows->keys()->sort()->values()->all())->toBe(['name', 'status'])
            ->and($rows['name'])->toMatchArray(['current' => 'C', 'restored' => 'B'])
            ->and($rows['status'])->toMatchArray([
                'current' => 'paid', 'current_display' => 'Fully paid',
                'restored' => 'pending', 'restored_display' => 'Waiting for payment',
            ]);
    });

    it('restores the subject to the version right after the activity and logs the restore', function () {
        enableRestore();
        [$model, $created] = modelWithHistory();
        $before = activities()->count();

        $this->from('/activity-log/timeline')->post("/activity-log/{$created->id}/restore")
            ->assertRedirect('/activity-log/timeline')
            ->assertSessionHas('activitylog_browse_success');

        $model->refresh();
        expect($model->name)->toBe('A')
            ->and($model->status->value)->toBe('pending')
            ->and($model->price)->toBe('10.00')
            ->and(activities()->count())->toBe($before + 1)
            ->and(lastActivity()->event)->toBe('updated')
            ->and(lastActivity()->properties['attributes'])->toMatchArray(['name' => 'A', 'status' => 'pending']);
    });

    it('restores a value that was null before a later change', function () {
        enableRestore();
        $model = TestModel::create(['name' => 'A']);
        $created = lastActivity();
        // A fresh instance (as in a later request) has every column loaded, so the log records old notes = null.
        TestModel::find($model->id)->update(['notes' => 'added later']);

        $this->post("/activity-log/{$created->id}/restore")->assertRedirect();

        expect($model->fresh()->notes)->toBeNull();
    });

    it('reports nothing to restore for the latest activity', function () {
        enableRestore();
        [, , , $third] = modelWithHistory();

        $this->getJson("/activity-log/{$third->id}/restore-preview")->assertOk()->assertExactJson(['rows' => []]);
        $this->post("/activity-log/{$third->id}/restore")->assertSessionHas('activitylog_browse_success', 'The record already matches this version.');
    });

    it('never restores excluded attributes, keys or timestamps', function () {
        enableRestore();
        $model = TestModel::create(['name' => 'A', 'password' => 'old-hash']);
        $created = lastActivity();
        $model->update(['name' => 'B', 'password' => 'new-hash']);
        // Simulate a log that carried protected columns anyway.
        Activity::query()->latest('id')->first()->forceFill(['properties' => [
            'old' => ['name' => 'A', 'password' => 'old-hash', 'id' => 99, 'created_at' => '2000-01-01 00:00:00'],
            'attributes' => ['name' => 'B'],
        ]])->save();

        $rows = collect($this->getJson("/activity-log/{$created->id}/restore-preview")->assertOk()->json('rows'));

        expect($rows->pluck('key')->all())->toBe(['name']);
    });

    it('refuses deleted activities and missing subjects', function () {
        enableRestore();
        [$model, $created] = modelWithHistory();
        $model->delete();
        $deleted = lastActivity();

        $this->getJson("/activity-log/{$deleted->id}/restore-preview")->assertStatus(422);
        $this->getJson("/activity-log/{$created->id}/restore-preview")->assertNotFound();
    });
});
