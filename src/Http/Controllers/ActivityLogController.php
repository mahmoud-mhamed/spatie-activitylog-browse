<?php

namespace Mhamed\SpatieActivitylogBrowse\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Mhamed\SpatieActivitylogBrowse\Helpers\RelationDiscovery;
use Mhamed\SpatieActivitylogBrowse\Helpers\RequestDataCollector;
use Mhamed\SpatieActivitylogBrowse\Http\Middleware\RequirePassword;
use Mhamed\SpatieActivitylogBrowse\Support\ActivityLogHelpers;
use Mhamed\SpatieActivitylogBrowse\Support\ColumnMigrator;
use Mhamed\SpatieActivitylogBrowse\Support\DeletionLogger;
use Mhamed\SpatieActivitylogBrowse\Support\RetentionPruner;
use Mhamed\SpatieActivitylogBrowse\Support\ValuePresenter;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogController extends Controller
{
    /** Rows changed per call by the cleanup page's batched actions. */
    private const CLEANUP_BATCH = 2000;

    /** Quick date ranges offered next to the From–To filter (resolved against "now" on every request). */
    public const DATE_RANGES = ['15m', '30m', '1h', '6h', '12h', '1d', '7d', '30d', 'today', 'yesterday'];

    /** Badge classes per HTTP method; the row partial reuses them for the Request column. */
    public const METHOD_COLORS = [
        'GET' => 'bg-blue-100 text-blue-800',
        'POST' => 'bg-green-100 text-green-800',
        'PUT' => 'bg-yellow-100 text-yellow-800',
        'PATCH' => 'bg-yellow-100 text-yellow-800',
        'DELETE' => 'bg-red-100 text-red-800',
    ];

    public function showLogin()
    {
        $password = config('activitylog-browse.browse.password');

        if ($password === null || $password === '' || session(RequirePassword::SESSION_KEY)) {
            return redirect()->route('activitylog-browse.index');
        }

        return view('activitylog-browse::login');
    }

    public function authenticate(Request $request)
    {
        $request->validate(['password' => 'required|string']);

        $maxAttempts = 5;
        $decaySeconds = 60;
        $throttleKey = 'activitylog-browse-login|' . $request->ip();
        $limiter = app(\Illuminate\Cache\RateLimiter::class);

        if ($limiter->tooManyAttempts($throttleKey, $maxAttempts)) {
            $seconds = $limiter->availableIn($throttleKey);

            return back()->withErrors([
                'password' => __('activitylog-browse::messages.login_too_many', ['seconds' => $seconds]),
            ]);
        }

        $password = config('activitylog-browse.browse.password');

        if ($password !== null && hash_equals((string) $password, (string) $request->input('password'))) {
            $limiter->clear($throttleKey);
            $request->session()->regenerate();
            $request->session()->put(RequirePassword::SESSION_KEY, true);

            return redirect()->intended(route('activitylog-browse.index'));
        }

        $limiter->hit($throttleKey, $decaySeconds);
        $remaining = max(0, $maxAttempts - $limiter->attempts($throttleKey));

        return back()->withErrors([
            'password' => __('activitylog-browse::messages.login_invalid', ['remaining' => $remaining]),
        ]);
    }

    public function logout(Request $request)
    {
        $request->session()->forget(RequirePassword::SESSION_KEY);

        return redirect()->route('activitylog-browse.login');
    }

    public function index(Request $request)
    {
        $this->authorize();

        // Filters are plain strings; an array value (e.g. ?method[]=x) would 500 on the casts and in the views.
        foreach (['search', 'log_name', 'event', 'subject_type', 'subject_id', 'causer_type', 'causer_id', 'date_from', 'date_to',
                  'changed_attribute', 'body_search', 'method', 'source', 'route_name', 'url', 'ip', 'request_id', 'range'] as $param) {
            if (is_array($request->input($param))) {
                $request->offsetUnset($param);
            }
        }

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $query = $activityModel::with(['causer'])->orderByDesc('id');
        $hasRequestId = ActivityLogHelpers::hasRequestIdColumn();

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->input('subject_type'));
        }

        if ($request->filled('subject_id')) {
            $ids = array_filter(
                array_map(fn($v) => trim($v), explode(',', $request->input('subject_id'))),
                fn($v) => $v !== ''
            );
            if (count($ids) === 1) {
                $query->where('subject_id', $ids[0]);
            } elseif (count($ids) > 1) {
                $query->whereIn('subject_id', $ids);
            }
        }

        if ($request->filled('causer_type')) {
            $query->where('causer_type', $request->input('causer_type'));
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->input('causer_id'));
        }

        // A quick range (?range=1h) becomes date_from/date_to relative to now, so a bookmarked
        // "last hour" stays current. Merged into the request so the inputs show the resolved times.
        $range = (string) $request->input('range');
        if (in_array($range, self::DATE_RANGES, true)) {
            [$from, $to] = match ($range) {
                '15m' => [now()->subMinutes(15), null],
                '30m' => [now()->subMinutes(30), null],
                '1h' => [now()->subHour(), null],
                '6h' => [now()->subHours(6), null],
                '12h' => [now()->subHours(12), null],
                '1d' => [now()->subDay(), null],
                '7d' => [now()->subDays(7), null],
                '30d' => [now()->subDays(30), null],
                'today' => [now()->startOfDay(), null],
                'yesterday' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            };
            $request->merge([
                'date_from' => $from->format('Y-m-d\TH:i:s'),
                'date_to' => $to?->format('Y-m-d\TH:i:s'),
            ]);
        }

        // A value with a time (e.g. from clicking a row's timestamp) filters to the second;
        // a plain date covers the whole day.
        foreach (['date_from' => '>=', 'date_to' => '<='] as $param => $operator) {
            $value = (string) $request->input($param);
            if ($value === '' || ! strtotime($value)) {
                continue;
            }
            if (str_contains($value, ':')) {
                $query->where('created_at', $operator, Carbon::parse($value)->format('Y-m-d H:i:s'));
            } else {
                $query->whereDate('created_at', $operator, $value);
            }
        }

        if ($request->filled('changed_attribute')) {
            $attrs = array_filter(array_map(
                fn($v) => preg_replace('/[^a-zA-Z0-9_.\-]/', '', trim($v)),
                explode(',', $request->input('changed_attribute'))
            ));
            if (count($attrs) === 1) {
                $attr = $attrs[0];
                $query->where(function ($q) use ($attr) {
                    $q->whereRaw("JSON_CONTAINS_PATH(properties, 'one', ?)", ['$.attributes.' . $attr])
                        ->orWhereRaw("JSON_CONTAINS_PATH(properties, 'one', ?)", ['$.old.' . $attr]);
                });
            } elseif (count($attrs) > 1) {
                $query->where(function ($q) use ($attrs) {
                    foreach ($attrs as $attr) {
                        $q->where(function ($sub) use ($attr) {
                            $sub->whereRaw("JSON_CONTAINS_PATH(properties, 'one', ?)", ['$.attributes.' . $attr])
                                ->orWhereRaw("JSON_CONTAINS_PATH(properties, 'one', ?)", ['$.old.' . $attr]);
                        });
                    }
                });
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where('description', 'like', "%{$search}%");
        }

        // Request filters read the enrichment JSON. JSON_UNQUOTE returns a binary-collated
        // string, so text matches lower both sides to stay case-insensitive.
        $jsonText = fn (string $path) => "JSON_UNQUOTE(JSON_EXTRACT(properties, '{$path}'))";
        $likeTerm = fn (string $input) => addcslashes(mb_strtolower((string) $request->input($input)), '%_\\');

        if ($request->filled('body_search')) {
            $bodyMatch = 'LOWER(' . $jsonText('$.request_data.body') . ') LIKE ?';
            $term = '%' . $likeTerm('body_search') . '%';

            if ($hasRequestId) {
                // The body is stored on a request's first activity only: include its siblings.
                $query->where(fn ($q) => $q->whereRaw($bodyMatch, [$term])
                    ->orWhereIn('request_id', $activityModel::query()->select('request_id')->whereNotNull('request_id')->whereRaw($bodyMatch, [$term])));
            } else {
                $query->whereRaw($bodyMatch, [$term]);
            }
        }

        // Queue/schedule/console rows carry a placeholder request (GET, APP_URL, 127.0.0.1),
        // so request filters only consider web rows (or rows without execution context).
        if ($request->filled('method') || $request->filled('route_name') || $request->filled('url') || $request->filled('ip')) {
            $query->where(fn ($q) => $q->whereRaw($jsonText('$.execution_context.source') . " = 'web'")
                ->orWhereRaw($jsonText('$.execution_context.source') . ' IS NULL'));
        }

        if ($request->filled('method')) {
            $query->whereRaw($jsonText('$.request_data.method') . ' = ?', [strtoupper((string) $request->input('method'))]);
        }

        if ($request->filled('source')) {
            $query->whereRaw($jsonText('$.execution_context.source') . ' = ?', [(string) $request->input('source')]);
        }

        if ($request->filled('route_name')) {
            $query->whereRaw('LOWER(' . $jsonText('$.request_data.route_name') . ') LIKE ?', ['%' . $likeTerm('route_name') . '%']);
        }

        if ($request->filled('url')) {
            $query->whereRaw('LOWER(' . $jsonText('$.request_data.url') . ') LIKE ?', ['%' . $likeTerm('url') . '%']);
        }

        if ($request->filled('ip')) {
            // Prefix match, so "192.168." finds a whole subnet.
            $query->whereRaw($jsonText('$.device_data.ip') . ' LIKE ?', [$likeTerm('ip') . '%']);
        }

        if ($hasRequestId && $request->filled('request_id')) {
            $query->where('request_id', (string) $request->input('request_id'));
        }

        $activities = $query->paginate(
            config('activitylog-browse.browse.per_page', 25)
        )->withQueryString();

        // How many activities each request on this page produced (for the "×N" badge).
        $requestCounts = [];
        if ($hasRequestId) {
            $requestIds = $activities->getCollection()->pluck('request_id')->filter()->unique()->values();
            if ($requestIds->isNotEmpty()) {
                $requestCounts = $activityModel::query()
                    ->whereIn('request_id', $requestIds)
                    ->groupBy('request_id')
                    ->selectRaw('request_id, COUNT(*) as aggregate')
                    ->pluck('aggregate', 'request_id')
                    ->all();
            }
        }

        // Grouping is on but the column is missing (migration not run or it timed out).
        $requestIdAlert = ! $hasRequestId
            && config('activitylog-browse.request_data.enabled')
            && (config('activitylog-browse.request_data.fields.request_id') ?? true)
                ? ['failure' => ColumnMigrator::lastRequestIdFailure()]
                : null;

        return view('activitylog-browse::index', compact('activities', 'requestCounts', 'requestIdAlert', 'hasRequestId'));
    }

    /**
     * Everything stored on an activity except the diff, for the request-details dialog.
     * The body is read from the request's first activity when this one doesn't carry it.
     */
    public function requestDetails($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::findOrFail($id);
        $props = $activity->properties?->toArray() ?? [];

        $requestId = ActivityLogHelpers::hasRequestIdColumn() ? $activity->request_id : null;
        $bodyFrom = null;

        if ($requestId && ! isset($props['request_data']['body'])) {
            $sibling = $this->requestBodySibling($activityModel, $activity);
            if ($sibling) {
                $props['request_data']['body'] = $sibling->properties['request_data']['body'];
                $bodyFrom = $sibling->getKey();
            }
        }

        return response()->json([
            'id' => $activity->getKey(),
            'request_id' => $requestId,
            'request_count' => $requestId ? $activityModel::query()->where('request_id', $requestId)->count() : 0,
            'request_logs_url' => $requestId ? route('activitylog-browse.index', ['request_id' => $requestId]) : null,
            'sections' => $this->requestDetailSections($props, $bodyFrom),
        ]);
    }

    public function statistics()
    {
        $this->authorize();

        return view('activitylog-browse::statistics');
    }

    public function stats(Request $request)
    {
        $this->authorize();

        $section = $request->input('section', 'overview');
        $dateFrom = $request->filled('date_from') && strtotime($request->input('date_from')) ? $request->input('date_from') : null;
        $dateTo = $request->filled('date_to') && strtotime($request->input('date_to')) ? $request->input('date_to') : null;

        $cacheKey = $this->cachePrefix() . ":stats:{$section}" . ($dateFrom ? ':f' . $dateFrom : '') . ($dateTo ? ':t' . $dateTo : '');
        $cacheTtl = ($dateFrom || $dateTo) ? 60 : 120;

        return response()->json(Cache::remember($cacheKey, $cacheTtl, function () use ($section, $dateFrom, $dateTo) {
            $activityModel = ActivitylogServiceProvider::determineActivityModel();

            $scoped = function () use ($activityModel, $dateFrom, $dateTo) {
                $q = $activityModel::query();
                if ($dateFrom) {
                    $q->where('created_at', '>=', $dateFrom . ' 00:00:00');
                }
                if ($dateTo) {
                    $q->where('created_at', '<=', $dateTo . ' 23:59:59');
                }
                return $q;
            };

            return match ($section) {
                'overview' => $this->statsOverview($scoped, $dateFrom, $dateTo),
                'events' => $this->statsEvents($scoped),
                'log_names' => $this->statsLogNames($scoped),
                'models' => $this->statsModels($scoped),
                'causers' => $this->statsCausers($scoped),
                'daily' => $this->statsDaily($scoped, $dateFrom || $dateTo),
                'hourly' => $this->statsHourly($scoped),
                'weekday' => $this->statsWeekday($scoped),
                'system_user' => $this->statsSystemUser($scoped),
                'attributes' => $this->statsAttributes($scoped),
                'monthly' => $this->statsMonthly($scoped),
                'peak_day' => $this->statsPeakDay($scoped),
                default => [],
            };
        }));
    }

    private function statsOverview(\Closure $scoped, ?string $dateFrom, ?string $dateTo): array
    {
        $info = $this->getTableInfo($scoped);

        $avgPerDay = $info['total_rows'] > 0 && $info['oldest_entry'] && $info['newest_entry']
            ? round($info['total_rows'] / max(1, $info['newest_entry']->diffInDays($info['oldest_entry']) ?: 1), 1)
            : 0;

        return [
            'total_rows' => $info['total_rows'],
            'table_size' => $info['table_size'],
            'oldest_entry' => $info['oldest_entry']?->toIso8601String(),
            'newest_entry' => $info['newest_entry']?->toIso8601String(),
            'avg_per_day' => $avgPerDay,
        ];
    }

    private function statsEvents(\Closure $scoped): array
    {
        return [
            'event_counts' => $scoped()->select('event', DB::raw('COUNT(*) as count'))
                ->whereNotNull('event')
                ->groupBy('event')
                ->orderByDesc('count')
                ->get()
                ->map(fn($row) => ['event' => $row->event, 'count' => $row->count]),
        ];
    }

    private function statsLogNames(\Closure $scoped): array
    {
        return [
            'log_name_counts' => $scoped()->select('log_name', DB::raw('COUNT(*) as count'))
                ->whereNotNull('log_name')
                ->groupBy('log_name')
                ->orderByDesc('count')
                ->get()
                ->map(fn($row) => ['log_name' => $row->log_name, 'count' => $row->count]),
        ];
    }

    private function statsModels(\Closure $scoped): array
    {
        return [
            'subject_type_counts' => $scoped()->select('subject_type', DB::raw('COUNT(*) as count'))
                ->whereNotNull('subject_type')
                ->groupBy('subject_type')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn($row) => ['subject_type' => class_basename($row->subject_type), 'count' => $row->count]),
        ];
    }

    private function statsCausers(\Closure $scoped): array
    {
        $topCausersRaw = $scoped()->select('causer_type', 'causer_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('causer_type')
            ->whereNotNull('causer_id')
            ->groupBy('causer_type', 'causer_id')
            ->orderByDesc('count')
            ->limit(10)
            ->get();

        $causerNames = [];
        foreach ($topCausersRaw->groupBy('causer_type') as $type => $rows) {
            try {
                $causerClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type) ?? $type;
                if (class_exists($causerClass)) {
                    $ids = $rows->pluck('causer_id')->all();
                    $models = $causerClass::whereIn((new $causerClass)->getKeyName(), $ids)->get()->keyBy(fn($m) => $m->getKey());
                    foreach ($models as $id => $model) {
                        $name = $model->name ?? $model->email ?? $model->title ?? null;
                        if ($name) {
                            $causerNames[$type . ':' . $id] = $name . ' (' . class_basename($type) . ')';
                        }
                    }
                }
            } catch (\Throwable) {
            }
        }

        return [
            'top_causers' => $topCausersRaw->map(function ($row) use ($causerNames) {
                $key = $row->causer_type . ':' . $row->causer_id;
                return ['causer' => $causerNames[$key] ?? class_basename($row->causer_type) . ' #' . $row->causer_id, 'count' => $row->count];
            }),
        ];
    }

    private function statsDaily(\Closure $scoped, bool $hasDateFilter): array
    {
        $q = $scoped()->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'));
        if (!$hasDateFilter) {
            $q->where('created_at', '>=', now()->subDays(30));
        }

        return [
            'daily_activity' => $q->groupBy(DB::raw('DATE(created_at)'))->orderBy('date')->limit(90)->get()
                ->map(fn($row) => ['date' => $row->date, 'count' => $row->count]),
        ];
    }

    private function statsHourly(\Closure $scoped): array
    {
        return [
            'hourly_activity' => $scoped()->select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw('HOUR(created_at)'))
                ->orderBy('hour')
                ->get()
                ->map(fn($row) => ['hour' => (int)$row->hour, 'count' => $row->count]),
        ];
    }

    private function statsWeekday(\Closure $scoped): array
    {
        return [
            'weekday_activity' => $scoped()->select(DB::raw('DAYOFWEEK(created_at) as dow'), DB::raw('COUNT(*) as count'))
                ->groupBy(DB::raw('DAYOFWEEK(created_at)'))
                ->orderBy('dow')
                ->get()
                ->map(fn($row) => ['dow' => (int)$row->dow, 'count' => $row->count]),
        ];
    }

    private function statsSystemUser(\Closure $scoped): array
    {
        $total = $scoped()->count();
        $userActions = $scoped()->whereNotNull('causer_id')->count();

        return ['user_actions' => $userActions, 'system_actions' => $total - $userActions];
    }

    private function statsAttributes(\Closure $scoped): array
    {
        $topAttributes = collect();
        try {
            $recentProps = $scoped()->whereNotNull('properties')
                ->where('event', 'updated')
                ->orderByDesc('id')
                ->limit(1000)
                ->pluck('properties');

            $attrCounts = [];
            foreach ($recentProps as $props) {
                $p = $props instanceof \Illuminate\Support\Collection ? $props->toArray() : (array)$props;
                foreach (array_keys($p['attributes'] ?? []) as $k) {
                    $attrCounts[$k] = ($attrCounts[$k] ?? 0) + 1;
                }
                foreach (array_keys($p['old'] ?? []) as $k) {
                    $attrCounts[$k] = ($attrCounts[$k] ?? 0) + 1;
                }
            }
            arsort($attrCounts);
            $topAttributes = collect(array_slice($attrCounts, 0, 30, true))
                ->map(fn($count, $attr) => ['attribute' => $attr, 'count' => $count])
                ->values();
        } catch (\Throwable) {
        }

        return ['top_attributes' => $topAttributes];
    }

    private function statsMonthly(\Closure $scoped): array
    {
        $monthlyActivity = $scoped()->select(
            DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
            DB::raw('COUNT(*) as count')
        )
            ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
            ->orderBy('month')
            ->get()
            ->map(fn($row) => ['month' => $row->month, 'count' => $row->count]);

        $peakMonth = $monthlyActivity->sortByDesc('count')->first();

        return [
            'monthly_activity' => $monthlyActivity,
            'peak_month' => $peakMonth['month'] ?? null,
            'peak_month_count' => $peakMonth['count'] ?? null,
        ];
    }

    private function statsPeakDay(\Closure $scoped): array
    {
        $peakDay = $scoped()->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderByDesc('count')
            ->limit(1)
            ->first();

        return [
            'peak_day_date' => $peakDay?->date,
            'peak_day_count' => $peakDay?->count,
        ];
    }

    public function filterOptions(Request $request)
    {
        $this->authorize();

        $column = $request->input('column');
        $allowed = ['log_name', 'event', 'subject_type', 'causer_type'];

        if (!in_array($column, $allowed)) {
            return response()->json([]);
        }

        $activityModel = ActivitylogServiceProvider::determineActivityModel();

        $values = Cache::remember($this->cachePrefix() . ":{$column}", 60, function () use ($activityModel, $column) {
            return $activityModel::distinct()
                ->whereNotNull($column)
                ->pluck($column)
                ->sort()
                ->values();
        });

        $useBasename = in_array($column, ['subject_type', 'causer_type']);

        $options = $values->map(fn($v) => [
            'value' => $v,
            'label' => $useBasename ? class_basename($v) : $v,
        ])->values();

        return response()->json($options);
    }

    public function attributes(Request $request)
    {
        $this->authorize();

        $subjectType = $request->input('subject_type');

        if (!$subjectType) {
            return response()->json([]);
        }

        $activityModel = ActivitylogServiceProvider::determineActivityModel();

        $activities = $activityModel::where('subject_type', $subjectType)
            ->whereNotNull('properties')
            ->orderByDesc('id')
            ->limit(100)
            ->pluck('properties');

        $keys = collect();

        foreach ($activities as $properties) {
            $props = $properties instanceof \Illuminate\Support\Collection ? $properties->toArray() : (array)$properties;

            if (isset($props['attributes']) && is_array($props['attributes'])) {
                $keys = $keys->merge(array_keys($props['attributes']));
            }
            if (isset($props['old']) && is_array($props['old'])) {
                $keys = $keys->merge(array_keys($props['old']));
            }
        }

        return response()->json($keys->unique()->sort()->values()
            ->map(fn ($key) => ['value' => $key, 'label' => ValuePresenter::attributeLabel((string) $key)]));
    }

    public function modelInfo(Request $request)
    {
        $this->authorize();

        $subjectType = $request->input('subject_type');

        if (!$subjectType) {
            return response()->json([]);
        }

        $activityModel = ActivitylogServiceProvider::determineActivityModel();

        // Gather changed attributes from recent logs
        $activities = $activityModel::where('subject_type', $subjectType)
            ->whereNotNull('properties')
            ->orderByDesc('id')
            ->limit(200)
            ->pluck('properties');

        $keys = collect();
        foreach ($activities as $properties) {
            $props = $properties instanceof \Illuminate\Support\Collection ? $properties->toArray() : (array)$properties;
            if (isset($props['attributes']) && is_array($props['attributes'])) {
                $keys = $keys->merge(array_keys($props['attributes']));
            }
            if (isset($props['old']) && is_array($props['old'])) {
                $keys = $keys->merge(array_keys($props['old']));
            }
        }

        $uniqueKeys = $keys->unique()->sort()->values();

        // Try to resolve translations from validation.attributes lang file
        $locale = app()->getLocale();
        $translatedAttributes = [];
        foreach ($uniqueKeys as $key) {
            $langKey = "validation.attributes.{$key}";
            $translated = __($langKey);
            $translatedAttributes[] = [
                'key' => $key,
                'label' => $translated !== $langKey ? $translated : \Illuminate\Support\Str::headline($key),
                'has_translation' => $translated !== $langKey,
            ];
        }

        // Stats for this model
        $totalLogs = $activityModel::where('subject_type', $subjectType)->count();

        $eventCounts = $activityModel::where('subject_type', $subjectType)
            ->select('event', DB::raw('COUNT(*) as count'))
            ->whereNotNull('event')
            ->groupBy('event')
            ->orderByDesc('count')
            ->get()
            ->pluck('count', 'event')
            ->toArray();

        $uniqueSubjects = $activityModel::where('subject_type', $subjectType)
            ->whereNotNull('subject_id')
            ->distinct('subject_id')
            ->count('subject_id');

        // Table name + size for the model (try to resolve)
        $tableName = null;
        $tableSize = null;
        $modelClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($subjectType) ?? $subjectType;
        if (class_exists($modelClass)) {
            try {
                $instance = new $modelClass;
                $tableName = $instance->getTable();
                $connection = $instance->getConnectionName() ?? config('database.default');
                $conn = DB::connection($connection);
                $conn->statement("ANALYZE TABLE `{$tableName}`");
                $dbName = $conn->getDatabaseName();
                $result = $conn
                    ->selectOne("SELECT (data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ? AND table_name = ?", [$dbName, $tableName]);
                $tableSize = $result?->size ? (int)$result->size : null;
            } catch (\Throwable) {
            }
        }

        return response()->json([
            'attributes' => $translatedAttributes,
            'stats' => [
                'total_logs' => $totalLogs,
                'unique_subjects' => $uniqueSubjects,
                'events' => $eventCounts,
                'table_name' => $tableName,
                'table_size' => $tableSize,
                'model_basename' => class_basename($subjectType),
            ],
        ]);
    }

    public function causers(Request $request)
    {
        $this->authorize();

        $causerType = $request->input('causer_type');

        if (!$causerType) {
            return response()->json([]);
        }

        $activityModel = ActivitylogServiceProvider::determineActivityModel();

        $causerIds = $activityModel::where('causer_type', $causerType)
            ->whereNotNull('causer_id')
            ->distinct()
            ->pluck('causer_id');

        if ($causerIds->isEmpty()) {
            return response()->json([]);
        }

        $causerClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($causerType) ?? $causerType;

        if (class_exists($causerClass)) {
            try {
                $instance = new $causerClass;
                $models = $causerClass::whereIn($instance->getKeyName(), $causerIds)->get();

                return response()->json($models->map(function ($model) {
                    $name = $model->name ?? $model->email ?? $model->title ?? null;
                    $label = $name
                        ? $name . ' (#' . $model->getKey() . ')'
                        : '#' . $model->getKey();

                    return ['value' => (string)$model->getKey(), 'label' => $label];
                })->sortBy('label')->values());
            } catch (\Throwable) {
                // Fall through to ID-only fallback
            }
        }

        return response()->json(
            $causerIds->sort()->map(fn($id) => ['value' => (string)$id, 'label' => '#' . $id])->values()
        );
    }

    public function subjectAttributes($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::findOrFail($id);

        if (!$activity->subject_type || !$activity->subject) {
            return response()->json(['rows' => []]);
        }

        $excluded = config('activitylog-browse.auto_log.excluded_attributes', []);
        $attrs = array_diff_key($activity->subject->getAttributes(), array_flip($excluded));

        return response()->json(['rows' => (new ValuePresenter)->attributeRows($attrs, get_class($activity->subject))]);
    }

    public function causerAttributes($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::findOrFail($id);

        if (!$activity->causer_type || !$activity->causer) {
            return response()->json(['rows' => []]);
        }

        $excluded = config('activitylog-browse.auto_log.excluded_attributes', []);
        $attrs = array_diff_key($activity->causer->getAttributes(), array_flip($excluded));

        return response()->json(['rows' => (new ValuePresenter)->attributeRows($attrs, get_class($activity->causer))]);
    }

    public function show($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::with(['subject', 'causer'])->findOrFail($id);

        $relations = [];
        if ($activity->subject) {
            $relations = RelationDiscovery::getRelations($activity->subject);
        }

        $requestId = ActivityLogHelpers::hasRequestIdColumn() ? $activity->request_id : null;
        $siblingBody = null;
        if ($requestId && ! isset($activity->properties['request_data']['body'])) {
            $sibling = $this->requestBodySibling($activityModel, $activity);
            $siblingBody = $sibling
                ? ['id' => $sibling->getKey(), 'body' => $sibling->properties['request_data']['body']]
                : null;
        }

        $props = $activity->properties?->toArray() ?? [];
        $changeRows = (new ValuePresenter)->changeRows((array) ($props['old'] ?? []), (array) ($props['attributes'] ?? []), $activity->subject_type);

        return view('activitylog-browse::show', compact('activity', 'relations', 'requestId', 'siblingBody', 'changeRows'));
    }

    public function relatedLogs(Request $request, $id, string $relation)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::with('subject')->findOrFail($id);

        if (!$activity->subject) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $subject = $activity->subject;
        $relations = RelationDiscovery::getRelations($subject);

        if (!in_array($relation, $relations)) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $relatedQuery = $subject->$relation();
        $relatedModel = $relatedQuery->getRelated();
        $relatedType = $relatedModel->getMorphClass();
        $relatedIds = $relatedQuery->pluck($relatedModel->getQualifiedKeyName())->all();

        return redirect()->route('activitylog-browse.index', [
            'subject_type' => $relatedType,
            'subject_id' => implode(',', $relatedIds),
            'via_activity' => $id,
            'via_relation' => $relation,
        ]);
    }

    public function about()
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $scoped = fn() => $activityModel::query();
        $info = $this->getTableInfo($scoped);

        $packageVersion = $this->packageVersion();
        $spatieVersion = $this->installedVersion('spatie/laravel-activitylog');

        $config = config('activitylog-browse');

        $features = [
            'auto_log'          => (bool) ($config['auto_log']['enabled'] ?? false),
            'request_data'      => (bool) ($config['request_data']['enabled'] ?? false),
            'device_data'       => (bool) ($config['device_data']['enabled'] ?? false),
            'performance_data'  => (bool) ($config['performance_data']['enabled'] ?? false),
            'app_data'          => (bool) ($config['app_data']['enabled'] ?? false),
            'session_data'      => (bool) ($config['session_data']['enabled'] ?? false),
            'execution_context' => (bool) ($config['execution_context']['enabled'] ?? false),
            'browse_ui'         => (bool) ($config['browse']['enabled'] ?? false),
            'retention'         => (bool) ($config['retention']['enabled'] ?? false),
        ];

        return view('activitylog-browse::about', [
            'packageName'    => 'mahmoud-mhamed/spatie-activitylog-browse',
            'packageVersion' => $packageVersion,
            'spatieVersion'  => $spatieVersion,
            'phpVersion'     => PHP_VERSION,
            'laravelVersion' => app()->version(),
            'environment'    => app()->environment(),
            'connection'     => ActivityLogHelpers::activityConnection(),
            'tableName'      => ActivityLogHelpers::tableName(),
            'totalRows'      => $info['total_rows'],
            'tableSize'      => $info['table_size'],
            'oldestEntry'    => $info['oldest_entry'],
            'newestEntry'    => $info['newest_entry'],
            'features'       => $features,
            'config'         => $config,
        ]);
    }

    protected function packageVersion(): string
    {
        $composer = __DIR__ . '/../../../composer.json';
        if (is_readable($composer)) {
            $json = json_decode((string) file_get_contents($composer), true);
            if (! empty($json['version'])) {
                return (string) $json['version'];
            }
        }

        return $this->installedVersion('mahmoud-mhamed/spatie-activitylog-browse')
            ?? $this->installedVersion('mhamed/spatie-activitylog-browse')
            ?? 'dev';
    }

    protected function installedVersion(string $package): ?string
    {
        try {
            if (class_exists(\Composer\InstalledVersions::class)
                && \Composer\InstalledVersions::isInstalled($package)) {
                return \Composer\InstalledVersions::getPrettyVersion($package);
            }
        } catch (\Throwable) {
        }

        return null;
    }

    public function cleanup()
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();

        $models = $activityModel::distinct()
            ->whereNotNull('subject_type')
            ->pluck('subject_type')
            ->sort()
            ->values()
            ->map(fn($v) => ['value' => $v, 'label' => class_basename($v)]);

        $scoped = fn() => $activityModel::query();
        $info = $this->getTableInfo($scoped);
        $totalRows = $info['total_rows'];
        $tableSize = $info['table_size'];
        $oldestEntry = $info['oldest_entry'];
        $newestEntry = $info['newest_entry'];

        $retention = config('activitylog-browse.retention', []);
        $bodyRetention = (array) config('activitylog-browse.request_data.body.retention', []);
        $requestIdStatus = ColumnMigrator::requestIdStatus();
        $optimizePendingSince = ActivityLogHelpers::optimizePendingSince();
        $autoOptimize = (bool) config('activitylog-browse.retention.optimize_after', true);
        $rebuildRunning = ActivityLogHelpers::rebuildState()['running'];

        return view('activitylog-browse::cleanup', compact(
            'models',
            'totalRows',
            'tableSize',
            'oldestEntry',
            'newestEntry',
            'retention',
            'bodyRetention',
            'requestIdStatus',
            'optimizePendingSince',
            'autoOptimize',
            'rebuildRunning'
        ));
    }

    /** Rows (and bytes) of stored request bodies older than ?days (0 = all), for the cleanup page. */
    public function cleanupBodiesPreview(Request $request, RetentionPruner $pruner)
    {
        $this->authorize();
        $days = max(0, (int) $request->input('days'));

        return response()->json([
            'count' => $pruner->stripRequestBodies($days, PHP_INT_MAX, true),
            'bytes' => $pruner->requestBodyBytes($days),
        ]);
    }

    /** Strip one batch of request bodies; the page calls it until `done` (keeps each request short). */
    public function cleanupBodies(Request $request, RetentionPruner $pruner)
    {
        $this->authorize();
        set_time_limit(60);
        $limit = self::CLEANUP_BATCH;
        $processed = $pruner->stripRequestBodies(max(0, (int) $request->input('days')), $limit);
        if ($processed > 0) {
            ActivityLogHelpers::markOptimizePending();
        }

        return response()->json(['processed' => $processed, 'done' => $processed < $limit]);
    }

    /** Rows of jobs/scheduled tasks/commands still carrying placeholder request/device data. */
    public function cleanupPlaceholderPreview(RetentionPruner $pruner)
    {
        $this->authorize();

        return response()->json(['count' => $pruner->stripPlaceholderData(PHP_INT_MAX, true)]);
    }

    /** Strip one batch of placeholder data; the page calls it until `done`. */
    public function cleanupPlaceholder(RetentionPruner $pruner)
    {
        $this->authorize();
        set_time_limit(60);
        $limit = self::CLEANUP_BATCH;
        $processed = $pruner->stripPlaceholderData($limit);
        if ($processed > 0) {
            ActivityLogHelpers::markOptimizePending();
        }

        return response()->json(['processed' => $processed, 'done' => $processed < $limit]);
    }

    /** Start rebuilding the table (OPTIMIZE TABLE) after this response; the page polls cleanupTableSize(). */
    public function cleanupReclaimSpace()
    {
        $this->authorize();

        if (! ActivityLogHelpers::rebuildState()['running']) {
            ActivityLogHelpers::rebuildInBackground();
        }

        return response()->json(['started' => true]);
    }

    /** Current table size (recomputed with ANALYZE TABLE) and whether a rebuild is running. */
    public function cleanupTableSize()
    {
        $this->authorize();
        $state = ActivityLogHelpers::rebuildState();

        return response()->json([
            'size' => $state['running'] ? null : ActivityLogHelpers::tableSizeBytes(),
            'running' => $state['running'],
            'error' => $state['error'],
            'pending_since' => ActivityLogHelpers::optimizePendingSince(),
        ]);
    }

    /** "Add it now" button for the request_id column (same safe path as the migration). */
    public function cleanupEnsureRequestId()
    {
        $this->authorize();

        if (ColumnMigrator::tryEnsureRequestIdColumn()) {
            return back()->with('success', __('activitylog-browse::messages.request_id_added'));
        }

        return back()->with('error', __('activitylog-browse::messages.request_id_add_failed', [
            'message' => ColumnMigrator::lastRequestIdFailure()['message'] ?? '',
        ]));
    }

    public function deletionHistory(Request $request)
    {
        $this->authorize();

        $page = (int) $request->input('page', 1);
        $perPage = 25;
        $data = DeletionLogger::paginate($page, $perPage);

        return view('activitylog-browse::deletion-history', [
            'entries'     => $data['entries'],
            'total'       => $data['total'],
            'page'        => $data['page'],
            'perPage'     => $data['per_page'],
            'lastPage'    => $data['last_page'],
            'fileSize'    => DeletionLogger::size(),
            'maxSize'     => DeletionLogger::maxSizeBytes(),
            'enabled'     => DeletionLogger::enabled(),
            'maxEntries'  => (int) config('activitylog-browse.deletion_history.max_entries', 500),
            'filePath'    => DeletionLogger::path(),
        ]);
    }

    public function clearDeletionHistory()
    {
        $this->authorize();

        DeletionLogger::clear();

        return redirect()->route('activitylog-browse.deletion-history')
            ->with('success', __('activitylog-browse::messages.deletion_history_cleared'));
    }

    public function cleanupRunRetention(RetentionPruner $pruner)
    {
        $this->authorize();

        if (! config('activitylog-browse.retention.enabled', false)) {
            return redirect()->route('activitylog-browse.cleanup')
                ->with('error', __('activitylog-browse::messages.retention_disabled'));
        }

        $result = $pruner->setTrigger('ui')->prune();

        return redirect()->route('activitylog-browse.cleanup')
            ->with('success', __('activitylog-browse::messages.retention_success', [
                'total'   => $result['total'],
                'by_age'  => $result['by_age'],
                'by_size' => $result['by_size'],
            ]));
    }

    public function cleanupPreview(Request $request)
    {
        $this->authorize();

        $request->validate([
            'days' => 'required|integer|min:0',
            'models' => 'nullable|array',
            'models.*' => 'string',
        ]);

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $days = (int) $request->input('days');
        $query = $days === 0
            ? $activityModel::query()
            : $activityModel::where('created_at', '<', now()->subDays($days));

        if ($request->filled('models')) {
            $query->whereIn('subject_type', $request->input('models'));
        }

        return response()->json(['count' => $query->count()]);
    }

    public function cleanupDelete(Request $request)
    {
        $this->authorize();

        $request->validate([
            'days' => 'required|integer|min:0',
            'models' => 'nullable|array',
            'models.*' => 'string',
        ]);

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $days = (int) $request->input('days');
        $query = $days === 0
            ? $activityModel::query()
            : $activityModel::where('created_at', '<', now()->subDays($days));

        if ($request->filled('models')) {
            $query->whereIn('subject_type', $request->input('models'));
        }

        $start = microtime(true);
        $rowsBefore = $activityModel::query()->count();
        $sizeBefore = ActivityLogHelpers::tableSizeBytes();

        $count = 0;
        do {
            set_time_limit(30);
            $ids = (clone $query)->limit(1000)->pluck('id');
            if ($ids->isEmpty()) {
                break;
            }
            $deleted = $activityModel::whereIn('id', $ids)->delete();
            $count += $deleted;
        } while (true);

        $this->clearStatsCache();

        if ($count > 0) {
            \Mhamed\SpatieActivitylogBrowse\Support\DeletionLogger::record([
                'operation'      => 'manual_cleanup',
                'trigger'        => 'ui',
                'deleted_count'  => $count,
                'breakdown'      => ['by_age' => $count, 'by_size' => 0],
                'duration_ms'    => round((microtime(true) - $start) * 1000, 2),
                'dry_run'        => false,
                'rows_before'    => $rowsBefore,
                'rows_after'     => $activityModel::query()->count(),
                'size_mb_before' => $sizeBefore !== null ? round($sizeBefore / 1048576, 2) : null,
                'size_mb_after'  => self::sizeMb(ActivityLogHelpers::tableSizeBytes()),
                'filters'        => [
                    'days'   => $days,
                    'models' => (array) $request->input('models', []),
                ],
                'context' => [
                    'user_id'   => auth()->id(),
                    'user_name' => optional(auth()->user())->name,
                    'ip'        => $request->ip(),
                ],
            ]);
        }

        return redirect()->route('activitylog-browse.cleanup')
            ->with('success', __('activitylog-browse::messages.cleanup_success_delete', ['count' => $count]));
    }

    protected static function sizeMb(?int $bytes): ?float
    {
        return $bytes === null ? null : round($bytes / 1048576, 2);
    }


    private function getTableInfo(\Closure $scoped): array
    {
        $table = config('activitylog.table_name', 'activity_log');
        $connection = $this->getActivityConnection();

        $totalRows = $scoped()->count();

        $tableSize = null;
        try {
            $conn = DB::connection($connection);
            $conn->statement("ANALYZE TABLE `{$table}`");
            $dbName = $conn->getDatabaseName();
            $result = $conn
                ->selectOne("SELECT (data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ? AND table_name = ?", [$dbName, $table]);
            $tableSize = $result?->size ? (int)$result->size : null;
        } catch (\Throwable) {
        }

        $oldestEntry = $scoped()->orderBy('created_at')->value('created_at');
        $newestEntry = $scoped()->orderByDesc('created_at')->value('created_at');

        // Some projects don't cast `created_at` to a datetime on their Activity
        // model, so it may come back as a string. Normalize to Carbon (or null)
        // so callers can safely call ->format()/->diffForHumans() on it.
        $toCarbon = fn ($value) => filled($value) ? \Illuminate\Support\Carbon::parse($value) : null;
        $oldestEntry = $toCarbon($oldestEntry);
        $newestEntry = $toCarbon($newestEntry);

        return [
            'total_rows' => $totalRows,
            'table_size' => $tableSize,
            'oldest_entry' => $oldestEntry,
            'newest_entry' => $newestEntry,
        ];
    }

    private function clearStatsCache(): void
    {
        ActivityLogHelpers::clearStatsCache();
    }

    public function switchLang(string $locale)
    {
        $availableLocales = config('activitylog-browse.browse.available_locales', ['en', 'ar']);

        if (!in_array($locale, $availableLocales)) {
            abort(Response::HTTP_BAD_REQUEST);
        }

        session(['activitylog-browse-locale' => $locale]);

        return redirect()->back();
    }

    private function cachePrefix(): string
    {
        return ActivityLogHelpers::cachePrefix();
    }

    private function getActivityConnection(): string
    {
        return ActivityLogHelpers::activityConnection();
    }

    /** Old/new values of every changed attribute, for the changes dialog. */
    public function changes($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::findOrFail($id);
        $props = $activity->properties?->toArray() ?? [];
        $old = (array) ($props['old'] ?? []);
        $new = (array) ($props['attributes'] ?? []);

        return response()->json([
            'has_old' => ! empty($old),
            'has_new' => ! empty($new),
            'rows' => (new ValuePresenter)->changeRows($old, $new, $activity->subject_type),
        ]);
    }

    /** Every activity of one subject, newest first, with its changes inline. */
    public function timeline(Request $request)
    {
        $this->authorize();

        $subjectType = (string) $request->input('subject_type');
        $subjectId = (string) $request->input('subject_id');
        abort_if($subjectType === '' || $subjectId === '' || is_array($request->input('subject_id')), 404);

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activities = $activityModel::with('causer')
            ->where('subject_type', $subjectType)
            ->where('subject_id', $subjectId)
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $presenter = new ValuePresenter;
        $changeRows = $activities->getCollection()->mapWithKeys(function ($activity) use ($presenter, $subjectType) {
            $props = $activity->properties?->toArray() ?? [];

            return [$activity->getKey() => $presenter->changeRows((array) ($props['old'] ?? []), (array) ($props['attributes'] ?? []), $subjectType)];
        });

        $subject = $this->findSubject($subjectType, $subjectId);
        $canRestore = $subject !== null && $this->restoreAllowed();
        $latestId = $activityModel::where('subject_type', $subjectType)->where('subject_id', $subjectId)->max('id');

        return view('activitylog-browse::timeline', compact('activities', 'changeRows', 'subjectType', 'subjectId', 'subject', 'canRestore', 'latestId'));
    }

    /** What restoring the subject to the version after this activity would change. */
    public function restorePreview($id)
    {
        $this->authorize();
        $this->authorizeRestore();

        [$activity, $subject] = $this->restoreTarget($id);
        $values = $this->restoreValues($activity, $subject);
        $presenter = new ValuePresenter;
        $current = $subject->getAttributes();

        return response()->json([
            'rows' => array_map(fn ($key) => array_filter([
                'key' => $key,
                'label' => ValuePresenter::attributeLabel($key),
                'current' => ValuePresenter::text($current[$key] ?? null),
                'current_display' => $presenter->display(get_class($subject), $key, $current[$key] ?? null),
                'restored' => ValuePresenter::text($values[$key]),
                'restored_display' => $presenter->display(get_class($subject), $key, $values[$key]),
            ], fn ($value) => $value !== null), array_keys($values)),
        ]);
    }

    /**
     * Restore the subject to the version right after this activity. Values are
     * written raw (as the log stored them) and saved normally, so model events run
     * and the restore itself is logged like any other update.
     */
    public function restore($id)
    {
        $this->authorize();
        $this->authorizeRestore();

        [$activity, $subject] = $this->restoreTarget($id);
        $values = $this->restoreValues($activity, $subject);

        if ($values === []) {
            return back()->with('activitylog_browse_success', __('activitylog-browse::messages.restore_nothing'));
        }

        try {
            $subject->getConnection()->transaction(function () use ($subject, $values) {
                $subject->setRawAttributes(array_merge($subject->getAttributes(), $values));
                $subject->save();
            });
        } catch (\Throwable $e) {
            report($e);

            return back()->with('activitylog_browse_error', __('activitylog-browse::messages.restore_failed', ['message' => Str::limit($e->getMessage(), 300)]));
        }

        return back()->with('activitylog_browse_success', __('activitylog-browse::messages.restored_success', [
            'model' => class_basename($subject),
            'id' => $subject->getKey(),
            'activity' => $activity->getKey(),
        ]));
    }

    /** Restore is opt-in (it writes to app models) and can be limited by a gate. */
    protected function restoreAllowed(): bool
    {
        if (! config('activitylog-browse.browse.restore.enabled', false)) {
            return false;
        }

        $gate = config('activitylog-browse.browse.restore.gate');

        return ! $gate || ! Gate::has($gate) || Gate::allows($gate);
    }

    protected function authorizeRestore(): void
    {
        abort_unless($this->restoreAllowed(), 403);
    }

    /** @return array{0: \Illuminate\Database\Eloquent\Model, 1: \Illuminate\Database\Eloquent\Model} activity, live subject */
    protected function restoreTarget($id): array
    {
        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::findOrFail($id);
        abort_if($activity->event === 'deleted', 422);

        $subject = $this->findSubject((string) $activity->subject_type, (string) $activity->subject_id);
        abort_if($subject === null, 404);

        return [$activity, $subject];
    }

    protected function findSubject(string $type, string $id): ?\Illuminate\Database\Eloquent\Model
    {
        // subject_type may be a morph-map alias rather than a class name.
        $type = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($type) ?? $type;

        if (! class_exists($type) || ! is_subclass_of($type, \Illuminate\Database\Eloquent\Model::class)) {
            return null;
        }

        try {
            return $type::query()->find($id);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Raw values that put the subject back to its state right after $activity:
     * for each attribute changed later, the "old" value of its first later change.
     * Only real columns that differ from the current row; keys, timestamps and
     * excluded attributes are never touched.
     *
     * @return array<string, mixed>
     */
    protected function restoreValues($activity, \Illuminate\Database\Eloquent\Model $subject): array
    {
        $later = $activity->newQuery()
            ->where('subject_type', $activity->subject_type)
            ->where('subject_id', $activity->subject_id)
            ->where('id', '>', $activity->getKey())
            ->orderBy('id')
            ->get(['id', 'properties']);

        $values = [];
        foreach ($later as $item) {
            foreach ((array) ($item->properties['old'] ?? []) as $key => $value) {
                $values[$key] ??= ['value' => $value];
            }
        }

        $protected = array_merge(
            (array) config('activitylog-browse.auto_log.excluded_attributes', []),
            [$subject->getKeyName(), $subject->getCreatedAtColumn(), $subject->getUpdatedAtColumn(), 'deleted_at']
        );
        $current = $subject->getAttributes();

        $restore = [];
        foreach ($values as $key => ['value' => $value]) {
            if (! is_string($key) || str_contains($key, '.') || ! array_key_exists($key, $current) || in_array($key, $protected, true)) {
                continue;
            }

            $raw = $this->rawAttributeValue($subject, $key, $value);
            if (! $this->sameStoredValue($subject, $key, $raw, $current[$key])) {
                $restore[$key] = $raw;
            }
        }

        return $restore;
    }

    /** Logged value in the column's storage form (LogsActivity may log casted arrays/dates). */
    protected function rawAttributeValue(\Illuminate\Database\Eloquent\Model $subject, string $key, mixed $value): mixed
    {
        return match (true) {
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            is_bool($value) => (int) $value,
            is_string($value) && $value !== '' && (in_array($key, $subject->getDates(), true)
                || $subject->hasCast($key, ['date', 'datetime', 'custom_datetime', 'immutable_date', 'immutable_datetime', 'immutable_custom_datetime'])) => $subject->fromDateTime($value),
            default => $value,
        };
    }

    protected function sameStoredValue(\Illuminate\Database\Eloquent\Model $subject, string $key, mixed $a, mixed $b): bool
    {
        if ($a === null || $b === null) {
            return $a === $b;
        }

        if ((string) $a === (string) $b) {
            return true;
        }

        // '100.00' vs '100' is the same amount, but '0501234567' vs '501234567' is not the same phone.
        if (is_numeric($a) && is_numeric($b) && $subject->hasCast($key, ['int', 'integer', 'float', 'double', 'real', 'decimal'])) {
            return (float) $a === (float) $b;
        }

        $decodedA = is_string($a) ? json_decode($a, true) : null;
        $decodedB = is_string($b) ? json_decode($b, true) : null;
        if (is_array($decodedA) && is_array($decodedB)) {
            return $decodedA == $decodedB;
        }

        return (string) $a === (string) $b;
    }

    /** The activity of the same request that carries the (deduplicated) body. */
    protected function requestBodySibling(string $activityModel, $activity)
    {
        $query = $activityModel::query()
            ->where('request_id', $activity->request_id)
            ->whereKeyNot($activity->getKey());

        $hasBody = match ($query->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => "JSON_CONTAINS_PATH(properties, 'one', '$.request_data.body')",
            'sqlite' => "json_extract(properties, '$.request_data.body') IS NOT NULL",
            'pgsql' => "(properties::jsonb #> '{request_data,body}') IS NOT NULL",
            default => null,
        };

        // Few rows share a request_id, so other drivers can afford checking them in PHP.
        return $hasBody
            ? $query->whereRaw($hasBody)->orderBy('id')->first()
            : $query->orderBy('id')->get()->first(fn ($row) => isset($row->properties['request_data']['body']));
    }

    /**
     * Group stored properties (except old/attributes) into dialog sections of
     * display-ready rows: [{title, rows: [{key, label, kind, value, class?}]}].
     * Known sections go first in a fixed order: MySQL JSON columns don't keep key order.
     */
    protected function requestDetailSections(array $props, ?int $bodyFrom): array
    {
        $knownSections = ['request_data', 'device_data', 'session_data', 'performance_data', 'app_data', 'execution_context'];
        $sections = [];
        $otherData = [];

        foreach (array_replace(array_intersect_key(array_flip($knownSections), $props), $props) as $group => $values) {
            if ($group === 'old' || $group === 'attributes') {
                continue;
            }
            if (! is_array($values)) {
                $otherData[$group] = $values;
                continue;
            }

            $body = $group === 'request_data' && array_key_exists('body', $values) ? $values['body'] : null;
            unset($values['body']);

            $sections[] = [
                'title' => in_array($group, $knownSections, true) ? __("activitylog-browse::messages.{$group}") : Str::headline((string) $group),
                'rows' => array_map(fn ($key) => $this->requestDetailRow($group, $key, $values[$key]), array_keys($values)),
            ];

            // The body gets its own section, flattened to dot-notation keys so it can be searched row by row.
            if ($body !== null) {
                $flatBody = is_array($body) ? Arr::dot($body) : null;
                $sections[] = [
                    'title' => __('activitylog-browse::messages.request_body'),
                    'note' => $bodyFrom ? __('activitylog-browse::messages.body_from_activity', ['id' => $bodyFrom]) : null,
                    'truncated' => is_string($body),
                    'copy' => is_string($body) ? $body : json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'rows' => $flatBody === null
                        ? [['key' => 'body', 'label' => __('activitylog-browse::messages.request_body'), 'kind' => 'pre', 'value' => (string) $body]]
                        : array_map(fn ($key) => $this->requestDetailRow('body', $key, $flatBody[$key]), array_keys($flatBody)),
                ];
            }
        }

        if ($otherData) {
            $sections[] = [
                'title' => __('activitylog-browse::messages.other_data'),
                'rows' => array_map(fn ($key) => $this->requestDetailRow('other_data', $key, $otherData[$key]), array_keys($otherData)),
            ];
        }

        return $sections;
    }

    protected function requestDetailRow(string $group, $key, $value): array
    {
        $labelKey = "activitylog-browse::messages.request_{$key}";
        $row = [
            'key' => (string) $key,
            'label' => match (true) {
                $group === 'body' => (string) $key,
                $group === 'request_data' && Lang::has($labelKey) => __($labelKey),
                default => Str::headline((string) $key),
            },
            'kind' => 'text',
            'value' => null,
        ];

        if ($value === null || $value === '' || $value === []) {
            $row['kind'] = 'null';
        } elseif ($group === 'request_data' && $key === 'method' && is_string($value)) {
            $row['kind'] = 'badge';
            $row['value'] = $value;
            $row['class'] = self::METHOD_COLORS[strtoupper($value)] ?? 'bg-gray-100 text-gray-800';
        } elseif ($group === 'performance_data' && $key === 'request_duration' && is_numeric($value)) {
            $row['value'] = $value . ' ms';
        } elseif ($group === 'performance_data' && $key === 'memory_peak' && is_numeric($value)) {
            $size = (float) $value;
            $units = ['B', 'KB', 'MB', 'GB'];
            for ($i = 0; $size >= 1024 && $i < count($units) - 1; $i++) {
                $size /= 1024;
            }
            $row['value'] = round($size, 1) . ' ' . $units[$i];
        } elseif (is_bool($value)) {
            $row['value'] = $value ? __('activitylog-browse::messages.true') : __('activitylog-browse::messages.false');
        } else {
            $row['kind'] = 'mono';
            $row['value'] = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : (string) $value;
        }

        // Values the index can filter by become links in the dialog.
        $filterKey = match (true) {
            $group === 'request_data' && in_array($key, ['method', 'route_name', 'url'], true) => $key,
            $group === 'device_data' && $key === 'ip' => 'ip',
            $group === 'execution_context' && $key === 'source' => 'source',
            $group === 'body' => 'body_search',
            default => null,
        };
        if ($filterKey && is_scalar($value) && ! is_bool($value) && (string) $value !== '' && $value !== RequestDataCollector::MASK) {
            // URLs filter by path: the query string rarely repeats exactly.
            $filterValue = $filterKey === 'url' ? strtok((string) $value, '?') : (string) $value;
            $row['filter'] = ['key' => $filterKey, 'value' => $filterValue];
        }

        return $row;
    }

    protected function authorize(): void
    {
        $gate = config('activitylog-browse.browse.gate');

        if ($gate && Gate::has($gate)) {
            Gate::authorize($gate);
        }
    }
}
