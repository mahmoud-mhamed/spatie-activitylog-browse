@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.deletion_history_title'))

@section('content')
    <div class="mb-6 flex items-center justify-between flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-bold text-gray-900">
                {{ __('activitylog-browse::messages.deletion_history_title') }}
            </h1>
            <p class="text-xs text-gray-500 mt-1">
                {{ __('activitylog-browse::messages.deletion_history_hint') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            @if($enabled)
                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                    {{ __('activitylog-browse::messages.retention_enabled') }}
                </span>
            @else
                <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">
                    <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>
                    {{ __('activitylog-browse::messages.retention_disabled_label') }}
                </span>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-800">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats --}}
    <div class="mb-6 grid grid-cols-2 sm:grid-cols-4 gap-3" x-data="{
        formatSize(bytes) {
            if (!bytes) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            let i = 0, size = bytes;
            for (; size >= 1024 && i < units.length - 1; i++) size /= 1024;
            return size.toFixed(2) + ' ' + units[i];
        }
    }">
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.deletion_history_total_entries') }}</div>
            <div class="text-lg font-bold text-gray-900 mt-1">{{ number_format($total) }}</div>
            <div class="text-[11px] text-gray-400 mt-0.5">/ {{ number_format($maxEntries) }} {{ __('activitylog-browse::messages.deletion_history_max') }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3">
            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.deletion_history_file_size') }}</div>
            <div class="text-lg font-bold text-gray-900 mt-1" x-text="formatSize({{ (int) $fileSize }})"></div>
            <div class="text-[11px] text-gray-400 mt-0.5">/ {{ round($maxSize / 1048576, 1) }} MB</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 col-span-2">
            <div class="text-xs text-gray-500">{{ __('activitylog-browse::messages.deletion_history_file_path') }}</div>
            <div class="text-xs font-mono text-gray-700 mt-1 truncate" title="{{ $filePath }}">{{ $filePath }}</div>
        </div>
    </div>

    @if(empty($entries))
        <div class="rounded-lg border border-gray-200 bg-white p-8 text-center">
            <svg class="w-10 h-10 mx-auto text-gray-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <p class="text-sm text-gray-500">{{ __('activitylog-browse::messages.deletion_history_empty') }}</p>
        </div>
    @else
        {{-- Entries table --}}
        <div class="rounded-lg border border-gray-200 bg-white overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 dark:bg-gray-900">
                        <tr class="text-xs uppercase text-gray-500">
                            <th class="py-2 px-3 text-start font-semibold w-8"></th>
                            <th class="py-2 px-3 text-start font-semibold">{{ __('activitylog-browse::messages.deletion_history_when') }}</th>
                            <th class="py-2 px-3 text-start font-semibold">{{ __('activitylog-browse::messages.deletion_history_trigger') }}</th>
                            <th class="py-2 px-3 text-start font-semibold">{{ __('activitylog-browse::messages.deletion_history_operation') }}</th>
                            <th class="py-2 px-3 text-end font-semibold">{{ __('activitylog-browse::messages.deletion_history_deleted') }}</th>
                            <th class="py-2 px-3 text-end font-semibold">{{ __('activitylog-browse::messages.deletion_history_size') }}</th>
                            <th class="py-2 px-3 text-end font-semibold">{{ __('activitylog-browse::messages.deletion_history_duration') }}</th>
                            <th class="py-2 px-3 text-start font-semibold">{{ __('activitylog-browse::messages.deletion_history_user') }}</th>
                        </tr>
                    </thead>
                    @foreach($entries as $entry)
                        @php
                            $trigger = $entry['trigger'] ?? 'manual';
                            $isDryRun = (bool) ($entry['dry_run'] ?? false);
                            $triggerColors = [
                                'schedule' => 'bg-blue-100 text-blue-700',
                                'cli'      => 'bg-purple-100 text-purple-700',
                                'ui'       => 'bg-green-100 text-green-700',
                                'manual'   => 'bg-gray-100 text-gray-700',
                            ];
                            $triggerColor = $triggerColors[$trigger] ?? 'bg-gray-100 text-gray-700';
                        @endphp
                        <tbody class="divide-y divide-gray-100 border-t border-gray-100" x-data="{ open: false }">
                            <tr>
                                <td class="py-2 px-3 align-top">
                                    <button type="button" @click="open = !open" class="text-gray-400 hover:text-gray-700">
                                        <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': open }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </td>
                                <td class="py-2 px-3 align-top whitespace-nowrap">
                                    <div class="font-mono text-xs text-gray-700">{{ \Carbon\Carbon::parse($entry['timestamp'])->format('Y-m-d H:i:s') }}</div>
                                    <div class="text-[10px] text-gray-400">{{ \Carbon\Carbon::parse($entry['timestamp'])->diffForHumans() }}</div>
                                </td>
                                <td class="py-2 px-3 align-top">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold uppercase {{ $triggerColor }}">
                                        {{ $trigger }}
                                    </span>
                                    @if($isDryRun)
                                        <span class="ms-1 inline-flex items-center rounded-full bg-amber-100 text-amber-700 px-2 py-0.5 text-[10px] font-bold uppercase">
                                            DRY-RUN
                                        </span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 align-top">
                                    <code class="font-mono text-xs text-gray-700">{{ $entry['operation'] ?? '—' }}</code>
                                </td>
                                <td class="py-2 px-3 align-top text-end">
                                    <div class="font-mono font-semibold text-gray-900">{{ number_format($entry['deleted_count'] ?? 0) }}</div>
                                    @if(!empty($entry['breakdown']))
                                        <div class="text-[10px] text-gray-400 font-mono">
                                            age: {{ number_format($entry['breakdown']['by_age'] ?? 0) }}
                                            · size: {{ number_format($entry['breakdown']['by_size'] ?? 0) }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2 px-3 align-top text-end whitespace-nowrap">
                                    @php
                                        $sizeBefore = $entry['size_mb_before'] ?? null;
                                        $sizeAfter  = $entry['size_mb_after'] ?? null;
                                        $diff = ($sizeBefore !== null && $sizeAfter !== null) ? round($sizeBefore - $sizeAfter, 2) : null;
                                    @endphp
                                    @if($sizeBefore !== null && $sizeAfter !== null)
                                        <div class="font-mono text-xs text-gray-700">
                                            {{ number_format($sizeBefore, 2) }}
                                            <span class="text-gray-400 mx-0.5">→</span>
                                            {{ number_format($sizeAfter, 2) }}
                                            <span class="text-gray-400">MB</span>
                                        </div>
                                        @if($diff !== null && $diff > 0)
                                            <div class="text-[10px] font-mono text-green-700">−{{ number_format($diff, 2) }} MB</div>
                                        @endif
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="py-2 px-3 align-top text-end font-mono text-xs text-gray-600">
                                    {{ number_format($entry['duration_ms'] ?? 0, 1) }} ms
                                </td>
                                <td class="py-2 px-3 align-top">
                                    @php $ctx = $entry['context'] ?? []; @endphp
                                    @if(!empty($ctx['user_name']) || !empty($ctx['user_id']))
                                        <div class="text-xs">{{ $ctx['user_name'] ?? ('#' . $ctx['user_id']) }}</div>
                                    @endif
                                    @if(!empty($ctx['ip']))
                                        <div class="text-[10px] text-gray-400 font-mono">{{ $ctx['ip'] }}</div>
                                    @elseif(!empty($ctx['command']))
                                        <div class="text-[10px] text-gray-400 font-mono">{{ $ctx['command'] }}</div>
                                    @endif
                                </td>
                            </tr>
                            <tr x-show="open" x-cloak>
                                <td></td>
                                <td colspan="7" class="bg-gray-50 dark:bg-gray-900 p-3">
                                    <pre class="text-[11px] font-mono text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                                </td>
                            </tr>
                        </tbody>
                    @endforeach
                </table>
            </div>
        </div>

        {{-- Pagination --}}
        @if($lastPage > 1)
            <div class="mt-4 flex items-center justify-between text-sm">
                <span class="text-gray-500">
                    {{ __('activitylog-browse::messages.showing_entries', [
                        'first' => (($page - 1) * $perPage) + 1,
                        'last'  => min($page * $perPage, $total),
                        'total' => $total,
                    ]) }}
                </span>
                <div class="flex items-center gap-1">
                    @if($page > 1)
                        <a href="?page={{ $page - 1 }}"
                           class="px-3 py-1.5 text-sm border border-gray-200 rounded-md hover:bg-gray-50">
                            ‹
                        </a>
                    @endif
                    <span class="px-3 py-1.5 text-sm bg-gray-100 dark:bg-gray-900 rounded-md font-mono">
                        {{ $page }} / {{ $lastPage }}
                    </span>
                    @if($page < $lastPage)
                        <a href="?page={{ $page + 1 }}"
                           class="px-3 py-1.5 text-sm border border-gray-200 rounded-md hover:bg-gray-50">
                            ›
                        </a>
                    @endif
                </div>
            </div>
        @endif

        {{-- Clear button --}}
        <div class="mt-6"
             x-data="{ confirmOpen: false }">
            <button type="button" @click="confirmOpen = true"
                    class="inline-flex items-center gap-2 rounded-md border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 shadow-sm hover:bg-red-50">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                {{ __('activitylog-browse::messages.deletion_history_clear') }}
            </button>

            <div x-show="confirmOpen" x-cloak x-transition
                 class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
                 @keydown.escape.window="confirmOpen = false">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full mx-4 p-6"
                     @click.away="confirmOpen = false">
                    <h3 class="text-lg font-semibold mb-3">{{ __('activitylog-browse::messages.deletion_history_clear') }}</h3>
                    <p class="text-sm text-gray-600 dark:text-gray-300 mb-5">
                        {{ __('activitylog-browse::messages.deletion_history_clear_confirm') }}
                    </p>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="confirmOpen = false"
                                class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm">
                            {{ __('activitylog-browse::messages.cancel') }}
                        </button>
                        <form method="POST" action="{{ route('activitylog-browse.clear-deletion-history') }}" class="m-0">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="rounded-md bg-red-600 hover:bg-red-700 px-4 py-2 text-sm text-white">
                                {{ __('activitylog-browse::messages.deletion_history_clear') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
