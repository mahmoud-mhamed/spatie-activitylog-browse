<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('activitylog-browse::messages.login_title') }}</title>
    <script>
        (function () {
            var t = localStorage.getItem('activitylog-browse-theme');
            if (!t) t = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { darkMode: 'class' };
    </script>
    @include('activitylog-browse::partials.dark-mode-styles')
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md">
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-lg p-6 sm:p-8">
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 dark:bg-blue-900 mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 dark:text-blue-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 11c0-1.105.895-2 2-2s2 .895 2 2v3M5 11V8a7 7 0 1114 0v3m1 0H4a1 1 0 00-1 1v8a1 1 0 001 1h16a1 1 0 001-1v-8a1 1 0 00-1-1z"/>
                </svg>
            </div>
            <h1 class="text-xl font-bold">{{ __('activitylog-browse::messages.login_title') }}</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('activitylog-browse::messages.login_hint') }}
            </p>
        </div>

        <form method="POST" action="{{ route('activitylog-browse.login.submit') }}" class="space-y-4">
            @csrf
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                    {{ __('activitylog-browse::messages.login_password') }}
                </label>
                <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                       class="w-full rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 px-3 py-2 text-sm shadow-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500">
                @error('password')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <button type="submit"
                    class="w-full inline-flex justify-center items-center gap-2 rounded-md bg-blue-600 hover:bg-blue-700 px-4 py-2 text-sm font-medium text-white shadow-sm focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                {{ __('activitylog-browse::messages.login_submit') }}
            </button>
        </form>
    </div>

    <div class="mt-4 flex items-center justify-center gap-3 text-xs text-gray-500 dark:text-gray-400">
        <button type="button" onclick="window.__toggleTheme && window.__toggleTheme()" class="inline-flex items-center gap-1 hover:text-blue-600">
            <svg id="theme-icon-sun" class="h-4 w-4 hidden dark:inline" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 1v2m0 18v2m11-11h-2M3 12H1m16.95-6.95l-1.414 1.414M6.464 17.536l-1.414 1.414m12.728 0l-1.414-1.414M6.464 6.464L5.05 5.05"/></svg>
            <svg id="theme-icon-moon" class="h-4 w-4 dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/></svg>
            <span class="dark:hidden">{{ __('activitylog-browse::messages.theme_dark') }}</span>
            <span class="hidden dark:inline">{{ __('activitylog-browse::messages.theme_light') }}</span>
        </button>
    </div>
</div>

<script>
    window.__toggleTheme = function () {
        var html = document.documentElement;
        var isDark = html.classList.toggle('dark');
        localStorage.setItem('activitylog-browse-theme', isDark ? 'dark' : 'light');
    };
</script>
</body>
</html>
