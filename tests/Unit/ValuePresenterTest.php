<?php

use Mhamed\SpatieActivitylogBrowse\Support\ValuePresenter;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\LabelWithArgsEnum;
use Mhamed\SpatieActivitylogBrowse\Tests\Support\TestModel;

function addTranslations(array $lines, string $locale = 'en'): void
{
    app('translator')->addLines($lines, $locale);
}

/** A model whose only cast is the given one, for display() checks. */
function modelWithCast(string $key, string $cast): string
{
    $class = 'CastModel' . md5($key . $cast);
    if (! class_exists($class)) {
        eval("class {$class} extends Illuminate\\Database\\Eloquent\\Model { protected \$casts = ['{$key}' => '" . addslashes($cast) . "']; }");
    }

    return $class;
}

describe('attributeLabel', function () {
    it('uses validation.attributes when translated', function () {
        addTranslations(['validation.attributes.email_address' => 'E-mail']);
        addTranslations(['validation.attributes.email_address' => 'البريد الإلكتروني'], 'ar');

        expect(ValuePresenter::attributeLabel('email_address'))->toBe('E-mail (email_address)');

        app()->setLocale('ar');
        expect(ValuePresenter::attributeLabel('email_address'))->toBe('البريد الإلكتروني (email_address)');
    });

    it('falls back to a headline of the key', function () {
        expect(ValuePresenter::attributeLabel('first_name'))->toBe('First Name (first_name)')
            ->and(ValuePresenter::attributeLabel('meta'))->toBe('Meta (meta)');
    });

    it('returns the bare key when the headline adds nothing', function () {
        expect(ValuePresenter::attributeLabel('Name'))->toBe('Name');
    });

    it('ignores an array translation (nested validation attributes)', function () {
        addTranslations(['validation.attributes.address' => ['city' => 'City']]);

        expect(ValuePresenter::attributeLabel('address'))->toBe('Address (address)');
    });
});

describe('textParts', function () {
    it('returns null for short or identical texts', function () {
        expect(ValuePresenter::textParts('short old', 'short new'))->toBeNull()
            ->and(ValuePresenter::textParts(str_repeat('x', 100), str_repeat('x', 100)))->toBeNull();
    });

    it('highlights only the differing middle with trimmed context', function () {
        $old = str_repeat('a', 50) . 'OLD' . str_repeat('b', 50);
        $new = str_repeat('a', 50) . 'NEWER' . str_repeat('b', 50);

        [$oldParts, $newParts] = ValuePresenter::textParts($old, $new);

        expect($oldParts)->toBe([
            ['…' . str_repeat('a', 30), false],
            ['OLD', true],
            [str_repeat('b', 30) . '…', false],
        ])->and($newParts)->toBe([
            ['…' . str_repeat('a', 30), false],
            ['NEWER', true],
            [str_repeat('b', 30) . '…', false],
        ]);
    });

    it('keeps short unchanged ends whole and handles multibyte text', function () {
        $old = 'مرحبا ' . str_repeat('ب', 60) . ' نهاية';
        $new = 'مرحبا ' . str_repeat('ت', 60) . ' نهاية';

        [$oldParts, $newParts] = ValuePresenter::textParts($old, $new);

        expect($oldParts)->toBe([['مرحبا ', false], [str_repeat('ب', 60), true], [' نهاية', false]])
            ->and($newParts)->toBe([['مرحبا ', false], [str_repeat('ت', 60), true], [' نهاية', false]]);
    });

    it('handles pure insertions (one side has no changed middle)', function () {
        $old = str_repeat('a', 70);
        $new = str_repeat('a', 35) . 'INSERTED' . str_repeat('a', 35);

        [$oldParts, $newParts] = ValuePresenter::textParts($old, $new);

        expect(collect($oldParts)->where(1, true))->toBeEmpty()
            ->and(collect($newParts)->where(1, true)->pluck(0)->implode(''))->toBe('INSERTED')
            ->and(collect($oldParts)->pluck(0)->implode(''))->toBe('…' . str_repeat('a', 30) . str_repeat('a', 30) . '…');
    });

    it('diffs when only one side is long', function () {
        $parts = ValuePresenter::textParts('short', str_repeat('z', 80));

        expect($parts)->not->toBeNull()
            ->and($parts[0])->toBe([['short', true]]);
    });
});

