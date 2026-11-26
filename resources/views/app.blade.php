<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title inertia>{{ config('app.name', 'StageTrack Enterprise') }}</title>
    <script>
        (function () {
            try {
                var t = localStorage.getItem('stagetrack.theme') || 'system';
                var dark = t === 'dark' || (t === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
                document.documentElement.classList.toggle('dark', dark);
            } catch (e) {}
        })();
    </script>
    @routes
    @vite(['resources/css/app.css', 'resources/js/app.ts'])
    @inertiaHead
</head>
<body class="h-full bg-canvas text-ink antialiased">
    @inertia
</body>
</html>
