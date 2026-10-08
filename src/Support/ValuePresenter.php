<?php

namespace Mhamed\SpatieActivitylogBrowse\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Turns stored attribute values into what the browse UI shows: translated
 * attribute names, enum labels / yes-no / formatted amounts (read from the
 * subject model's casts and the app's own translation files, no config), and
 * diffs that point at the part of a long text or JSON value that changed.
 */
class ValuePresenter
{
    /** Texts shorter than this are shown whole; longer ones get the changed part highlighted. */
    private const MIN_DIFF_TEXT_LENGTH = 60;

    /** Unchanged characters kept on each side of a highlighted change. */
    private const DIFF_CONTEXT = 30;

    /** Enum methods tried, in order, for a human label (first one without required parameters wins). */
    private const ENUM_LABEL_METHODS = ['getLabel', 'label', 'translate', 'getTranslate', 'title'];

    /** @var array<string, array<string, mixed>> */
    private array $casts = [];

    /** "Translated name (key)" from validation.attributes, else "Headline (key)". */
    public static function attributeLabel(string $key): string
    {
        $langKey = "validation.attributes.{$key}";
        $translated = Lang::has($langKey) ? __($langKey) : null;

        if (is_string($translated) && $translated !== '') {
            return "{$translated} ({$key})";
        }

        $headline = Str::headline($key);

        return $headline !== $key ? "{$headline} ({$key})" : $key;
    }

    /**
     * Rows for the changes dialog / show page: [{key, label, old, new, old_display?,
     * new_display?, old_parts?, new_parts?, same_content?}]. A JSON/array attribute
     * becomes one row per changed leaf ("meta.note") instead of two full blobs.
     */
    public function changeRows(array $old, array $new, ?string $modelClass): array
    {
        $keys = array_unique(array_merge(array_keys($old), array_keys($new)));
        sort($keys);

        $rows = [];
        foreach ($keys as $key) {
            $key = (string) $key;
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;
            $label = self::attributeLabel($key);

            $leaves = array_key_exists($key, $old) && array_key_exists($key, $new)
                ? self::structuredChanges($oldValue, $newValue)
                : null;

            if ($leaves) {
                foreach ($leaves as $path => [$oldLeaf, $newLeaf]) {
                    $rows[] = $this->changeRow("{$key}.{$path}", "{$label} › {$path}", $oldLeaf, $newLeaf, null);
                }
                continue;
            }

            if ($leaves === []) {
                // Same JSON content, only the encoding changed (spacing, \u escapes, key order):
                // a character diff of the raw strings would point at noise.
                $rows[] = ['key' => $key, 'label' => $label, 'old' => self::text($oldValue), 'new' => self::text($newValue), 'same_content' => true];
                continue;
            }

            $rows[] = $this->changeRow($key, $label, $oldValue, $newValue, $modelClass);
        }

        return $rows;
    }

