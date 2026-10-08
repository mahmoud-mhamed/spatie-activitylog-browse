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
    <style>
        /* Tooltip for clickable values: <a data-tip="What clicking does">. Matches the icon tooltips. */
        [data-tip] { position: relative; }
        [data-tip]:hover::after, [data-tip]:focus-visible::after {
            content: attr(data-tip);
            position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
            padding: 4px 8px; border-radius: 4px; background: #1f2937; color: #fff;
            font-size: 12px; font-weight: 400; line-height: 1rem; white-space: nowrap;
            text-decoration: none; pointer-events: none; z-index: 60;
        }
        /* Near the table edges: anchor to the element's start/end so the overflow wrapper doesn't clip it. */
        [data-tip][data-tip-align="start"]::after { left: auto; transform: none; inset-inline-start: 0; }
        [data-tip][data-tip-align="end"]::after { left: auto; transform: none; inset-inline-end: 0; }
    </style>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3/dist/cdn.min.js"></script>
    <script>
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
