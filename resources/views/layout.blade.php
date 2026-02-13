<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('activitylog-browse::messages.activity_log'))</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-900 min-h-screen">
    <div class="w-full px-4 sm:px-6 lg:px-8 py-8">
        <header class="mb-8 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-900">
                <a href="{{ route('activitylog-browse.index') }}" class="hover:text-blue-600">{{ __('activitylog-browse::messages.activity_log') }}</a>
            </h1>
            @php
                $currentLocale = app()->getLocale();
                $switchLocale = $currentLocale === 'ar' ? 'en' : 'ar';
                $switchLabel = $currentLocale === 'ar' ? 'English' : 'العربية';
            @endphp
            <a href="{{ route('activitylog-browse.switch-lang', $switchLocale) }}"
               class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md shadow-sm hover:bg-gray-50">
                {{ $switchLabel }}
            </a>
        </header>

        <main>
            @yield('content')
        </main>
    </div>
</body>
</html>
