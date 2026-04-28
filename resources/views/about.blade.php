@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.about_title'))

@section('content')
    @php
        $featureLabels = [
            'auto_log'          => __('activitylog-browse::messages.feature_auto_log'),
            'request_data'      => __('activitylog-browse::messages.feature_request_data'),
            'device_data'       => __('activitylog-browse::messages.feature_device_data'),
            'performance_data'  => __('activitylog-browse::messages.feature_performance_data'),
            'app_data'          => __('activitylog-browse::messages.feature_app_data'),
            'session_data'      => __('activitylog-browse::messages.feature_session_data'),
            'execution_context' => __('activitylog-browse::messages.feature_execution_context'),
            'browse_ui'         => __('activitylog-browse::messages.feature_browse_ui'),
            'retention'         => __('activitylog-browse::messages.feature_retention'),
        ];
    @endphp

    {{-- Hero --}}
    <section class="text-center py-8 mb-6">
        <h1 class="text-4xl font-extrabold text-blue-600 tracking-tight">
            {{ __('activitylog-browse::messages.activity_log') }}
        </h1>
        <p class="mt-2 text-sm text-gray-600 max-w-xl mx-auto">
            {{ __('activitylog-browse::messages.about_tagline') }}
        </p>
        <div class="mt-3 flex justify-center gap-2">
            <span class="inline-block px-3 py-1 text-xs font-mono font-semibold rounded-full bg-blue-100 text-blue-700">
                v{{ $packageVersion }}
            </span>
            <span class="inline-block px-3 py-1 text-xs font-mono rounded-full bg-gray-100 text-gray-700">
                {{ $packageName }}
            </span>
        </div>
    </section>

    {{-- Quick Links --}}
    <section class="bg-white border border-gray-200 rounded-lg p-5 mb-4">
        <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
            </svg>
            {{ __('activitylog-browse::messages.about_quick_links') }}
        </h3>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('activitylog-browse.index') }}" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:border-blue-500 hover:text-blue-600 hover:bg-blue-50">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                {{ __('activitylog-browse::messages.activity_log') }}
            </a>
            <a href="{{ route('activitylog-browse.statistics') }}" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:border-blue-500 hover:text-blue-600 hover:bg-blue-50">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                {{ __('activitylog-browse::messages.view_statistics') }}
            </a>
            <a href="{{ route('activitylog-browse.cleanup') }}" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm border border-gray-300 rounded-md hover:border-red-500 hover:text-red-600 hover:bg-red-50">
                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                {{ __('activitylog-browse::messages.cleanup') }}
            </a>
        </div>
    </section>

    {{-- Environment + Database --}}
    <section class="grid md:grid-cols-2 gap-4 mb-4" x-data="{
        formatSize(bytes) {
            if (!bytes) return '—';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0, size = bytes;
            for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
            return size.toFixed(3) + ' ' + units[i];
        }
    }">
        <div class="bg-white border border-gray-200 rounded-lg p-5">
            <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="2" y="3" width="20" height="14" rx="2"/>
                    <line x1="8" y1="21" x2="16" y2="21"/>
                    <line x1="12" y1="17" x2="12" y2="21"/>
                </svg>
                {{ __('activitylog-browse::messages.about_environment') }}
            </h3>
            <dl class="text-sm divide-y divide-gray-100">
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_package') }}</dt><dd class="font-mono font-semibold">v{{ $packageVersion }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_spatie_activitylog') }}</dt><dd class="font-mono font-semibold">{{ $spatieVersion ?? '—' }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">Laravel</dt><dd class="font-mono font-semibold">{{ $laravelVersion }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">PHP</dt><dd class="font-mono font-semibold">{{ $phpVersion }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_environment_label') }}</dt><dd class="font-mono font-semibold">{{ $environment }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_db_connection') }}</dt><dd class="font-mono font-semibold">{{ $connection }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_table_name') }}</dt><dd class="font-mono font-semibold">{{ $tableName }}</dd></div>
            </dl>
        </div>

        <div class="bg-white border border-gray-200 rounded-lg p-5">
            <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <ellipse cx="12" cy="5" rx="9" ry="3"/>
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/>
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>
                </svg>
                {{ __('activitylog-browse::messages.about_database_snapshot') }}
            </h3>
            <dl class="text-sm divide-y divide-gray-100">
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.cleanup_total_rows') }}</dt><dd class="font-mono font-semibold">{{ number_format($totalRows) }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.cleanup_table_size') }}</dt><dd class="font-mono font-semibold" x-text="formatSize({{ (int) $tableSize }})"></dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.cleanup_oldest_entry') }}</dt><dd class="font-mono font-semibold">{{ $oldestEntry ? $oldestEntry->format('Y-m-d H:i') : '—' }}</dd></div>
                <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.cleanup_newest_entry') }}</dt><dd class="font-mono font-semibold">{{ $newestEntry ? $newestEntry->format('Y-m-d H:i') : '—' }}</dd></div>
            </dl>
        </div>
    </section>

    {{-- Feature Status --}}
    <section class="bg-white border border-gray-200 rounded-lg p-5 mb-4">
        <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            {{ __('activitylog-browse::messages.about_features') }}
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
            @foreach($features as $key => $enabled)
                <div class="flex items-center justify-between px-3 py-2 rounded border {{ $enabled ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }}">
                    <span class="text-sm text-gray-700">{{ $featureLabels[$key] }}</span>
                    @if($enabled)
                        <span class="text-xs font-bold uppercase rounded-full px-2 py-0.5 bg-green-600 text-white">
                            {{ __('activitylog-browse::messages.about_on') }}
                        </span>
                    @else
                        <span class="text-xs font-bold uppercase rounded-full px-2 py-0.5 bg-gray-300 text-gray-600">
                            {{ __('activitylog-browse::messages.about_off') }}
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Retention Detail --}}
    @php
        $r = $config['retention'] ?? [];
        $perModelRules = (array) ($r['per_model'] ?? []);
        $perLogNameRules = (array) ($r['per_log_name'] ?? []);
        $foreverModels = collect($perModelRules)->filter(fn($v) => is_string($v) && strtolower($v) === 'forever')->keys();
    @endphp
    <section class="bg-white border border-gray-200 rounded-lg p-5 mb-4">
        <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"/>
                <polyline points="12 6 12 12 16 14"/>
            </svg>
            {{ __('activitylog-browse::messages.retention_settings') }}
        </h3>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm mb-4">
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.retention_default_days') }}</div>
                <div class="font-semibold">{{ (int) ($r['default_days'] ?? 0) }} {{ __('activitylog-browse::messages.days') }}</div>
            </div>
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.retention_max_rows') }}</div>
                <div class="font-semibold">{{ ($r['max_rows'] ?? null) === null ? '—' : number_format((int) $r['max_rows']) }}</div>
            </div>
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.retention_max_size') }}</div>
                <div class="font-semibold">{{ ($r['max_size_mb'] ?? null) === null ? '—' : ((int) $r['max_size_mb']) . ' MB' }}</div>
            </div>
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.retention_schedule') }}</div>
                <div class="font-semibold">
                    {{ ($r['schedule'] ?? null) ? __('activitylog-browse::messages.retention_schedule_' . $r['schedule']) : '—' }}
                    @if(! empty($r['schedule']))
                        <span class="text-xs font-mono font-normal text-gray-500 ms-1">@ {{ $r['schedule_time'] ?? '03:00' }}</span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Priority hierarchy --}}
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 mb-4">
            <h4 class="text-sm font-bold text-blue-900 mb-3 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 6h13M3 12h9m-9 6h6m4-6l4 4-4 4m4-12l-4-4-4 4"/>
                </svg>
                {{ __('activitylog-browse::messages.retention_priority_title') }}
            </h4>
            <ol class="space-y-2 text-xs text-blue-900">
                <li class="flex items-start gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-600 text-white font-bold text-[10px] shrink-0">1</span>
                    <div>
                        <strong class="font-semibold">per_model / per_log_name</strong>
                        — {{ __('activitylog-browse::messages.retention_priority_1') }}
                    </div>
                </li>
                <li class="flex items-start gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-500 text-white font-bold text-[10px] shrink-0">2</span>
                    <div>
                        <strong class="font-semibold">max_rows / max_size_mb</strong>
                        — {{ __('activitylog-browse::messages.retention_priority_2') }}
                    </div>
                </li>
                <li class="flex items-start gap-2">
                    <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-blue-400 text-white font-bold text-[10px] shrink-0">3</span>
                    <div>
                        <strong class="font-semibold">default_days</strong>
                        — {{ __('activitylog-browse::messages.retention_priority_3') }}
                    </div>
                </li>
            </ol>
        </div>

        {{-- Behavior at size limit --}}
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 mb-4">
            <h4 class="text-sm font-bold text-amber-900 mb-2 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z"/>
                </svg>
                {{ __('activitylog-browse::messages.retention_behavior_title') }}
            </h4>
            <p class="text-xs text-amber-900 mb-3 leading-relaxed">
                {{ __('activitylog-browse::messages.retention_behavior_intro') }}
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-start border-b border-amber-200">
                            <th class="py-1.5 px-2 font-semibold text-amber-900 text-start">{{ __('activitylog-browse::messages.retention_behavior_rule') }}</th>
                            <th class="py-1.5 px-2 font-semibold text-amber-900 text-start">{{ __('activitylog-browse::messages.retention_behavior_age') }}</th>
                            <th class="py-1.5 px-2 font-semibold text-amber-900 text-start">{{ __('activitylog-browse::messages.retention_behavior_size') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-amber-100">
                        <tr>
                            <td class="py-1.5 px-2 font-mono">—</td>
                            <td class="py-1.5 px-2">{{ __('activitylog-browse::messages.retention_behavior_age_default') }}</td>
                            <td class="py-1.5 px-2 text-red-700">{{ __('activitylog-browse::messages.retention_behavior_size_can_delete') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-2 font-mono">365</td>
                            <td class="py-1.5 px-2">{{ __('activitylog-browse::messages.retention_behavior_age_custom') }}</td>
                            <td class="py-1.5 px-2 text-green-700">{{ __('activitylog-browse::messages.retention_behavior_size_protected_window') }}</td>
                        </tr>
                        <tr>
                            <td class="py-1.5 px-2 font-mono font-bold text-purple-700">'forever'</td>
                            <td class="py-1.5 px-2 text-green-700">{{ __('activitylog-browse::messages.retention_behavior_age_never') }}</td>
                            <td class="py-1.5 px-2 text-green-700">{{ __('activitylog-browse::messages.retention_behavior_size_protected') }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-xs text-amber-900 mt-3 leading-relaxed">
                <strong>{{ __('activitylog-browse::messages.retention_behavior_tldr_label') }}</strong>
                {{ __('activitylog-browse::messages.retention_behavior_tldr') }}
            </p>
        </div>

        {{-- Per-model rules --}}
        @if(! empty($perModelRules) || ! empty($perLogNameRules))
            <div class="grid md:grid-cols-2 gap-4">
                @if(! empty($perModelRules))
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2 flex items-center gap-2">
                            {{ __('activitylog-browse::messages.retention_per_model') }}
                            <span class="rounded-full bg-gray-200 text-gray-700 px-1.5 py-0.5 text-[10px] font-bold">{{ count($perModelRules) }}</span>
                        </h4>
                        <ul class="rounded border border-gray-200 divide-y divide-gray-100 text-sm bg-white">
                            @foreach($perModelRules as $modelClass => $rule)
                                @php
                                    $isForever = is_string($rule) && strtolower($rule) === 'forever';
                                    $cutoff = $isForever ? null : now()->subDays((int) $rule);
                                @endphp
                                <li class="px-3 py-2">
                                    <div class="flex items-center justify-between gap-2">
                                        <div class="min-w-0 flex-1">
                                            <div class="text-gray-800 font-medium truncate">{{ class_basename($modelClass) }}</div>
                                            <div class="text-[11px] font-mono text-gray-400 truncate" title="{{ $modelClass }}">{{ $modelClass }}</div>
                                        </div>
                                        @if($isForever)
                                            <span class="shrink-0 rounded-full bg-purple-100 text-purple-800 px-2 py-0.5 text-xs font-medium">
                                                {{ __('activitylog-browse::messages.retention_forever') }}
                                            </span>
                                        @else
                                            <div class="shrink-0 text-right">
                                                <div class="text-gray-700 text-xs font-semibold">{{ (int) $rule }} {{ __('activitylog-browse::messages.days') }}</div>
                                                <div class="text-[10px] text-gray-400 font-mono">{{ __('activitylog-browse::messages.retention_cutoff') }}: {{ $cutoff->format('Y-m-d') }}</div>
                                            </div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if(! empty($perLogNameRules))
                    <div>
                        <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2 flex items-center gap-2">
                            {{ __('activitylog-browse::messages.retention_per_log_name') }}
                            <span class="rounded-full bg-gray-200 text-gray-700 px-1.5 py-0.5 text-[10px] font-bold">{{ count($perLogNameRules) }}</span>
                        </h4>
                        <ul class="rounded border border-gray-200 divide-y divide-gray-100 text-sm bg-white">
                            @foreach($perLogNameRules as $logName => $rule)
                                @php
                                    $isForever = is_string($rule) && strtolower($rule) === 'forever';
                                    $cutoff = $isForever ? null : now()->subDays((int) $rule);
                                @endphp
                                <li class="flex items-center justify-between gap-2 px-3 py-2">
                                    <span class="text-gray-800 font-medium font-mono">{{ $logName }}</span>
                                    @if($isForever)
                                        <span class="shrink-0 rounded-full bg-purple-100 text-purple-800 px-2 py-0.5 text-xs font-medium">
                                            {{ __('activitylog-browse::messages.retention_forever') }}
                                        </span>
                                    @else
                                        <div class="shrink-0 text-right">
                                            <div class="text-gray-700 text-xs font-semibold">{{ (int) $rule }} {{ __('activitylog-browse::messages.days') }}</div>
                                            <div class="text-[10px] text-gray-400 font-mono">{{ __('activitylog-browse::messages.retention_cutoff') }}: {{ $cutoff->format('Y-m-d') }}</div>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endif

        {{-- Other retention internals --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm mt-4">
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.retention_chunk_size') }}</div>
                <div class="font-semibold font-mono">{{ (int) ($r['chunk_size'] ?? 1000) }}</div>
            </div>
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.retention_optimize_after') }}</div>
                <div class="font-semibold">
                    {{ ! empty($r['optimize_after']) ? __('activitylog-browse::messages.about_on') : __('activitylog-browse::messages.about_off') }}
                </div>
            </div>
            <div class="rounded border border-gray-200 p-3">
                <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.retention_protected_count') }}</div>
                <div class="font-semibold">
                    {{ $foreverModels->count() }} {{ __('activitylog-browse::messages.retention_models_word') }}
                </div>
            </div>
        </div>
    </section>

    {{-- Current Configuration --}}
    @php
        $browseCfg = $config['browse'] ?? [];
        $autoLog = $config['auto_log'] ?? [];
        $enrichmentSections = [
            'request_data', 'device_data', 'performance_data', 'app_data', 'session_data', 'execution_context',
        ];
    @endphp
    <section class="bg-white border border-gray-200 rounded-lg p-5 mb-4">
        <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/>
                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            {{ __('activitylog-browse::messages.about_current_config') }}
        </h3>

        <div class="grid md:grid-cols-2 gap-4">
            {{-- Browse UI --}}
            <div class="rounded border border-gray-200 p-4">
                <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">{{ __('activitylog-browse::messages.feature_browse_ui') }}</h4>
                <dl class="text-sm divide-y divide-gray-100">
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_prefix') }}</dt><dd class="font-mono">{{ $browseCfg['prefix'] ?? '—' }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_middleware') }}</dt><dd class="font-mono text-xs">{{ implode(', ', (array) ($browseCfg['middleware'] ?? [])) ?: '—' }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_per_page') }}</dt><dd class="font-mono">{{ (int) ($browseCfg['per_page'] ?? 25) }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_gate') }}</dt><dd class="font-mono">{{ $browseCfg['gate'] ?? '—' }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_password') }}</dt><dd class="font-mono">{{ ! empty($browseCfg['password']) ? '••••••' : '—' }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_locales') }}</dt><dd class="font-mono">{{ implode(', ', (array) ($browseCfg['available_locales'] ?? [])) ?: '—' }}</dd></div>
                </dl>
            </div>

            {{-- Auto-Log --}}
            <div class="rounded border border-gray-200 p-4">
                <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2">{{ __('activitylog-browse::messages.feature_auto_log') }}</h4>
                <dl class="text-sm divide-y divide-gray-100">
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_events') }}</dt><dd class="font-mono text-xs">{{ implode(', ', (array) ($autoLog['events'] ?? [])) ?: '—' }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_models') }}</dt><dd class="font-mono">{{ is_array($autoLog['models'] ?? null) ? count($autoLog['models']) : ($autoLog['models'] ?? '—') }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_log_name') }}</dt><dd class="font-mono">{{ $autoLog['log_name'] ?? '—' }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_log_only_dirty') }}</dt><dd class="font-mono">{{ ! empty($autoLog['log_only_dirty']) ? __('activitylog-browse::messages.about_on') : __('activitylog-browse::messages.about_off') }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_submit_empty_logs') }}</dt><dd class="font-mono">{{ ! empty($autoLog['submit_empty_logs']) ? __('activitylog-browse::messages.about_on') : __('activitylog-browse::messages.about_off') }}</dd></div>
                    <div class="flex justify-between py-1.5"><dt class="text-gray-500">{{ __('activitylog-browse::messages.about_cfg_exclude_null_on_create') }}</dt><dd class="font-mono">{{ ! empty($autoLog['exclude_null_on_create']) ? __('activitylog-browse::messages.about_on') : __('activitylog-browse::messages.about_off') }}</dd></div>
                </dl>
            </div>
        </div>

        {{-- Excluded Attributes + Excluded Models lists --}}
        @php
            $excludedAttributes = (array) ($autoLog['excluded_attributes'] ?? []);
            $excludedModels = (array) ($autoLog['excluded_models'] ?? []);
        @endphp
        <div class="grid md:grid-cols-2 gap-4 mt-4">
            <div>
                <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2 flex items-center gap-2">
                    {{ __('activitylog-browse::messages.about_cfg_excluded_attributes') }}
                    <span class="rounded-full bg-gray-200 text-gray-700 px-1.5 py-0.5 text-[10px] font-bold">{{ count($excludedAttributes) }}</span>
                </h4>
                @if(! empty($excludedAttributes))
                    <div class="rounded border border-gray-200 p-3 bg-gray-50">
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($excludedAttributes as $attr)
                                <span class="inline-block rounded bg-white border border-gray-200 text-gray-700 px-2 py-0.5 text-[11px] font-mono">{{ $attr }}</span>
                            @endforeach
                        </div>
                    </div>
                @else
                    <p class="text-xs text-gray-400 italic">{{ __('activitylog-browse::messages.about_cfg_none') }}</p>
                @endif
            </div>

            <div>
                <h4 class="text-xs font-bold uppercase tracking-wide text-gray-500 mb-2 flex items-center gap-2">
                    {{ __('activitylog-browse::messages.about_cfg_excluded_models') }}
                    <span class="rounded-full bg-gray-200 text-gray-700 px-1.5 py-0.5 text-[10px] font-bold">{{ count($excludedModels) }}</span>
                </h4>
                @if(! empty($excludedModels))
                    <ul class="rounded border border-gray-200 divide-y divide-gray-100 text-sm bg-white">
                        @foreach($excludedModels as $model)
                            <li class="flex items-center justify-between px-3 py-1.5">
                                <span class="text-gray-800 font-medium">{{ class_basename($model) }}</span>
                                <span class="text-gray-400 text-[11px] font-mono truncate ml-3" title="{{ $model }}">{{ $model }}</span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-xs text-gray-400 italic">{{ __('activitylog-browse::messages.about_cfg_none') }}</p>
                @endif
            </div>
        </div>

        {{-- Enrichment fields per section --}}
        <div class="mt-4 grid md:grid-cols-2 lg:grid-cols-3 gap-3">
            @foreach($enrichmentSections as $section)
                @php
                    $cfg = $config[$section] ?? [];
                    $on = (bool) ($cfg['enabled'] ?? false);
                    $fields = (array) ($cfg['fields'] ?? []);
                    $enabledFields = array_keys(array_filter($fields));
                @endphp
                <div class="rounded border {{ $on ? 'border-green-200 bg-green-50' : 'border-gray-200 bg-gray-50' }} p-3">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-gray-700">
                            {{ __('activitylog-browse::messages.feature_' . $section) }}
                        </h4>
                        <span class="text-[10px] font-bold uppercase rounded-full px-2 py-0.5 {{ $on ? 'bg-green-600 text-white' : 'bg-gray-300 text-gray-600' }}">
                            {{ $on ? __('activitylog-browse::messages.about_on') : __('activitylog-browse::messages.about_off') }}
                        </span>
                    </div>
                    @if($on && ! empty($enabledFields))
                        <div class="flex flex-wrap gap-1">
                            @foreach($enabledFields as $field)
                                <span class="inline-block rounded bg-white text-gray-700 border border-gray-200 px-1.5 py-0.5 text-[11px] font-mono">{{ $field }}</span>
                            @endforeach
                        </div>
                    @elseif($on)
                        <p class="text-xs text-gray-500">{{ __('activitylog-browse::messages.about_cfg_no_fields') }}</p>
                    @else
                        <p class="text-xs text-gray-400">—</p>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    {{-- Artisan Commands --}}
    <section class="bg-white border border-gray-200 rounded-lg p-5 mb-4">
        <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="4 17 10 11 4 5"/>
                <line x1="12" y1="19" x2="20" y2="19"/>
            </svg>
            {{ __('activitylog-browse::messages.about_commands') }}
        </h3>
        <div class="space-y-1.5">
            <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-3 px-3 py-2 bg-gray-50 rounded">
                <code class="font-mono text-xs font-semibold text-blue-700 whitespace-nowrap">php artisan activitylog-browse:install</code>
                <span class="text-xs text-gray-500">{{ __('activitylog-browse::messages.about_cmd_install') }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-3 px-3 py-2 bg-gray-50 rounded">
                <code class="font-mono text-xs font-semibold text-blue-700 whitespace-nowrap">php artisan activitylog-browse:prune</code>
                <span class="text-xs text-gray-500">{{ __('activitylog-browse::messages.about_cmd_prune') }}</span>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-3 px-3 py-2 bg-gray-50 rounded">
                <code class="font-mono text-xs font-semibold text-blue-700 whitespace-nowrap">php artisan activitylog-browse:prune --dry-run</code>
                <span class="text-xs text-gray-500">{{ __('activitylog-browse::messages.about_cmd_prune_dry') }}</span>
            </div>
        </div>
    </section>

    {{-- Features list --}}
    <section class="bg-white border border-gray-200 rounded-lg p-5 mb-4">
        <h3 class="text-sm font-bold text-gray-900 mb-3 flex items-center gap-2">
            <svg class="w-4 h-4 text-blue-600" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            {{ __('activitylog-browse::messages.about_capabilities') }}
        </h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
            @foreach([
                'about_cap_auto_log',
                'about_cap_uuid',
                'about_cap_enrichment',
                'about_cap_browse_ui',
                'about_cap_statistics',
                'about_cap_cleanup',
                'about_cap_retention',
                'about_cap_per_model',
                'about_cap_size_limit',
                'about_cap_i18n',
                'about_cap_tenancy',
                'about_cap_attribute_translation',
            ] as $key)
                <div class="flex items-center gap-2 px-3 py-1.5 bg-gray-50 rounded text-gray-700">
                    <span class="text-green-600 font-bold">&#10003;</span>
                    <span class="text-xs">{{ __('activitylog-browse::messages.' . $key) }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Footer --}}
    <div class="text-center py-4 text-xs text-gray-500">
        {{ $packageName }} v{{ $packageVersion }} &middot; Laravel {{ $laravelVersion }} &middot; PHP {{ $phpVersion }} &middot; MIT License
    </div>
@endsection
