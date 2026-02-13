@php
    $__langKey = "validation.attributes.{$key}";
    $__translated = __($__langKey);
    $__hasTranslation = $__translated !== $__langKey;
@endphp
@if($__hasTranslation)
    <span>{{ $__translated }}</span> <span class="text-gray-400 font-normal">({{ $key }})</span>
@else
    {{ \Illuminate\Support\Str::headline($key) }} <span class="text-gray-400 font-normal font-mono text-xs">{{ $key !== \Illuminate\Support\Str::headline($key) ? $key : '' }}</span>
@endif