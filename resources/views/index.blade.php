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

    <div class="flex gap-6 items-start">
        {{-- Main content --}}
        <div class="flex-1 min-w-0">
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
        </div>

        {{-- Model Info sidebar --}}
        <div id="model_info_card" style="display:none" class="w-80 shrink-0 sticky top-0 h-screen overflow-y-auto bg-white rounded-lg shadow p-4"
             x-data="{
                loading: false,
                info: null,
                search: '',
                selectedAttrs: @js(array_filter(explode(',', request('changed_attribute', '')))),
                formatSize(bytes) {
                    if (!bytes) return '-';
                    const units = ['B', 'KB', 'MB', 'GB'];
                    let i = 0, size = bytes;
                    for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
                    return size.toFixed(3) + ' ' + units[i];
                },
                isAttrSelected(key) {
                    return this.selectedAttrs.includes(key);
                },
                get filteredAttrs() {
                    if (!this.info) return [];
                    if (!this.search) return this.info.attributes;
                    let s = this.search.toLowerCase();
                    return this.info.attributes.filter(a => a.key.toLowerCase().includes(s) || a.label.toLowerCase().includes(s));
                }
             }">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span x-show="info" x-text="info?.stats?.model_basename"></span>
                    — {{ __('activitylog-browse::messages.model_info') }}
                </h3>
            </div>

            {{-- Loading --}}
            <div x-show="loading" class="flex justify-center py-6">
                <svg class="animate-spin h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
            </div>

            <template x-if="!loading && info">
                <div>
                    {{-- Stats mini cards --}}
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.total_logs') }}</div>
                            <div class="text-sm font-bold text-gray-900" x-text="info.stats.total_logs?.toLocaleString()"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.unique_records') }}</div>
                            <div class="text-sm font-bold text-gray-900" x-text="info.stats.unique_subjects?.toLocaleString()"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.table_name') }}</div>
                            <div class="text-sm font-bold text-gray-900 font-mono" x-text="info.stats.table_name ?? '-'"></div>
                        </div>
                        <div class="bg-gray-50 rounded-lg px-3 py-2">
                            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.stats_table_size') }}</div>
                            <div class="text-sm font-bold text-gray-900" x-text="formatSize(info.stats.table_size)"></div>
                        </div>
                    </div>

                    {{-- Events --}}
                    <div class="flex flex-wrap gap-1 mb-4">
                        <template x-for="(count, event) in info.stats.events" :key="event">
                            <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-xs font-medium"
                                  :class="{
                                      'bg-green-100 text-green-800': event === 'created',
                                      'bg-blue-100 text-blue-800': event === 'updated',
                                      'bg-red-100 text-red-800': event === 'deleted',
                                      'bg-gray-100 text-gray-800': !['created','updated','deleted'].includes(event)
                                  }">
                                <span x-text="event"></span>
                                <span class="opacity-70" x-text="count"></span>
                            </span>
                        </template>
                    </div>

                    {{-- Attributes grid --}}
                    <div>
                        <template x-if="info && info.attributes.length > 8">
                            <input type="text" x-model="search"
                                   placeholder="{{ __('activitylog-browse::messages.search') }}..."
                                   class="w-full rounded-md border-gray-300 shadow-sm text-sm px-3 py-1.5 border focus:border-blue-500 focus:ring-blue-500 mb-3">
                        </template>
                        <div class="text-xs font-medium text-gray-500 uppercase mb-2">
                            {{ __('activitylog-browse::messages.model_attributes') }}
                            <span class="text-gray-400 normal-case" x-text="'(' + info.attributes.length + ')'"></span>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="attr in filteredAttrs" :key="attr.key">
                                <button type="button"
                                        @click="toggleAttribute(attr.key)"
                                        class="inline-flex items-center gap-1 px-2 py-1 text-xs rounded-lg border cursor-pointer transition-colors"
                                        :class="isAttrSelected(attr.key)
                                            ? 'bg-blue-600 border-blue-600 text-white shadow-sm'
                                            : 'bg-white border-gray-200 text-gray-700 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700'"
                                        :title="'{{ __('activitylog-browse::messages.click_to_filter') }}'">
                                    <svg x-show="isAttrSelected(attr.key)" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 shrink-0" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" />
                                    </svg>
                                    <span class="font-medium" x-text="attr.label"></span>
                                    <span class="opacity-70" x-show="attr.has_translation" x-text="'(' + attr.key + ')'"></span>
                                    <span x-show="!attr.has_translation" class="font-mono opacity-70" x-text="attr.key === attr.label ? '' : attr.key"></span>
                                </button>
                            </template>
                            <template x-if="filteredAttrs.length === 0 && search">
                                <span class="text-sm text-gray-400 italic py-1">{{ __('activitylog-browse::messages.no_data') }}</span>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
@endsection
