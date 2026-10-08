<?php

namespace Mhamed\SpatieActivitylogBrowse\Helpers;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class RequestDataCollector
{
    /**
     * Fallbacks for `request_data.body`. Laravel merges package config only one
     * level deep, so apps that published the config before this option existed
     * won't have these nested keys.
     */
    private const DEFAULT_MASKED_KEYS = [
        '*password*', '*passwd*', 'pwd', '*token*', '*secret*',
        '*api_key*', '*apikey*', '*private_key*', 'authorization',
        'otp', 'otp_*', '*_otp', 'pin', 'pin_code', '*_pin', 'verification_code',
        'cvv', 'cvc', 'card_number', 'credit_card',
    ];

    private const DEFAULT_MAX_LENGTH = 10000;

    private const DEFAULT_MAX_VALUE_LENGTH = 1000;

    public const MASK = '********';

    /**
     * One request can produce many activities; sanitize its body once and reuse it.
     *
     * @var \WeakMap<\Illuminate\Http\Request, array|string|null>|null
     */
    private static ?\WeakMap $bodyCache = null;

    /** @var \WeakMap<\Illuminate\Http\Request, string>|null */
    private static ?\WeakMap $requestIds = null;

    /** @var \WeakMap<\Illuminate\Http\Request, true>|null */
    private static ?\WeakMap $bodyClaimed = null;

    /**
     * Group ids of the queue jobs running in this process, keyed by job object id.
     * A stack because a job can run another one synchronously (sync driver).
     *
     * @var array<int, string>
     */
    private static array $jobRequestIds = [];

    /** Payload key that carries the group id from the dispatching request/job to the worker. */
    public const JOB_PAYLOAD_KEY = 'activitylog_request_id';

    public static function collect(): array
    {
        // Jobs, scheduled tasks and commands only have a placeholder request (APP_URL, GET).
        if (! RuntimeContext::isHttpRequest()) {
            return [];
        }

        $config = config('activitylog-browse.request_data');

        if (! ($config['enabled'] ?? false)) {
            return [];
        }

        $fields = $config['fields'] ?? [];
        $data = [];

        if ($fields['url'] ?? false) {
            $data['url'] = self::scrub(Request::fullUrl());
        }

        if ($fields['previous_url'] ?? false) {
            $data['previous_url'] = self::scrub(url()->previous());
        }

        if ($fields['method'] ?? false) {
            $data['method'] = self::scrub(Request::method());
        }

        if ($fields['route_name'] ?? false) {
            $data['route_name'] = self::scrub(Request::route()?->getName());
        }

        if ($fields['body'] ?? false) {
            $body = self::cachedBody(is_array($config['body'] ?? null) ? $config['body'] : []);
            if ($body !== null) {
                $data['body'] = $body;
            }
        }

        return $data ? ['request_data' => $data] : [];
    }

    /** Client-controlled strings may be invalid UTF-8, which would make the whole properties JSON fail to encode. */
    public static function scrub(?string $value): ?string
    {
        return $value === null ? null : mb_scrub($value, 'UTF-8');
    }

    /**
     * UUID shared by every activity logged during the current HTTP request, or the
     * running queue job (which inherits the id of the request/job that dispatched it).
     * Null for scheduled tasks and artisan commands: their Request instance lives for
     * the whole process, so grouping by it would merge unrelated work.
     */
    public static function requestId(): ?string
    {
        $config = config('activitylog-browse.request_data');

        if (! ($config['enabled'] ?? false) || ! ($config['fields']['request_id'] ?? true)) {
            return null;
        }

        if (self::$jobRequestIds) {
            return end(self::$jobRequestIds);
        }

        if (! RuntimeContext::isHttpRequest()) {
            return null;
        }

        $request = Request::instance();
        self::$requestIds ??= new \WeakMap();

        if (! self::$requestIds->offsetExists($request)) {
            self::$requestIds[$request] = (string) Str::uuid();
        }

        return self::$requestIds[$request];
    }

    /** Group id to put in the payload of a job dispatched now (null when there is none). */
    public static function payloadForDispatch(): array
    {
        $requestId = self::requestId();

        return $requestId !== null ? [self::JOB_PAYLOAD_KEY => $requestId] : [];
    }

    /**
     * A queue job starts: use the id it was dispatched with, or a fresh one so the
     * job's own changes are still grouped.
     */
    public static function beginJob(object $job, ?string $requestId): void
    {
        self::$jobRequestIds[spl_object_id($job)] = $requestId ?: (string) Str::uuid();
    }

    /** Safe to call more than once per job (a failing job fires several events). */
    public static function endJob(object $job): void
    {
        unset(self::$jobRequestIds[spl_object_id($job)]);
    }

