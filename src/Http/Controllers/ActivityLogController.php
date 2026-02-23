<?php

namespace Mhamed\SpatieActivitylogBrowse\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Mhamed\SpatieActivitylogBrowse\Helpers\RelationDiscovery;
use Spatie\Activitylog\ActivitylogServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $query = $activityModel::with(['causer'])->orderByDesc('id');

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

        if ($request->filled('date_from') && strtotime($request->input('date_from'))) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to') && strtotime($request->input('date_to'))) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
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

        $activities = $query->paginate(
            config('activitylog-browse.browse.per_page', 25)
        )->withQueryString();

        return view('activitylog-browse::index', compact('activities'));
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

        $cacheKey = "activitylog-browse:stats:{$section}" . ($dateFrom ? ':f' . $dateFrom : '') . ($dateTo ? ':t' . $dateTo : '');
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

        $values = Cache::remember("activitylog-browse:{$column}", 60, function () use ($activityModel, $column) {
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

        return response()->json($keys->unique()->sort()->values());
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
            return response()->json([]);
        }

        $excluded = config('activitylog-browse.auto_log.excluded_attributes', []);
        $attrs = array_diff_key($activity->subject->getAttributes(), array_flip($excluded));
        ksort($attrs);

        return response()->json($attrs);
    }

    public function causerAttributes($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::findOrFail($id);

        if (!$activity->causer_type || !$activity->causer) {
            return response()->json([]);
        }

        $excluded = config('activitylog-browse.auto_log.excluded_attributes', []);
        $attrs = array_diff_key($activity->causer->getAttributes(), array_flip($excluded));
        ksort($attrs);

        return response()->json($attrs);
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

        return view('activitylog-browse::show', compact('activity', 'relations'));
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

        return view('activitylog-browse::cleanup', compact('models', 'totalRows', 'tableSize', 'oldestEntry', 'newestEntry'));
    }

    public function cleanupPreview(Request $request)
    {
        $this->authorize();

        $request->validate([
            'days' => 'required|integer|min:1',
            'models' => 'nullable|array',
            'models.*' => 'string',
        ]);

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $query = $activityModel::where('created_at', '<', now()->subDays($request->input('days')));

        if ($request->filled('models')) {
            $query->whereIn('subject_type', $request->input('models'));
        }

        return response()->json(['count' => $query->count()]);
    }

    public function cleanupDelete(Request $request)
    {
        $this->authorize();

        $request->validate([
            'days' => 'required|integer|min:1',
            'models' => 'nullable|array',
            'models.*' => 'string',
        ]);

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $query = $activityModel::where('created_at', '<', now()->subDays($request->input('days')));

        if ($request->filled('models')) {
            $query->whereIn('subject_type', $request->input('models'));
        }

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

        return redirect()->route('activitylog-browse.cleanup')
            ->with('success', __('activitylog-browse::messages.cleanup_success_delete', ['count' => $count]));
    }


    private function getTableInfo(\Closure $scoped): array
    {
        $table = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection', config('database.default'));

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

        return [
            'total_rows' => $totalRows,
            'table_size' => $tableSize,
            'oldest_entry' => $oldestEntry,
            'newest_entry' => $newestEntry,
        ];
    }

    private function clearStatsCache(): void
    {
        $sections = ['overview', 'events', 'log_names', 'models', 'causers', 'daily', 'hourly', 'weekday', 'system_user', 'attributes', 'monthly', 'peak_day'];

        foreach ($sections as $section) {
            Cache::forget("activitylog-browse:stats:{$section}");
        }

        try {
            $table = config('activitylog.table_name', 'activity_log');
            $connection = config('activitylog.database_connection', config('database.default'));
            DB::connection($connection)->statement("OPTIMIZE TABLE `{$table}`");
        } catch (\Throwable) {
        }
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

    protected function authorize(): void
    {
        $gate = config('activitylog-browse.browse.gate');

        if ($gate && Gate::has($gate)) {
            Gate::authorize($gate);
        }
    }
}