describe('structuredChanges', function () {
    it('returns only the changed leaves as dot paths', function () {
        $old = ['a' => 1, 'b' => ['c' => 'x', 'd' => 'same'], 'list' => [1, 2]];
        $new = ['a' => 1, 'b' => ['c' => 'y', 'd' => 'same'], 'list' => [1, 3], 'added' => true];

        expect(ValuePresenter::structuredChanges($old, $new))->toBe([
            'b.c' => ['x', 'y'],
            'list.1' => [2, 3],
            'added' => [null, true],
        ]);
    });

    it('accepts JSON strings on either side', function () {
        expect(ValuePresenter::structuredChanges('{"a":{"b":1}}', ['a' => ['b' => 2]]))->toBe(['a.b' => [1, 2]])
            ->and(ValuePresenter::structuredChanges(' [1,2]', '[1,2,3]'))->toBe(['2' => [null, 3]]);
    });

    it('returns an empty array for equal content in different encodings', function () {
        expect(ValuePresenter::structuredChanges('{"a": 1, "b": "ب"}', '{"b":"ب","a":1}'))->toBe([]);
    });

    it('returns null unless both sides are structured', function () {
        expect(ValuePresenter::structuredChanges('plain', ['a' => 1]))->toBeNull()
            ->and(ValuePresenter::structuredChanges('{not json', '{"a":1}'))->toBeNull()
            ->and(ValuePresenter::structuredChanges(null, ['a' => 1]))->toBeNull()
            ->and(ValuePresenter::structuredChanges(5, 6))->toBeNull();
    });
});

describe('changeRows', function () {
    it('expands a JSON attribute into one row per changed leaf', function () {
        $rows = (new ValuePresenter)->changeRows(
            ['meta' => '{"note":"old note","tags":["a"],"same":1}', 'name' => 'Old'],
            ['meta' => '{"note":"new note","tags":["a","b"],"same":1}', 'name' => 'New'],
            TestModel::class,
        );

        expect($rows)->toBe([
            ['key' => 'meta.note', 'label' => 'Meta (meta) › note', 'old' => 'old note', 'new' => 'new note'],
            ['key' => 'meta.tags.1', 'label' => 'Meta (meta) › tags.1', 'old' => null, 'new' => 'b'],
            ['key' => 'name', 'label' => 'Name (name)', 'old' => 'Old', 'new' => 'New'],
        ]);
    });

    it('emits one same_content row when only the JSON encoding changed', function () {
        $rows = (new ValuePresenter)->changeRows(['meta' => '{"a": 1}'], ['meta' => '{"a":1}'], TestModel::class);

        expect($rows)->toBe([
            ['key' => 'meta', 'label' => 'Meta (meta)', 'old' => '{"a": 1}', 'new' => '{"a":1}', 'same_content' => true],
        ]);
    });

    it('keeps a whole-value row when the attribute exists on one side only', function () {
        $rows = (new ValuePresenter)->changeRows([], ['meta' => ['a' => 1], 'name' => 'x'], TestModel::class);

        expect($rows)->toBe([
            ['key' => 'meta', 'label' => 'Meta (meta)', 'old' => null, 'new' => '{"a":1}'],
            ['key' => 'name', 'label' => 'Name (name)', 'old' => null, 'new' => 'x'],
        ]);
    });

    it('shows enum labels from an enum method', function () {
        $rows = (new ValuePresenter)->changeRows(['status' => 'pending'], ['status' => 'paid'], TestModel::class);

        expect($rows[0])->toMatchArray([
            'key' => 'status',
            'old' => 'pending',
            'new' => 'paid',
            'old_display' => 'Waiting for payment',
            'new_display' => 'Fully paid',
        ]);
    });

    it('shows enum labels from enums.{EnumBasename}.{value} translations', function () {
        addTranslations(['enums.PriorityEnum.1' => 'Low priority', 'enums.PriorityEnum.2' => 'High priority']);

        // Raw values may come back as numeric strings from the database.
        $rows = (new ValuePresenter)->changeRows(['priority' => '1'], ['priority' => 2], TestModel::class);

        expect($rows[0])->toMatchArray(['old_display' => 'Low priority', 'new_display' => 'High priority']);
    });

    it('shows no label for unknown enum values or enums without labels', function () {
        $rows = (new ValuePresenter)->changeRows(['status' => 'archived', 'priority' => 1], ['status' => 'paid', 'priority' => 2], TestModel::class);

        expect($rows[0])->not->toHaveKeys(['old_display', 'new_display'])
            ->and($rows[1])->not->toHaveKey('old_display')
            ->and($rows[1]['new_display'])->toBe('Fully paid');
    });

    it('shows true/false translations for boolean casts', function () {
        $rows = (new ValuePresenter)->changeRows(['is_active' => 0], ['is_active' => 1], TestModel::class);
        expect($rows[0])->toMatchArray(['old' => '0', 'new' => '1', 'old_display' => 'false', 'new_display' => 'true']);

        app()->setLocale('ar');
        $rows = (new ValuePresenter)->changeRows(['is_active' => false], ['is_active' => true], TestModel::class);
        expect($rows[0])->toMatchArray(['old' => 'false', 'new' => 'true', 'old_display' => 'لا', 'new_display' => 'نعم']);
    });

    it('formats decimal:N casts', function () {
        $rows = (new ValuePresenter)->changeRows(['price' => '1234.5'], ['price' => '99.00'], TestModel::class);

        expect($rows[0])->toMatchArray(['old' => '1234.5', 'old_display' => '1,234.50', 'new' => '99.00'])
            // Already in the display form: no duplicate display value.
            ->and($rows[0])->not->toHaveKey('new_display');
    });

    it('highlights the changed part of long texts', function () {
        $rows = (new ValuePresenter)->changeRows(
            ['notes' => str_repeat('x', 40) . ' first ' . str_repeat('y', 40)],
            ['notes' => str_repeat('x', 40) . ' second ' . str_repeat('y', 40)],
            TestModel::class,
        );

        expect(collect($rows[0]['old_parts'])->where(1, true)->pluck(0)->all())->toBe(['first'])
            ->and(collect($rows[0]['new_parts'])->where(1, true)->pluck(0)->all())->toBe(['second']);
    });

    it('does not fail for unknown model classes', function () {
        $rows = (new ValuePresenter)->changeRows(['status' => 'pending'], ['status' => 'paid'], 'App\\Models\\DoesNotExist');

        expect($rows[0])->toBe(['key' => 'status', 'label' => 'Status (status)', 'old' => 'pending', 'new' => 'paid']);
    });
});

