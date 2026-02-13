@extends('activitylog-browse::layout')

@php
    $isRelatedMode = request('via_activity') && request('via_relation');
@endphp

@section('title', $isRelatedMode
    ? __('activitylog-browse::messages.related_logs_for', ['model' => class_basename(request('subject_type', '')) . ' — ' . Str::headline(request('via_relation')), 'relation' => Str::headline(request('via_relation'))])
    : __('activitylog-browse::messages.activity_log'))

@section('content')
    @if($isRelatedMode)
        <div class="mb-4 flex items-center gap-3 text-sm">
            <a href="{{ route('activitylog-browse.show', request('via_activity')) }}" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                </svg>
                {{ __('activitylog-browse::messages.back_to_activity', ['id' => request('via_activity')]) }}
            </a>
        </div>
    @endif

    @unless($isRelatedMode)
        <div class="mb-6 flex justify-end">
            <a href="{{ route('activitylog-browse.statistics') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-blue-600 bg-white border border-blue-200 rounded-lg shadow-sm hover:bg-blue-50 hover:border-blue-300 transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                {{ __('activitylog-browse::messages.view_statistics') }}
            </a>
        </div>
    @endunless

    @include('activitylog-browse::partials.filters')

    @if($isRelatedMode)
        <div class="mb-4 px-4 py-3 bg-blue-50 border border-blue-200 rounded-lg flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
            </svg>
            <h2 class="text-sm font-semibold text-blue-800">
                {{ __('activitylog-browse::messages.related_logs_for', ['model' => class_basename(request('subject_type', '')), 'relation' => Str::headline(request('via_relation'))]) }}
            </h2>
        </div>
    @endif

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.id') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.date') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.log') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.event') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.description') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.subject') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.causer') }}</th>
                        <th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse($activities as $act)
                        @include('activitylog-browse::partials.activity-row', ['activity' => $act])
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
