@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.related_logs_for', ['model' => class_basename($activity->subject_type) . ' #' . $activity->subject_id, 'relation' => Str::headline($relation)]))

@section('content')
    <div class="mb-4">
        <a href="{{ route('activitylog-browse.show', $activity->id) }}" class="text-blue-600 hover:text-blue-800 text-sm">
            &larr; {{ __('activitylog-browse::messages.back_to_activity', ['id' => $activity->id]) }}
        </a>
    </div>

    <div class="mb-6">
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('activitylog-browse::messages.related_logs_for', ['model' => class_basename($activity->subject_type) . ' #' . $activity->subject_id, 'relation' => Str::headline($relation)]) }}
        </h2>
    </div>

    <form method="GET" action="{{ route('activitylog-browse.related-logs', [$activity->id, $relation]) }}" class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.search') }}</label>
                <input type="text" name="search" id="search" value="{{ request('search') }}"
                       placeholder="{{ __('activitylog-browse::messages.search_placeholder') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="event" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.event') }}</label>
                <select name="event" id="event"
                        class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
                    <option value="">{{ __('activitylog-browse::messages.all') }}</option>
                    @foreach(['created', 'updated', 'deleted'] as $event)
                        <option value="{{ $event }}" @selected(request('event') === $event)>{{ $event }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.from') }}</label>
                <input type="date" name="date_from" id="date_from" value="{{ request('date_from') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div>
                <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">{{ __('activitylog-browse::messages.to') }}</label>
                <input type="date" name="date_to" id="date_to" value="{{ request('date_to') }}"
                       class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-2 border focus:border-blue-500 focus:ring-blue-500">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                    {{ __('activitylog-browse::messages.filter') }}
                </button>
                <a href="{{ route('activitylog-browse.related-logs', [$activity->id, $relation]) }}"
                   class="inline-flex items-center px-4 py-2 bg-gray-200 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-300">
                    {{ __('activitylog-browse::messages.reset') }}
                </a>
            </div>
        </div>
    </form>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.id') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.date') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.log') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.event') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.description') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.subject') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.causer') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($activities as $relatedActivity)
                        @include('activitylog-browse::partials.activity-row', ['activity' => $relatedActivity])
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                                {{ __('activitylog-browse::messages.no_related_logs') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($activities->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $activities->links() }}
            </div>
        @endif
    </div>

    <div class="mt-4 text-sm text-gray-500">
        {{ __('activitylog-browse::messages.showing_entries', ['first' => $activities->firstItem() ?? 0, 'last' => $activities->lastItem() ?? 0, 'total' => $activities->total()]) }}
    </div>
@endsection
