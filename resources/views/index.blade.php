@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.activity_log'))

@section('content')
    @include('activitylog-browse::partials.filters')

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
                    @forelse($activities as $activity)
                        @include('activitylog-browse::partials.activity-row', ['activity' => $activity])
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">
                                {{ __('activitylog-browse::messages.no_activity_logs') }}
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