describe('display', function () {
    it('skips enum label methods that need arguments', function () {
        $class = modelWithCast('kind', LabelWithArgsEnum::class);

        expect((new ValuePresenter)->display($class, 'kind', 'one'))->toBe('Title of one');
    });

    it('returns null for empty, array or uncast values', function () {
        $presenter = new ValuePresenter;

        expect($presenter->display(TestModel::class, 'status', null))->toBeNull()
            ->and($presenter->display(TestModel::class, 'status', ''))->toBeNull()
            ->and($presenter->display(TestModel::class, 'meta', ['a' => 1]))->toBeNull()
            ->and($presenter->display(TestModel::class, 'name', 'x'))->toBeNull()
            ->and($presenter->display(null, 'status', 'paid'))->toBeNull();
    });

    it('swallows a value that does not fit an int-backed enum', function () {
        expect((new ValuePresenter)->display(TestModel::class, 'priority', 'not-a-number'))->toBeNull();
    });
});

describe('attributeRows', function () {
    it('sorts by key and adds display values', function () {
        addTranslations(['validation.attributes.status' => 'Payment status']);

        $rows = (new ValuePresenter)->attributeRows(
            ['status' => 'paid', 'name' => 'Widget', 'meta' => ['a' => 1], 'is_active' => true, 'notes' => null],
            TestModel::class,
        );

        expect($rows)->toBe([
            ['key' => 'is_active', 'label' => 'Is Active (is_active)', 'value' => 'true', 'display' => 'true'],
            ['key' => 'meta', 'label' => 'Meta (meta)', 'value' => '{"a":1}'],
            ['key' => 'name', 'label' => 'Name (name)', 'value' => 'Widget'],
            ['key' => 'notes', 'label' => 'Notes (notes)', 'value' => null],
            ['key' => 'status', 'label' => 'Payment status (status)', 'value' => 'paid', 'display' => 'Fully paid'],
        ]);
    });
});
