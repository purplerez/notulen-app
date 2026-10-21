<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-900 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">

    <title>@yield('title', 'Platform Super Admin') - Notulen App</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#4f46e5">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="/icons/icon.svg">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-950 text-slate-100 flex flex-col font-sans">
    <!-- Platform Navigation Bar -->
    <header class="border-b border-slate-800 bg-slate-900/90 backdrop-blur-md sticky top-0 z-40">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center gap-8">
                    <a href="{{ route('platform.dashboard') }}" class="flex items-center gap-2.5 group">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-600 text-white font-black text-sm shadow-md group-hover:bg-indigo-500 transition-colors">
                            ⚡
                        </span>
                        <div>
                            <span class="text-sm font-bold tracking-tight text-white block">Platform Console</span>
                            <span class="text-[10px] text-slate-400 block -mt-0.5">Super Admin Guard</span>
                        </div>
                    </a>

                    <nav class="hidden md:flex items-center gap-1">
                        <a
                            href="{{ route('platform.dashboard') }}"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('platform.dashboard') || request()->routeIs('platform.organizations.*') ? 'bg-slate-800 text-indigo-400 font-bold' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }}"
                        >
                            🏢 Dashboard Lembaga
                        </a>
                        <a
                            href="{{ route('platform.reconciliation.index') }}"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('platform.reconciliation.*') ? 'bg-slate-800 text-indigo-400 font-bold' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }}"
                        >
                            ⚖️ Rekonsiliasi Biaya
                        </a>
                        <a
                            href="{{ route('platform.rates.index') }}"
                            class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors {{ request()->routeIs('platform.rates.*') ? 'bg-slate-800 text-indigo-400 font-bold' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }}"
                        >
                            💲 Tarif & Kurs
                        </a>
                    </nav>
                </div>

                <div class="flex items-center gap-4">
                    <div class="text-right hidden sm:block">
                        <span class="text-xs font-semibold text-slate-200 block">{{ auth('platform')->user()?->name ?? 'Super Admin' }}</span>
                        <span class="text-[10px] text-emerald-400 flex items-center justify-end gap-1">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> 2FA Terverifikasi
                        </span>
                    </div>

                    <form method="POST" action="{{ route('platform.logout') }}">
                        @csrf
                        <button
                            type="submit"
                            class="rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-rose-950/40 hover:border-rose-800 hover:text-rose-300 transition-all shadow-xs"
                            title="Keluar dari Platform"
                        >
                            Keluar
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content Body -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <!-- Flash Messages -->
        @if (session('success'))
            <div class="rounded-xl border border-emerald-800/60 bg-emerald-950/40 p-4 text-xs font-medium text-emerald-300 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-base">✓</span>
                    <span>{{ session('success') }}</span>
                </div>
                <button type="button" onclick="this.parentElement.remove()" class="text-emerald-400 hover:text-emerald-200">✕</button>
            </div>
        @endif

        @if (session('error') || $errors->any())
            <div class="rounded-xl border border-rose-800/60 bg-rose-950/40 p-4 text-xs font-medium text-rose-300 space-y-1">
                <div class="flex items-center gap-2 font-bold">
                    <span class="text-base">⚠️</span>
                    <span>{{ session('error') ?? 'Terjadi kesalahan pada input:' }}</span>
                </div>
                @if ($errors->any())
                    <ul class="list-disc list-inside pl-5 space-y-0.5 text-rose-300/90 text-[11px]">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Platform Footer -->
    <footer class="border-t border-slate-900 bg-slate-950 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-4">
            <p>Notulen App • Platform Super Admin Console • Akses terisolasi & diaudit penuh.</p>
        </div>
    </footer>
</body>
</html>
