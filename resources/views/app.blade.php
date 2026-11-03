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
        <meta name="mawred-reverb" data-key="{{ config('mawred.realtime.key') }}" data-host="{{ config('mawred.realtime.host') }}" data-port="{{ config('mawred.realtime.port') }}" data-scheme="{{ config('mawred.realtime.scheme') }}">

        {{-- Apply the system dark preference before first paint. --}}
        <script nonce="{{ Vite::cspNonce() }}">
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
        <link rel="manifest" href="/build/manifest.webmanifest">

        {{-- Public pages that search engines and link previews read (careers, D87). --}}
        @isset($meta)
            <meta name="description" content="{{ $meta['description'] }}">
            <link rel="canonical" href="{{ $meta['canonical'] }}">
            @foreach ($meta['alternates'] as $lang => $href)
                <link rel="alternate" hreflang="{{ $lang }}" href="{{ $href }}">
            @endforeach
            <meta property="og:type" content="website">
            <meta property="og:title" content="{{ $meta['title'] }}">
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta property="og:url" content="{{ $meta['canonical'] }}">
            <meta property="og:image" content="{{ $meta['image'] }}">
            <meta property="og:site_name" content="{{ $meta['siteName'] }}">
            <meta property="og:locale" content="{{ $meta['locale'] }}">
            <meta name="twitter:card" content="summary_large_image">
            @if ($meta['jsonLd'])
                <script type="application/ld+json" nonce="{{ Vite::cspNonce() }}">{!! json_encode($meta['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) !!}</script>
            @endif
        @endisset

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ $meta['title'] ?? config('app.name', 'Mawred') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
