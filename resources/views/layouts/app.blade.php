<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Notulensi Rapat') }}</title>

    <!-- PWA Minimal -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Notulensi">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Scripts & Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col text-slate-800 selection:bg-indigo-500 selection:text-white pt-safe pb-safe pl-safe pr-safe">
    <div class="flex min-h-screen flex-col">
        <!-- Top Navigation Bar (Mobile-first) -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 bg-white/95 backdrop-blur backdrop-filter">
            <div class="mx-auto flex h-16 max-w-lg items-center justify-between px-4 sm:max-w-2xl sm:px-6">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold shadow-sm">
                        N
                    </span>
                    <span class="text-base font-semibold tracking-tight text-slate-900">
                        {{ config('app.name', 'Notulensi Rapat') }}
                    </span>
                </div>
                @isset($headerActions)
                    <div class="flex items-center gap-2">
                        {{ $headerActions }}
                    </div>
                @endisset
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1">
            <div class="mx-auto max-w-lg px-4 py-6 sm:max-w-2xl sm:px-6">
                {{ $slot }}
            </div>
        </main>

        <!-- Bottom Navigation / Action Bar for Mobile -->
        @isset($bottomBar)
            <div class="sticky bottom-0 z-40 border-t border-slate-200 bg-white/95 p-3 backdrop-blur pb-safe">
                <div class="mx-auto max-w-lg sm:max-w-2xl">
                    {{ $bottomBar }}
                </div>
            </div>
        @endisset
    </div>
</body>
</html>
