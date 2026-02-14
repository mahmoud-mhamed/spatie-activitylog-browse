<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('activitylog-browse::messages.activity_log'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <script>
        var __attrTranslations = @json(is_array(__('validation.attributes')) ? __('validation.attributes') : []);
        function translateAttribute(key) {
            if (__attrTranslations[key]) return __attrTranslations[key] + ' (' + key + ')';
            var headline = key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            return headline !== key ? headline + ' (' + key + ')' : key;
        }
    </script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
    <div class="w-full px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-8 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">
                <a href="{{ route('activitylog-browse.index') }}" class="hover:text-blue-600">{{ __('activitylog-browse::messages.activity_log') }}</a>
            </h1>
            <div class="flex items-center gap-2">
                <a href="{{ route('activitylog-browse.statistics') }}" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-blue-600 bg-white border border-blue-200 rounded-md shadow-sm hover:bg-blue-50 hover:border-blue-300 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                    </svg>
                    {{ __('activitylog-browse::messages.view_statistics') }}
                </a>
                @php
                    $currentLocale = app()->getLocale();
                    $switchLocale = $currentLocale === 'ar' ? 'en' : 'ar';
                    $switchLabel = $currentLocale === 'ar' ? 'English' : 'العربية';
                @endphp
                <a href="{{ route('activitylog-browse.cleanup') }}" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-600 bg-white border border-red-200 rounded-md shadow-sm hover:bg-red-50 hover:border-red-300 transition-colors">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                    {{ __('activitylog-browse::messages.cleanup') }}
                </a>
                <a href="{{ route('activitylog-browse.switch-lang', $switchLocale) }}"
                   class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                    {{ $switchLabel }}
                </a>
            </div>
        </header>

        <main>
            @yield('content')
        </main>
    </div>
</body>
</html>
