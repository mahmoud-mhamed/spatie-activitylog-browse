@php
    $alpine = $alpine ?? null;
    $loading = $loading ?? null;
    $size = $size ?? 'text-2xl';
@endphp

<div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
    <div class="text-xs font-medium text-gray-500 uppercase tracking-wide">{{ $label }}</div>
    @if($loading)
        <div class="mt-1 h-7 w-16 bg-gray-100 rounded animate-pulse" x-show="{{ $loading }}"></div>
    @endif
    @if($alpine)
        <div class="mt-1 {{ $size }} font-bold text-gray-900" @if($loading) x-show="!({{ $loading }})" @endif x-text="{{ $alpine }}"></div>
    @else
        <div class="mt-1 {{ $size }} font-bold text-gray-900">{!! $value ?? '—' !!}</div>
    @endif
</div>