    /** Rows for the attributes dialog: [{key, label, value, display?}] sorted by key. */
    public function attributeRows(array $attributes, ?string $modelClass): array
    {
        ksort($attributes);

        $rows = [];
        foreach ($attributes as $key => $value) {
            $row = ['key' => (string) $key, 'label' => self::attributeLabel((string) $key), 'value' => self::text($value)];
            if (($display = $this->display($modelClass, (string) $key, $value)) !== null) {
                $row['display'] = $display;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * Human form of a stored value when the model's casts give it meaning (enum
     * label, yes/no, formatted amount); null when the raw value is all there is.
     */
    public function display(?string $modelClass, string $key, mixed $value): ?string
    {
        if ($value === null || $value === '' || is_array($value) || ! $modelClass) {
            return null;
        }

        $cast = $this->castsFor($modelClass)[$key] ?? null;
        if (! is_string($cast)) {
            return null;
        }

        try {
            $display = match (true) {
                enum_exists($cast) => self::enumLabel($cast, $value),
                in_array($cast, ['bool', 'boolean'], true) => __(filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'activitylog-browse::messages.true' : 'activitylog-browse::messages.false'),
                str_starts_with($cast, 'decimal:') && is_numeric($value) => number_format((float) $value, (int) Str::after($cast, 'decimal:')),
                default => null,
            };
        } catch (\Throwable) {
            // A label is a nicety: show the raw value rather than fail the page.
            return null;
        }

        return is_string($display) && $display !== (string) $value ? $display : null;
    }

    private function changeRow(string $key, string $label, mixed $old, mixed $new, ?string $modelClass): array
    {
        $row = ['key' => $key, 'label' => $label, 'old' => self::text($old), 'new' => self::text($new)];

        foreach (['old' => $old, 'new' => $new] as $side => $value) {
            if (($display = $this->display($modelClass, $key, $value)) !== null) {
                $row["{$side}_display"] = $display;
            }
        }

        if (is_string($row['old']) && is_string($row['new']) && ($parts = self::textParts($row['old'], $row['new']))) {
            [$row['old_parts'], $row['new_parts']] = $parts;
        }

        return $row;
    }

    /**
     * For a change to a long text: [oldParts, newParts], each a list of
     * [text, changed] around the differing middle, unchanged ends trimmed to
     * DIFF_CONTEXT characters. Null for short or identical texts.
     */
    public static function textParts(string $old, string $new): ?array
    {
        if ($old === $new || (mb_strlen($old) < self::MIN_DIFF_TEXT_LENGTH && mb_strlen($new) < self::MIN_DIFF_TEXT_LENGTH)) {
            return null;
        }

        $a = mb_str_split($old);
        $b = mb_str_split($new);
        $lenA = count($a);
        $lenB = count($b);

        $prefix = 0;
        while ($prefix < $lenA && $prefix < $lenB && $a[$prefix] === $b[$prefix]) {
            $prefix++;
        }

        $suffix = 0;
        while ($suffix < $lenA - $prefix && $suffix < $lenB - $prefix && $a[$lenA - 1 - $suffix] === $b[$lenB - 1 - $suffix]) {
            $suffix++;
        }

        $split = function (array $chars, int $length) use ($prefix, $suffix): array {
            $head = implode('', array_slice($chars, 0, $prefix));
            $middle = implode('', array_slice($chars, $prefix, $length - $prefix - $suffix));
            $tail = implode('', array_slice($chars, $length - $suffix));

            return array_values(array_filter([
                $head !== '' ? [mb_strlen($head) > self::DIFF_CONTEXT ? '…' . mb_substr($head, -self::DIFF_CONTEXT) : $head, false] : null,
                $middle !== '' ? [$middle, true] : null,
                $tail !== '' ? [mb_strlen($tail) > self::DIFF_CONTEXT ? mb_substr($tail, 0, self::DIFF_CONTEXT) . '…' : $tail, false] : null,
            ]));
        };

        return [$split($a, $lenA), $split($b, $lenB)];
    }

    /**
     * Changed leaves of two structured values (arrays or JSON strings), as
     * [dotPath => [old, new]]. Null unless both sides are structured.
     */
    public static function structuredChanges(mixed $old, mixed $new): ?array
    {
        $old = self::decodeStructured($old);
        $new = self::decodeStructured($new);

        if (! is_array($old) || ! is_array($new)) {
            return null;
        }

        $flatOld = Arr::dot($old);
        $flatNew = Arr::dot($new);

        $changes = [];
        foreach (array_unique(array_merge(array_keys($flatOld), array_keys($flatNew))) as $path) {
            $before = $flatOld[$path] ?? null;
            $after = $flatNew[$path] ?? null;
            if ($before !== $after) {
                $changes[(string) $path] = [$before, $after];
            }
        }

        return $changes;
    }

    /** Stored value as display text (arrays as compact JSON, booleans as true/false). */
    public static function text(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            is_bool($value) => $value ? 'true' : 'false',
            is_array($value) || is_object($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            default => (string) $value,
        };
    }

    private static function decodeStructured(mixed $value): ?array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && in_array(ltrim($value)[0] ?? '', ['{', '['], true)) {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? $decoded : null;
        }

        return null;
    }

    /** @param class-string<\UnitEnum> $enum */
    private static function enumLabel(string $enum, mixed $value): ?string
    {
        $case = is_subclass_of($enum, \BackedEnum::class) ? $enum::tryFrom(is_numeric($value) && is_int($enum::cases()[0]->value ?? null) ? (int) $value : (string) $value) : null;
        if (! $case) {
            return null;
        }

        foreach (self::ENUM_LABEL_METHODS as $method) {
            if (method_exists($case, $method) && (new \ReflectionMethod($case, $method))->getNumberOfRequiredParameters() === 0) {
                $label = $case->{$method}();
                if (is_string($label) && $label !== '') {
                    return $label;
                }
            }
        }

        // Convention used by many apps: lang/{locale}/enums.php => ['StatusEnum' => ['pending' => '...']]
        $langKey = 'enums.' . class_basename($enum) . '.' . $case->value;

        return Lang::has($langKey) ? __($langKey) : null;
    }

    /** @return array<string, mixed> */
    private function castsFor(string $modelClass): array
    {
        if (! array_key_exists($modelClass, $this->casts)) {
            try {
                // subject_type may be a morph-map alias rather than a class name.
                $class = Relation::getMorphedModel($modelClass) ?? $modelClass;
                $this->casts[$modelClass] = is_subclass_of($class, Model::class) ? (new $class)->getCasts() : [];
            } catch (\Throwable) {
                $this->casts[$modelClass] = [];
            }
        }

        return $this->casts[$modelClass];
    }
}