    /** Whether an activity of the current request already stored the body (see claimBody()). */
    public static function isBodyClaimed(): bool
    {
        return self::$bodyClaimed !== null && self::$bodyClaimed->offsetExists(Request::instance());
    }

    /**
     * True the first time it is called for the current request, false afterwards.
     * Used to keep the (identical) body on the request's first activity only.
     */
    public static function claimBody(): bool
    {
        $request = Request::instance();
        self::$bodyClaimed ??= new \WeakMap();

        if (self::$bodyClaimed->offsetExists($request)) {
            return false;
        }

        self::$bodyClaimed[$request] = true;

        return true;
    }

    /**
     * Let the next activity of this request store the body again. Called on any
     * transaction rollback: the activity that claimed it may be gone. A second
     * copy is harmless; a lost body is not.
     */
    public static function releaseBody(): void
    {
        if (self::$bodyClaimed !== null) {
            unset(self::$bodyClaimed[Request::instance()]);
        }
    }

    private static function cachedBody(array $config): array|string|null
    {
        $request = Request::instance();
        self::$bodyCache ??= new \WeakMap();

        if (! self::$bodyCache->offsetExists($request)) {
            try {
                self::$bodyCache[$request] = self::body($request, $config);
            } catch (\Throwable $e) {
                // This runs inside the Activity "creating" observer: a failure here
                // must not break the model save that triggered the log entry.
                report($e);
                self::$bodyCache[$request] = null;
            }
        }

        return self::$bodyCache[$request];
    }

    /**
     * Sanitized request body: sensitive keys masked, uploaded files reduced to
     * metadata, long values clipped, invalid UTF-8 scrubbed. Returns a clipped
     * JSON string when the result still exceeds `max_length`, or null when
     * there is no body.
     */
    private static function body(\Illuminate\Http\Request $request, array $config): array|string|null
    {
        $input = match (true) {
            $request->isJson() => $request->json()->all(),
            in_array($request->getRealMethod(), ['GET', 'HEAD'], true) => [],
            default => $request->request->all(),
        };

        $files = self::describeFiles($request->allFiles());
        $body = $files ? array_replace_recursive($input, $files) : $input;

        if (empty($body)) {
            return null;
        }

        $patterns = array_map('strtolower', (array) ($config['masked_keys'] ?? self::DEFAULT_MASKED_KEYS));
        $maxValueLength = (int) ($config['max_value_length'] ?? self::DEFAULT_MAX_VALUE_LENGTH);
        $body = self::sanitize($body, $patterns, $maxValueLength);

        $maxLength = (int) ($config['max_length'] ?? self::DEFAULT_MAX_LENGTH);
        if ($maxLength <= 0) {
            return $body;
        }

        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return $json !== false && strlen($json) > $maxLength
            ? mb_strcut($json, 0, $maxLength) . '…'
            : $body;
    }

    private static function sanitize(array $data, array $patterns, int $maxValueLength): array
    {
        $clean = [];

        foreach ($data as $key => $value) {
            if (is_string($key)) {
                $key = mb_scrub($key, 'UTF-8');
            }

            if (is_string($key) && self::isMasked($key, $patterns)) {
                $value = self::MASK;
            } elseif (is_array($value)) {
                $value = self::sanitize($value, $patterns, $maxValueLength);
            } elseif (is_string($value)) {
                $value = mb_scrub($value, 'UTF-8');
                if ($maxValueLength > 0 && mb_strlen($value) > $maxValueLength) {
                    $value = Str::limit($value, $maxValueLength);
                }
            }

            $clean[$key] = $value;
        }

        // Key/value-shaped settings payloads, e.g. [{"key": "whatsapp_token", "value": "..."}].
        if (array_key_exists('value', $clean)) {
            foreach (['key', 'name'] as $nameKey) {
                if (is_string($clean[$nameKey] ?? null) && self::isMasked($clean[$nameKey], $patterns)) {
                    $clean['value'] = self::MASK;
                    break;
                }
            }
        }

        return $clean;
    }

    private static function isMasked(string $key, array $patterns): bool
    {
        return Str::is($patterns, strtolower($key));
    }

    private static function describeFiles(array $files): array
    {
        $described = [];

        foreach ($files as $key => $file) {
            if (is_array($file)) {
                $described[$key] = self::describeFiles($file);
            } elseif ($file instanceof UploadedFile) {
                $described[$key] = [
                    'file' => mb_scrub((string) $file->getClientOriginalName(), 'UTF-8'),
                    'size' => $file->getSize(),
                    'mime' => mb_scrub((string) $file->getClientMimeType(), 'UTF-8'),
                ];
            }
        }

        return $described;
    }
}
