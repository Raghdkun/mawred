@php
    $locale = app()->getLocale();
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $digits = auth()->user()?->digits ?? 'latn';
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $dir }}" data-digits="{{ $digits }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#4570C0" media="(prefers-color-scheme: light)">
        <meta name="theme-color" content="#0E1626" media="(prefers-color-scheme: dark)">

        {{-- Apply the system dark preference before first paint. --}}
        <script>
            (function () {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>

        {{-- Token background colours (tokens.css --background) so there is no white flash in dark mode. --}}
        <style>
            html { background-color: #F6F8FC; }
            html.dark { background-color: #0E1626; }
        </style>

        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="icon" href="/favicon-32.png" sizes="32x32" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Mawred') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
