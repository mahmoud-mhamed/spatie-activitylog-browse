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
                array_map(fn ($v) => (int) trim($v), explode(',', $request->input('subject_id'))),
                fn ($v) => $v > 0
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
            $attr = preg_replace('/[^a-zA-Z0-9_.\-]/', '', $request->input('changed_attribute'));
            if ($attr) {
                $query->where(function ($q) use ($attr) {
                    $q->whereRaw("JSON_CONTAINS_PATH(properties, 'one', ?)", ['$.attributes.' . $attr])
                      ->orWhereRaw("JSON_CONTAINS_PATH(properties, 'one', ?)", ['$.old.' . $attr]);
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

        $table = config('activitylog.table_name', 'activity_log');
        $connection = config('activitylog.database_connection', config('database.default'));

        $dateFrom = $request->filled('date_from') && strtotime($request->input('date_from')) ? $request->input('date_from') : null;
        $dateTo = $request->filled('date_to') && strtotime($request->input('date_to')) ? $request->input('date_to') : null;
        $hasDateFilter = $dateFrom || $dateTo;

        $cacheKey = 'activitylog-browse:stats' . ($dateFrom ? ':from-' . $dateFrom : '') . ($dateTo ? ':to-' . $dateTo : '');

        return response()->json(Cache::remember($cacheKey, $hasDateFilter ? 60 : 120, function () use ($table, $connection, $dateFrom, $dateTo) {
            $activityModel = ActivitylogServiceProvider::determineActivityModel();

            $dateScope = function ($query) use ($dateFrom, $dateTo) {
                if ($dateFrom) {
                    $query->whereDate('created_at', '>=', $dateFrom);
                }
                if ($dateTo) {
                    $query->whereDate('created_at', '<=', $dateTo);
                }
                return $query;
            };

            $totalRows = $dateScope($activityModel::query())->count();

            // Table size (MySQL/MariaDB)
            $tableSize = null;
            try {
                $dbName = DB::connection($connection)->getDatabaseName();
                $result = DB::connection($connection)
                    ->selectOne("SELECT (data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ? AND table_name = ?", [$dbName, $table]);
                $tableSize = $result?->size ? (int) $result->size : null;
            } catch (\Throwable) {
                // Not MySQL or no access to information_schema
            }

            $oldestEntry = $dateScope($activityModel::query())->orderBy('id')->value('created_at');
            $newestEntry = $dateScope($activityModel::query())->orderByDesc('id')->value('created_at');

            // Events breakdown
            $eventCounts = $dateScope($activityModel::select('event', DB::raw('COUNT(*) as count'))
                ->whereNotNull('event'))
                ->groupBy('event')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['event' => $row->event, 'count' => $row->count]);

            // Log names breakdown
            $logNameCounts = $dateScope($activityModel::select('log_name', DB::raw('COUNT(*) as count'))
                ->whereNotNull('log_name'))
                ->groupBy('log_name')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => ['log_name' => $row->log_name, 'count' => $row->count]);

            // Top subject types
            $subjectTypeCounts = $dateScope($activityModel::select('subject_type', DB::raw('COUNT(*) as count'))
                ->whereNotNull('subject_type'))
                ->groupBy('subject_type')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(fn ($row) => ['subject_type' => class_basename($row->subject_type), 'count' => $row->count]);

            // Top causers
            $topCausers = $dateScope($activityModel::select('causer_type', 'causer_id', DB::raw('COUNT(*) as count'))
                ->whereNotNull('causer_type')
                ->whereNotNull('causer_id'))
                ->groupBy('causer_type', 'causer_id')
                ->orderByDesc('count')
                ->limit(10)
                ->get()
                ->map(function ($row) {
                    $label = class_basename($row->causer_type) . ' #' . $row->causer_id;
                    try {
                        $causerClass = \Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($row->causer_type) ?? $row->causer_type;
                        if (class_exists($causerClass)) {
                            $model = $causerClass::find($row->causer_id);
                            if ($model) {
                                $name = $model->name ?? $model->email ?? $model->title ?? null;
                                if ($name) {
                                    $label = $name . ' (' . class_basename($row->causer_type) . ')';
                                }
                            }
                        }
                    } catch (\Throwable) {}

                    return ['causer' => $label, 'count' => $row->count];
                });

            // Activity per day (last 30 days or within date range)
            $dailyQuery = $activityModel::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'));
            if (! $dateFrom && ! $dateTo) {
                $dailyQuery->where('created_at', '>=', now()->subDays(30));
            }
            $dailyActivity = $dateScope($dailyQuery)
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->get()
                ->map(fn ($row) => ['date' => $row->date, 'count' => $row->count]);

            // Average per day
            $avgPerDay = $totalRows > 0 && $oldestEntry
                ? round($totalRows / max(1, ($newestEntry ?? now())->diffInDays($oldestEntry) ?: 1), 1)
                : 0;

            // Activity by hour of day
            $hourlyActivity = $dateScope($activityModel::select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count')))
                ->groupBy(DB::raw('HOUR(created_at)'))
                ->orderBy('hour')
                ->get()
                ->map(fn ($row) => ['hour' => (int) $row->hour, 'count' => $row->count]);

            // Activity by day of week (1=Sunday ... 7=Saturday in MySQL DAYOFWEEK)
            $weekdayActivity = $dateScope($activityModel::select(DB::raw('DAYOFWEEK(created_at) as dow'), DB::raw('COUNT(*) as count')))
                ->groupBy(DB::raw('DAYOFWEEK(created_at)'))
                ->orderBy('dow')
                ->get()
                ->map(fn ($row) => ['dow' => (int) $row->dow, 'count' => $row->count]);

            // System vs User actions
            $userActions = $dateScope($activityModel::whereNotNull('causer_id'))->count();
            $systemActions = $totalRows - $userActions;

            // Batch operations
            $batchCount = $dateScope($activityModel::whereNotNull('batch_uuid'))
                ->distinct('batch_uuid')
                ->count('batch_uuid');
            $batchedEntries = $dateScope($activityModel::whereNotNull('batch_uuid'))->count();

            // Most changed attributes (from properties->attributes and properties->old)
            $topAttributes = collect();
            try {
                $recentProps = $dateScope($activityModel::whereNotNull('properties')
                    ->where('event', 'updated'))
                    ->orderByDesc('id')
                    ->limit(500)
                    ->pluck('properties');

                $attrCounts = [];
                foreach ($recentProps as $props) {
                    $p = $props instanceof \Illuminate\Support\Collection ? $props->toArray() : (array) $props;
                    $keys = array_unique(array_merge(
                        array_keys($p['attributes'] ?? []),
                        array_keys($p['old'] ?? [])
                    ));
                    foreach ($keys as $k) {
                        $attrCounts[$k] = ($attrCounts[$k] ?? 0) + 1;
                    }
                }
                arsort($attrCounts);
                $topAttributes = collect(array_slice($attrCounts, 0, 10, true))
                    ->map(fn ($count, $attr) => ['attribute' => $attr, 'count' => $count])
                    ->values();
            } catch (\Throwable) {}

            // Monthly activity
            $monthlyActivity = $dateScope($activityModel::select(
                    DB::raw("DATE_FORMAT(created_at, '%Y-%m') as month"),
                    DB::raw('COUNT(*) as count')
                ))
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '%Y-%m')"))
                ->orderBy('month')
                ->get()
                ->map(fn ($row) => ['month' => $row->month, 'count' => $row->count]);

            // Peak period detection (busiest single day)
            $peakDay = $dateScope($activityModel::select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count')))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderByDesc('count')
                ->first();

            // Busiest month
            $peakMonth = $monthlyActivity->sortByDesc('count')->first();

            return [
                'total_rows' => $totalRows,
                'table_size' => $tableSize,
                'oldest_entry' => $oldestEntry?->toIso8601String(),
                'newest_entry' => $newestEntry?->toIso8601String(),
                'event_counts' => $eventCounts,
                'log_name_counts' => $logNameCounts,
                'subject_type_counts' => $subjectTypeCounts,
                'top_causers' => $topCausers,
                'daily_activity' => $dailyActivity,
                'avg_per_day' => $avgPerDay,
                'hourly_activity' => $hourlyActivity,
                'weekday_activity' => $weekdayActivity,
                'user_actions' => $userActions,
                'system_actions' => $systemActions,
                'batch_count' => $batchCount,
                'batched_entries' => $batchedEntries,
                'top_attributes' => $topAttributes,
                'monthly_activity' => $monthlyActivity,
                'peak_day_date' => $peakDay?->date,
                'peak_day_count' => $peakDay?->count,
                'peak_month' => $peakMonth['month'] ?? null,
                'peak_month_count' => $peakMonth['count'] ?? null,
            ];
        }));
    }

    public function filterOptions(Request $request)
    {
        $this->authorize();

        $column = $request->input('column');
        $allowed = ['log_name', 'event', 'subject_type', 'causer_type'];

        if (! in_array($column, $allowed)) {
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

        $options = $values->map(fn ($v) => [
            'value' => $v,
            'label' => $useBasename ? class_basename($v) : $v,
        ])->values();

        return response()->json($options);
    }

    public function attributes(Request $request)
    {
        $this->authorize();

        $subjectType = $request->input('subject_type');

        if (! $subjectType) {
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
            $props = $properties instanceof \Illuminate\Support\Collection ? $properties->toArray() : (array) $properties;

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

        if (! $subjectType) {
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
            $props = $properties instanceof \Illuminate\Support\Collection ? $properties->toArray() : (array) $properties;
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
                $dbName = DB::connection($connection)->getDatabaseName();
                $result = DB::connection($connection)
                    ->selectOne("SELECT (data_length + index_length) AS size FROM information_schema.tables WHERE table_schema = ? AND table_name = ?", [$dbName, $tableName]);
                $tableSize = $result?->size ? (int) $result->size : null;
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

        if (! $causerType) {
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

                    return ['value' => (string) $model->getKey(), 'label' => $label];
                })->sortBy('label')->values());
            } catch (\Throwable) {
                // Fall through to ID-only fallback
            }
        }

        return response()->json(
            $causerIds->sort()->map(fn ($id) => ['value' => (string) $id, 'label' => '#' . $id])->values()
        );
    }

    public function subjectAttributes($id)
    {
        $this->authorize();

        $activityModel = ActivitylogServiceProvider::determineActivityModel();
        $activity = $activityModel::findOrFail($id);

        if (! $activity->subject_type || ! $activity->subject) {
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

        if (! $activity->causer_type || ! $activity->causer) {
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

        if (! $activity->subject) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $subject = $activity->subject;
        $relations = RelationDiscovery::getRelations($subject);

        if (! in_array($relation, $relations)) {
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

    public function switchLang(string $locale)
    {
        $availableLocales = config('activitylog-browse.browse.available_locales', ['en', 'ar']);

        if (! in_array($locale, $availableLocales)) {
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
