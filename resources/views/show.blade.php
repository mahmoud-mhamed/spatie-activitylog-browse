@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.activity_log') . " #{$activity->id}")

@section('content')
    <div class="mb-4">
        <a href="{{ route('activitylog-browse.index') }}" class="text-blue-600 hover:text-blue-800 text-sm">&larr; {{ __('activitylog-browse::messages.back_to_list') }}</a>
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
                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.attribute') }}</th>
                            @if($old)
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.old') }}</th>
                            @endif
                            @if($attributes)
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.new') }}</th>
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

    {{-- Raw JSON --}}
    <div class="bg-white rounded-lg shadow mb-6">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('activitylog-browse::messages.raw_properties') }}</h2>
        </div>
        <div class="px-6 py-4">
            <pre class="text-xs bg-gray-50 p-4 rounded overflow-x-auto">{{ json_encode($properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
        </div>
    </div>
@endsection
