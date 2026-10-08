@extends('activitylog-browse::layout')

@section('title', __('activitylog-browse::messages.timeline') . ' — ' . class_basename($subjectType) . ' #' . $subjectId)

@section('content')
    @php
        $eventDots = ['created' => 'bg-green-500', 'updated' => 'bg-blue-500', 'deleted' => 'bg-red-500'];
        $eventBadges = ['created' => 'bg-green-100 text-green-800', 'updated' => 'bg-blue-100 text-blue-800', 'deleted' => 'bg-red-100 text-red-800'];
    @endphp

    <div class="mb-4 flex flex-wrap items-center gap-3 text-sm">
        <a href="{{ route('activitylog-browse.index') }}" class="inline-flex items-center gap-1 text-blue-600 hover:text-blue-800">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 rtl:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
            </svg>
            {{ __('activitylog-browse::messages.back_to_list') }}
        </a>
        <span class="text-gray-300">|</span>
        <a href="{{ route('activitylog-browse.index', ['subject_type' => $subjectType, 'subject_id' => $subjectId]) }}" class="text-blue-600 hover:text-blue-800">
            {{ __('activitylog-browse::messages.view_as_list') }}
        </a>
    </div>

    <div class="bg-white rounded-lg shadow mb-6 px-6 py-4 flex flex-wrap items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-900">
            {{ __('activitylog-browse::messages.timeline') }} — {{ class_basename($subjectType) }} <span class="text-gray-400 tabular-nums">#{{ $subjectId }}</span>
        </h2>
        @unless($subject)
            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">{{ __('activitylog-browse::messages.subject_missing') }}</span>
        @endunless
        <span class="ms-auto text-sm text-gray-500 tabular-nums">{{ __('activitylog-browse::messages.showing_entries', ['first' => $activities->firstItem() ?? 0, 'last' => $activities->lastItem() ?? 0, 'total' => $activities->total()]) }}</span>
    </div>

    @if(session('activitylog_browse_success'))
        <div role="status" class="mb-4 px-4 py-3 rounded-lg border border-green-200 bg-green-50 text-sm text-green-800">{{ session('activitylog_browse_success') }}</div>
    @endif
    @if(session('activitylog_browse_error'))
        <div role="alert" class="mb-4 px-4 py-3 rounded-lg border border-red-200 bg-red-50 text-sm text-red-800">{{ session('activitylog_browse_error') }}</div>
    @endif

    <ol class="relative border-s-2 border-gray-200 ms-3 space-y-6">
        @forelse($activities as $activity)
            @php
                $props = $activity->properties?->toArray() ?? [];
                $rows = $changeRows[$activity->getKey()] ?? [];
                $createdAt = \Illuminate\Support\Carbon::parse($activity->created_at);
                $causerName = $activity->causer
                    ? ($activity->causer->name ?? $activity->causer->title ?? trim(($activity->causer->first_name ?? '') . ' ' . ($activity->causer->last_name ?? '')) ?: null)
                    : null;
                // Restoring to the newest version would change nothing; a "deleted" row has no version to go back to.
                $restorable = $canRestore && $activity->getKey() != $latestId && $activity->event !== 'deleted';
            @endphp
            <li class="ms-6">
                <span class="absolute -start-[9px] mt-4 h-4 w-4 rounded-full border-2 border-white {{ $eventDots[$activity->event] ?? 'bg-gray-400' }}"></span>
                <div class="bg-white rounded-lg shadow">
                    <div class="px-4 py-3 border-b border-gray-200 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
                        <a href="{{ route('activitylog-browse.show', $activity->id) }}" data-tip="{{ __('activitylog-browse::messages.tip_view_details') }}" data-tip-align="start"
                           class="font-medium text-blue-600 hover:text-blue-800 hover:underline tabular-nums">#{{ $activity->id }}</a>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $eventBadges[$activity->event] ?? 'bg-gray-100 text-gray-800' }}">{{ $activity->event ?? '-' }}</span>
                        <span class="text-gray-900">{{ $activity->description }}</span>
                        <span class="text-gray-500">{{ $createdAt->diffForHumans() }} · <span dir="ltr" class="tabular-nums">{{ $createdAt->format('Y-m-d H:i:s') }}</span></span>
                        <span class="text-gray-500">
                            @if($activity->causer)
                                {{ class_basename($activity->causer_type) }} <span class="text-gray-400">#{{ $activity->causer_id }}</span>
                                @if($causerName) — {{ $causerName }} @endif
                            @else
                                {{ __('activitylog-browse::messages.system') }}
                            @endif
                        </span>
                        @if($restorable)
                            <div x-data class="ms-auto">
                                <button type="button"
                                        @click="$dispatch('open-restore', @js(['id' => $activity->id, 'preview' => route('activitylog-browse.restore-preview', $activity->id), 'action' => route('activitylog-browse.restore', $activity->id)]))"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md border border-orange-300 text-orange-700 hover:bg-orange-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6" />
                                    </svg>
                                    {{ __('activitylog-browse::messages.restore_version') }}
                                </button>
                            </div>
                        @endif
                    </div>
                    @if($rows)
                        <div class="overflow-x-auto">
                            @include('activitylog-browse::partials.change-rows', ['rows' => $rows, 'hasOld' => ! empty($props['old']), 'hasNew' => ! empty($props['attributes'])])
                        </div>
                    @else
                        <p class="px-4 py-3 text-sm text-gray-400 italic">{{ __('activitylog-browse::messages.no_data') }}</p>
                    @endif
                </div>
            </li>
        @empty
            <li class="ms-6 text-sm text-gray-500">{{ __('activitylog-browse::messages.no_activity_logs') }}</li>
        @endforelse
    </ol>

    @if($activities->hasPages())
        <div class="mt-6">{{ $activities->links() }}</div>
    @endif

    @if($canRestore)
        {{-- Restore confirmation: shows exactly which fields change before anything is written --}}
        <div x-data="{
                open: false,
                activityId: null,
                action: null,
                rows: [],
                loading: false,
                failed: false,
                show(payload) {
                    this.activityId = payload.id;
                    this.action = payload.action;
                    this.rows = [];
                    this.failed = false;
                    this.loading = true;
                    this.open = true;
                    fetch(payload.preview, { headers: { Accept: 'application/json' } })
                        .then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
                        .then(data => { if (this.activityId === payload.id) this.rows = data.rows; })
                        .catch(e => { console.error('activitylog-browse: restore preview failed', e); if (this.activityId === payload.id) this.failed = true; })
                        .finally(() => { if (this.activityId === payload.id) this.loading = false; });
                }
             }"
             @open-restore.window="show($event.detail)"
             @keydown.escape.window="open = false"
             x-show="open" style="display:none"
             class="fixed inset-0 z-[9999] flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>
            <div role="dialog" aria-modal="true" class="relative w-full max-w-3xl max-h-[85vh] flex flex-col bg-white rounded-lg shadow-xl border border-gray-200">
                <div class="px-5 py-3 border-b border-gray-200 flex items-center gap-3">
                    <h3 class="text-sm font-semibold text-gray-900">
                        {{ __('activitylog-browse::messages.restore_confirm_title') }}
                        <span class="text-gray-400 font-normal tabular-nums" x-text="'#' + activityId"></span>
                    </h3>
                    <button type="button" @click="open = false" class="ms-auto text-gray-400 hover:text-gray-600" title="{{ __('activitylog-browse::messages.close') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="overflow-y-auto px-5 py-4 space-y-3 text-sm">
                    <template x-if="loading">
                        <p class="text-gray-400">{{ __('activitylog-browse::messages.loading') }}...</p>
                    </template>
                    <template x-if="failed">
                        <p class="text-red-600">{{ __('activitylog-browse::messages.load_failed') }}</p>
                    </template>
                    <template x-if="!loading && !failed && rows.length === 0">
                        <p class="text-gray-500">{{ __('activitylog-browse::messages.restore_nothing') }}</p>
                    </template>
                    <template x-if="rows.length > 0">
                        <div class="space-y-3">
                            <p class="px-3 py-2 rounded border border-orange-200 bg-orange-50 text-orange-800">{{ __('activitylog-browse::messages.restore_confirm_body') }}</p>
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-200 text-xs text-gray-500">
                                        <th class="text-start py-1.5 pe-3 font-medium">{{ __('activitylog-browse::messages.attr') }}</th>
                                        <th class="text-start py-1.5 pe-3 font-medium">{{ __('activitylog-browse::messages.current_value') }}</th>
                                        <th class="text-start py-1.5 font-medium">{{ __('activitylog-browse::messages.restored_value') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template x-for="row in rows" :key="row.key">
                                        <tr class="border-b border-gray-100 align-top">
                                            <td class="py-1.5 pe-3 font-medium text-gray-700 whitespace-nowrap" :title="row.key" x-text="row.label"></td>
                                            <td class="py-1.5 pe-3 text-red-700 break-all" dir="auto" x-text="row.current_display ? row.current_display + ' (' + row.current + ')' : (row.current ?? '-')"></td>
                                            <td class="py-1.5 text-green-700 break-all" dir="auto" x-text="row.restored_display ? row.restored_display + ' (' + row.restored + ')' : (row.restored ?? '-')"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </div>
                <div class="px-5 py-3 border-t border-gray-200 flex items-center justify-end gap-2">
                    <button type="button" @click="open = false" class="px-4 py-2 text-sm font-medium rounded-md bg-gray-200 text-gray-700 hover:bg-gray-300">{{ __('activitylog-browse::messages.cancel') }}</button>
                    <form method="POST" :action="action">
                        @csrf
                        <button type="submit" :disabled="loading || failed || rows.length === 0"
                                class="px-4 py-2 text-sm font-medium rounded-md bg-orange-600 text-white hover:bg-orange-700 disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ __('activitylog-browse::messages.restore_confirm') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
