@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.activity_log') . " #{$activity->id}")

@section('content')
    <div class="mb-4 flex items-center gap-3 text-sm">
        <a href="{{ route('activitylog-browse.index') }}" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('activitylog-browse::messages.back_to_list') }}
            </a>
        @if($activity->subject_type)
            <span class="text-gray-300">|</span>
            <a href="{{ route('activitylog-browse.index', ['subject_type' => $activity->subject_type, 'subject_id' => $activity->subject_id]) }}"
               class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                {{ __('activitylog-browse::messages.all_model_logs', ['model' => class_basename($activity->subject_type) . ' #' . $activity->subject_id]) }}
            </a>
        @endif
    </div>

    {{-- Metadata --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.activity_log') }} #{{ $activity->id }}</h2>
        </div>
        <div class="px-6 py-4">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4">
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('activitylog-browse::messages.description') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $activity->description }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('activitylog-browse::messages.date') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $activity->created_at->format('Y-m-d H:i:s') }} ({{ $activity->created_at->diffForHumans() }})</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('activitylog-browse::messages.log_name') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">{{ $activity->log_name }}</dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('activitylog-browse::messages.event') }}</dt>
                    <dd class="mt-1 text-sm">
                        @php
                            $eventColors = [
                                'created' => 'bg-green-100 text-green-800',
                                'updated' => 'bg-blue-100 text-blue-800',
                                'deleted' => 'bg-red-100 text-red-800',
                            ];
                            $color = $eventColors[$activity->event] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-medium {{ $color }}">
                            {{ $activity->event ?? '-' }}
                        </span>
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('activitylog-browse::messages.subject') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($activity->subject_type)
                            {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                        @else
                            <span class="text-gray-400">-</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm font-medium text-gray-500">{{ __('activitylog-browse::messages.causer') }}</dt>
                    <dd class="mt-1 text-sm text-gray-900">
                        @if($activity->causer)
                            {{ class_basename($activity->causer_type) }} #{{ $activity->causer_id }}
                        @else
                            <span class="text-gray-400">{{ __('activitylog-browse::messages.system') }}</span>
                        @endif
                    </dd>
                </div>
                @if($activity->batch_uuid)
                    <div>
                        <dt class="text-sm font-medium text-gray-500">{{ __('activitylog-browse::messages.batch_uuid') }}</dt>
                        <dd class="mt-1 text-sm text-gray-900 font-mono">{{ $activity->batch_uuid }}</dd>
                    </div>
                @endif
            </dl>
        </div>
    </div>

    {{-- Changes Diff --}}
    @php
        $properties = $activity->properties?->toArray() ?? [];
        $old = $properties['old'] ?? null;
        $attributes = $properties['attributes'] ?? null;
    @endphp

    @if($old || $attributes)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.changes') }}</h2>
            </div>
            <div class="px-6 py-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.attribute') }}</th>
                            @if($old)
                                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.old') }}</th>
                            @endif
                            @if($attributes)
                                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.new') }}</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @php
                            $allKeys = array_unique(array_merge(
                                array_keys($old ?? []),
                                array_keys($attributes ?? []),
                            ));
                            sort($allKeys);
                        @endphp
                        @foreach($allKeys as $key)
                            <tr>
                                <td class="px-4 py-2 text-sm font-medium text-gray-700">{{ $key }}</td>
                                @if($old)
                                    <td class="px-4 py-2 text-sm text-red-700 bg-red-50">
                                        @if(isset($old[$key]))
                                            @if(is_array($old[$key]))
                                                <pre class="text-xs">{{ json_encode($old[$key], JSON_PRETTY_PRINT) }}</pre>
                                            @elseif(is_null($old[$key]))
                                                <span class="italic text-gray-400">{{ __('activitylog-browse::messages.null') }}</span>
                                            @else
                                                {{ $old[$key] }}
                                            @endif
                                        @else
                                            <span class="italic text-gray-400">-</span>
                                        @endif
                                    </td>
                                @endif
                                @if($attributes)
                                    <td class="px-4 py-2 text-sm text-green-700 bg-green-50">
                                        @if(isset($attributes[$key]))
                                            @if(is_array($attributes[$key]))
                                                <pre class="text-xs">{{ json_encode($attributes[$key], JSON_PRETTY_PRINT) }}</pre>
                                            @elseif(is_null($attributes[$key]))
                                                <span class="italic text-gray-400">{{ __('activitylog-browse::messages.null') }}</span>
                                            @else
                                                {{ $attributes[$key] }}
                                            @endif
                                        @else
                                            <span class="italic text-gray-400">-</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Request Data --}}
    @php $requestData = $properties['request_data'] ?? null; @endphp
    @if($requestData)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.request_data') }}</h2>
            </div>
            <div class="px-6 py-4">
                @include('activitylog-browse::partials.properties-display', ['properties' => $requestData])
            </div>
        </div>
    @endif

    {{-- Device Data --}}
    @php $deviceData = $properties['device_data'] ?? null; @endphp
    @if($deviceData)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.device_data') }}</h2>
            </div>
            <div class="px-6 py-4">
                @include('activitylog-browse::partials.properties-display', ['properties' => $deviceData])
            </div>
        </div>
    @endif

    {{-- Performance Data --}}
    @php $performanceData = $properties['performance_data'] ?? null; @endphp
    @if($performanceData)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.performance_data') }}</h2>
            </div>
            <div class="px-6 py-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.key') }}</th>
                                <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.value') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($performanceData as $key => $value)
                                <tr>
                                    <td class="px-4 py-2 text-sm font-medium text-gray-700">{{ $key }}</td>
                                    <td class="px-4 py-2 text-sm text-gray-600">
                                        @if($key === 'request_duration' && is_numeric($value))
                                            @php
                                                $ms = (float) $value;
                                                if ($ms < 200) {
                                                    $speedLabel = __('activitylog-browse::messages.speed_fast');
                                                    $speedClass = 'bg-green-100 text-green-800';
                                                } elseif ($ms < 1000) {
                                                    $speedLabel = __('activitylog-browse::messages.speed_normal');
                                                    $speedClass = 'bg-yellow-100 text-yellow-800';
                                                } else {
                                                    $speedLabel = __('activitylog-browse::messages.speed_slow');
                                                    $speedClass = 'bg-red-100 text-red-800';
                                                }
                                            @endphp
                                            {{ $ms }} ms
                                            <span class="ms-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $speedClass }}">
                                                {{ $speedLabel }}
                                            </span>
                                        @elseif($key === 'memory_peak' && is_numeric($value))
                                            @php
                                                $bytes = (int) $value;
                                                $units = ['B', 'KB', 'MB', 'GB'];
                                                $i = 0;
                                                $size = $bytes;
                                                for (; $size >= 1024 && $i < count($units) - 1; $i++) {
                                                    $size /= 1024;
                                                }
                                            @endphp
                                            {{ round($size, 1) }} {{ $units[$i] }}
                                        @elseif(is_array($value) || is_object($value))
                                            <pre class="text-xs bg-gray-50 p-2 rounded overflow-x-auto">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        @elseif(is_null($value))
                                            <span class="text-gray-400 italic">{{ __('activitylog-browse::messages.null') }}</span>
                                        @elseif(is_bool($value))
                                            <span class="text-gray-400 italic">{{ $value ? __('activitylog-browse::messages.true') : __('activitylog-browse::messages.false') }}</span>
                                        @else
                                            {{ $value }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    {{-- App Data --}}
    @php $appData = $properties['app_data'] ?? null; @endphp
    @if($appData)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.app_data') }}</h2>
            </div>
            <div class="px-6 py-4">
                @include('activitylog-browse::partials.properties-display', ['properties' => $appData])
            </div>
        </div>
    @endif

    {{-- Session Data --}}
    @php $sessionData = $properties['session_data'] ?? null; @endphp
    @if($sessionData)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.session_data') }}</h2>
            </div>
            <div class="px-6 py-4">
                @include('activitylog-browse::partials.properties-display', ['properties' => $sessionData])
            </div>
        </div>
    @endif

    {{-- Execution Context --}}
    @php $executionContext = $properties['execution_context'] ?? null; @endphp
    @if($executionContext)
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.execution_context') }}</h2>
            </div>
            <div class="px-6 py-4">
                @include('activitylog-browse::partials.properties-display', ['properties' => $executionContext])
            </div>
        </div>
    @endif

    {{-- Related Models --}}
    @if($activity->subject && count($relations) > 0)
        <div class="bg-white rounded-lg shadow mb-6" x-data="{ search: '' }">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                <h2 class="text-lg font-semibold text-gray-900 shrink-0">{{ __('activitylog-browse::messages.related_models') }}</h2>
                @if(count($relations) > 5)
                    <input type="text" x-model="search"
                           placeholder="{{ __('activitylog-browse::messages.search') }}..."
                           class="rounded-md border-gray-300 shadow-sm text-sm px-3 py-1.5 border focus:border-blue-500 focus:ring-blue-500 w-full max-w-xs">
                @endif
            </div>
            <div class="px-6 py-4">
                <div class="flex flex-wrap gap-2">
                    @foreach($relations as $rel)
                        <a href="{{ route('activitylog-browse.related-logs', [$activity->id, $rel]) }}"
                           x-show="!search || '{{ strtolower(Str::headline($rel)) }}'.includes(search.toLowerCase())"
                           class="inline-flex items-center px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-lg hover:bg-blue-100 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 me-1.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            {{ Str::headline($rel) }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Raw JSON --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Raw Properties</h2>
        </div>
        <div class="px-6 py-4">
            <pre dir="ltr" class="text-left text-xs bg-gray-50 p-4 rounded overflow-x-auto">{{ json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
@endsection
