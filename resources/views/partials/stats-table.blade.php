@php
    $fullWidth = $fullWidth ?? false;
    $badge = $badge ?? false;
    $mono = $mono ?? false;
    $translate = $translate ?? false;
@endphp

<div class="{{ $fullWidth ? 'mb-8' : '' }}">
    <h2 class="text-lg font-semibold text-gray-800 mb-3">{{ $title }}</h2>
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <template x-if="sl('{{ $sectionKey }}')">
            <div class="p-4 space-y-3">
                <div class="h-5 w-full bg-gray-100 rounded animate-pulse"></div>
                <div class="h-5 w-3/4 bg-gray-100 rounded animate-pulse"></div>
                <div class="h-5 w-1/2 bg-gray-100 rounded animate-pulse"></div>
            </div>
        </template>
        <template x-if="!sl('{{ $sectionKey }}') && (!s('{{ $sectionKey }}')?.{{ $dataKey }} || s('{{ $sectionKey }}').{{ $dataKey }}.length === 0)">
            <div class="p-4 text-sm text-gray-400 text-center">{{ __('activitylog-browse::messages.stats_no_data') }}</div>
        </template>
        <template x-if="!sl('{{ $sectionKey }}') && s('{{ $sectionKey }}')?.{{ $dataKey }}?.length > 0">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ $colLabel }}</th>
                        <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase">{{ __('activitylog-browse::messages.stats_count') }}</th>
                        <th class="px-4 py-2 text-start text-xs font-medium text-gray-500 uppercase w-1/2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <template x-for="(item, idx) in s('{{ $sectionKey }}').{{ $dataKey }}" :key="idx">
                        <tr>
                            <td class="px-4 py-2 text-sm font-medium text-gray-900 {{ $mono ? 'font-mono' : '' }}">
                                @if($badge)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium"
                                        :class="{
                                            'bg-green-100 text-green-800': item.{{ $itemKey }} === 'created',
                                            'bg-blue-100 text-blue-800': item.{{ $itemKey }} === 'updated',
                                            'bg-red-100 text-red-800': item.{{ $itemKey }} === 'deleted',
                                            'bg-gray-100 text-gray-800': !['created','updated','deleted'].includes(item.{{ $itemKey }})
                                        }"
                                        x-text="item.{{ $itemKey }}"></span>
                                @else
                                    <span x-text="item.{{ $itemKey }}"></span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-sm text-gray-600" x-text="item.count.toLocaleString()"></td>
                            <td class="px-4 py-2">
                                <div class="w-full bg-gray-100 rounded-full h-2">
                                    <div class="{{ $barColor }} h-2 rounded-full" :style="'width: ' + pct(item.count, maxCount(s('{{ $sectionKey }}').{{ $dataKey }})) + '%'"></div>
                                </div>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </template>
    </div>
</div>
