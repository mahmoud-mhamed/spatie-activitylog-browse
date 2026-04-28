<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', __('activitylog-browse::messages.activity_log'))</title>
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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <script>
        var __attrTranslations = @json(is_array(__('validation.attributes')) ? __('validation.attributes') : []);
        function translateAttribute(key) {
            if (__attrTranslations[key]) return __attrTranslations[key] + ' (' + key + ')';
            var headline = key.replace(/_/g, ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
            return headline !== key ? headline + ' (' + key + ')' : key;
        }
        window.__toggleTheme = function () {
            var html = document.documentElement;
            var isDark = html.classList.toggle('dark');
            localStorage.setItem('activitylog-browse-theme', isDark ? 'dark' : 'light');
        };
    </script>
</head>
<body class="bg-gray-50 dark:bg-gray-900 text-gray-900 dark:text-gray-100 min-h-screen">
    @include('activitylog-browse::partials.nav')
    <div class="w-full px-4 sm:px-6 lg:px-8 py-6">
        <main>
            @yield('content')
        </main>
    </div>
</body>
</html>
