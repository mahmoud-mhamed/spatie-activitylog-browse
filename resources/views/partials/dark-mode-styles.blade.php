<style>
    /* Dark mode global overrides for views that don't explicitly use dark: variants. */
    html.dark { color-scheme: dark; }
    html.dark body { background-color: #0b1220; color: #e5e7eb; }

    /* Backgrounds */
    html.dark .bg-white { background-color: #111827 !important; }
    html.dark .bg-gray-50 { background-color: #0b1220 !important; }
    html.dark .bg-gray-100 { background-color: #1f2937 !important; }
    html.dark .bg-gray-200 { background-color: #374151 !important; }

    /* Borders */
    html.dark *, html.dark ::before, html.dark ::after { border-color: rgba(75,85,99,0.35); }
    html.dark .border-gray-100 { border-color: rgba(31,41,55,0.6) !important; }
    html.dark .border-gray-200 { border-color: rgba(55,65,81,0.5) !important; }
    html.dark .border-gray-300 { border-color: rgba(75,85,99,0.55) !important; }
    html.dark .divide-gray-100 > * + * { border-color: rgba(31,41,55,0.6) !important; }
    html.dark .divide-gray-200 > * + * { border-color: rgba(55,65,81,0.5) !important; }

    /* Text */
    html.dark .text-gray-900 { color: #f3f4f6 !important; }
    html.dark .text-gray-800 { color: #e5e7eb !important; }
    html.dark .text-gray-700 { color: #d1d5db !important; }
    html.dark .text-gray-600 { color: #9ca3af !important; }
    html.dark .text-gray-500 { color: #9ca3af !important; }
    html.dark .text-gray-400 { color: #6b7280 !important; }

    /* Hover backgrounds in tables / lists */
    html.dark .hover\:bg-gray-50:hover { background-color: #1f2937 !important; }
    html.dark .hover\:bg-gray-100:hover { background-color: #374151 !important; }

    /* Soft tinted backgrounds (used for status pills / cards) — make readable on dark */
    html.dark .bg-blue-50 { background-color: rgba(37,99,235,0.10) !important; }
    html.dark .bg-blue-100 { background-color: rgba(37,99,235,0.20) !important; }
    html.dark .bg-green-50 { background-color: rgba(22,163,74,0.08) !important; }
    html.dark .bg-green-100 { background-color: rgba(22,163,74,0.18) !important; }
    html.dark .bg-red-50 { background-color: rgba(220,38,38,0.10) !important; }
    html.dark .bg-red-100 { background-color: rgba(220,38,38,0.20) !important; }
    html.dark .bg-amber-50 { background-color: rgba(217,119,6,0.10) !important; }
    html.dark .bg-amber-100 { background-color: rgba(217,119,6,0.20) !important; }
    html.dark .bg-purple-50 { background-color: rgba(147,51,234,0.10) !important; }
    html.dark .bg-purple-100 { background-color: rgba(147,51,234,0.20) !important; }

    /* Soft tinted borders — soften vivid Tailwind 200/300 shades on dark backgrounds */
    html.dark .border-blue-200 { border-color: rgba(37,99,235,0.25) !important; }
    html.dark .border-blue-300 { border-color: rgba(37,99,235,0.35) !important; }
    html.dark .border-green-200 { border-color: rgba(22,163,74,0.25) !important; }
    html.dark .border-green-300 { border-color: rgba(22,163,74,0.35) !important; }
    html.dark .border-red-200 { border-color: rgba(220,38,38,0.25) !important; }
    html.dark .border-red-300 { border-color: rgba(220,38,38,0.35) !important; }
    html.dark .border-amber-200 { border-color: rgba(217,119,6,0.25) !important; }
    html.dark .border-amber-300 { border-color: rgba(217,119,6,0.35) !important; }
    html.dark .border-purple-200 { border-color: rgba(147,51,234,0.25) !important; }
    html.dark .border-purple-300 { border-color: rgba(147,51,234,0.35) !important; }

    /* Colored text on tinted backgrounds — lighten so it stays readable on dark */
    html.dark .text-blue-600,
    html.dark .text-blue-700,
    html.dark .text-blue-800,
    html.dark .text-blue-900 { color: #bfdbfe !important; }
    html.dark .text-green-600,
    html.dark .text-green-700,
    html.dark .text-green-800,
    html.dark .text-green-900 { color: #bbf7d0 !important; }
    html.dark .text-red-600,
    html.dark .text-red-700,
    html.dark .text-red-800,
    html.dark .text-red-900 { color: #fecaca !important; }
    html.dark .text-amber-600,
    html.dark .text-amber-700,
    html.dark .text-amber-800,
    html.dark .text-amber-900 { color: #fde68a !important; }
    html.dark .text-purple-600,
    html.dark .text-purple-700,
    html.dark .text-purple-800,
    html.dark .text-purple-900 { color: #ddd6fe !important; }
    html.dark .text-emerald-600,
    html.dark .text-emerald-700,
    html.dark .text-emerald-800 { color: #a7f3d0 !important; }
    html.dark .text-indigo-600,
    html.dark .text-indigo-700,
    html.dark .text-indigo-800 { color: #c7d2fe !important; }

    /* Inputs */
    html.dark input, html.dark select, html.dark textarea {
        background-color: #111827 !important;
        color: #f3f4f6 !important;
        border-color: #4b5563 !important;
    }
    html.dark input::placeholder, html.dark textarea::placeholder { color: #6b7280 !important; }

    /* Code-like & monospace blocks */
    html.dark code, html.dark pre { color: #e5e7eb; }

    /* Modal/overlay backgrounds */
    html.dark .bg-black\/50 { background-color: rgba(0,0,0,0.7) !important; }
</style>
