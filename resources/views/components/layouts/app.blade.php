@props([
    'title' => null,
    'narrow' => false,
    'maxWidth' => null,
])

@php
    $pageTitle = $title ?? config('app.name', 'Notulensi Rapat');
    $user = auth()->user();
    $org = $user?->organization;

    // Determine container width class
    $containerWidth = $maxWidth ?? ($narrow ? 'max-w-xl' : 'max-w-6xl');

    // Initials for avatar
    $initials = 'U';
    if ($user && !empty($user->name)) {
        $words = preg_split('/\s+/', trim($user->name));
        $initials = mb_strtoupper(mb_substr($words[0] ?? '', 0, 1) . mb_substr($words[1] ?? '', 0, 1));
        if (empty($initials)) {
            $initials = 'U';
        }
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $pageTitle }}</title>

    <!-- PWA & Mobile Meta -->
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
<body
    x-data="{ userDropdownOpen: false, mobileNavOpen: false }"
    class="flex min-h-full flex-col text-slate-800 selection:bg-indigo-500 selection:text-white pt-safe pb-safe pl-safe pr-safe bg-slate-50 font-sans"
>
    <div class="flex min-h-screen flex-col">
        <!-- ========================================================================= -->
        <!-- TOP NAVIGATION BAR -->
        <!-- ========================================================================= -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 bg-white/95 backdrop-blur-md shadow-2xs">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Brand Logo & Tenant Info -->
                <div class="flex items-center gap-6">
                    <a href="{{ auth()->check() ? route('dashboard') : url('/') }}" class="flex items-center gap-2.5 group transition">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-700 text-white font-black text-base shadow-sm shadow-indigo-500/20 group-hover:scale-105 transition-transform">
                            N
                        </span>
                        <div class="flex flex-col">
                            <span class="text-base font-bold tracking-tight text-slate-900 group-hover:text-indigo-600 transition-colors leading-none">
                                Notulen<span class="text-indigo-600">App</span>
                            </span>
                            @if($org)
                                <span class="text-[11px] font-medium text-slate-500 truncate max-w-[150px] sm:max-w-[220px] mt-0.5" title="{{ $org->name }}">
                                    {{ $org->name }}
                                </span>
                            @else
                                <span class="text-[10px] font-medium text-slate-400 mt-0.5">Sistem Notulensi Rapat</span>
                            @endif
                        </div>
                    </a>

                    <!-- Desktop Nav Links (For Authenticated Tenant Users) -->
                    @auth
                        <nav class="hidden lg:flex items-center gap-1 pl-2">
                            <a
                                href="{{ route('dashboard') }}"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70' }}"
                            >
                                <span>📊</span>
                                <span>Dashboard</span>
                            </a>

                            <a
                                href="{{ route('meetings.index') }}"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('meetings.*') ? 'bg-indigo-50 text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70' }}"
                            >
                                <span>📅</span>
                                <span>Rapat</span>
                            </a>

                            @can('viewAny', App\Models\QuotaOrder::class)
                                <a
                                    href="{{ route('quota.index') }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('quota.*') ? 'bg-indigo-50 text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70' }}"
                                >
                                    <span>⏱️</span>
                                    <span>Kuota</span>
                                    @if($org && $org->availableQuotaHours() > 0)
                                        <span class="ml-1 rounded-full bg-emerald-100 px-1.5 py-0.2 text-[10px] font-bold text-emerald-800">
                                            {{ $org->availableQuotaHours() }}h
                                        </span>
                                    @endif
                                </a>
                            @endcan

                            @can('create', App\Models\User::class)
                                <a
                                    href="{{ route('users.index') }}"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('users.*') ? 'bg-indigo-50 text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70' }}"
                                >
                                    <span>👥</span>
                                    <span>Pengguna</span>
                                </a>
                            @endcan

                            <a
                                href="{{ route('audit_logs.index') }}"
                                class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-semibold transition {{ request()->routeIs('audit_logs.*') ? 'bg-indigo-50 text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70' }}"
                            >
                                <span>📜</span>
                                <span>Log Akses</span>
                            </a>
                        </nav>
                    @endauth
                </div>

                <!-- Right Side Actions & User Profile -->
                <div class="flex items-center gap-2 sm:gap-3">
                    @isset($headerActions)
                        <div class="flex items-center gap-2">
                            {{ $headerActions }}
                        </div>
                    @endisset

                    @auth
                        <!-- User Profile Dropdown (Desktop & Mobile) -->
                        <div class="relative">
                            <button
                                type="button"
                                @click="userDropdownOpen = !userDropdownOpen"
                                class="flex items-center gap-2 rounded-xl p-1.5 text-slate-700 hover:bg-slate-100 transition cursor-pointer select-none ring-1 ring-slate-200/60"
                                :class="{ 'bg-slate-100 ring-indigo-500/30': userDropdownOpen }"
                                title="Menu Pengguna"
                                aria-label="Menu Pengguna"
                            >
                                <!-- Avatar Initials -->
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-tr from-indigo-600 to-violet-600 text-[11px] font-bold text-white shadow-xs">
                                    {{ $initials }}
                                </span>

                                <div class="hidden md:flex flex-col text-left">
                                    <span class="text-xs font-bold text-slate-900 leading-tight truncate max-w-[120px]">
                                        {{ $user->name }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 capitalize leading-none mt-0.5">
                                        {{ $user->role }}
                                    </span>
                                </div>

                                <svg class="h-4 w-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': userDropdownOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>

                            <!-- Dropdown Panel -->
                            <div
                                x-show="userDropdownOpen"
                                @click.outside="userDropdownOpen = false"
                                x-cloak
                                x-transition:enter="transition ease-out duration-150"
                                x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                x-transition:leave="transition ease-in duration-100"
                                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                                class="absolute right-0 top-full mt-2 w-64 origin-top-right rounded-2xl border border-slate-200/90 bg-white p-2 shadow-xl ring-1 ring-black/5 z-50 text-xs"
                            >
                                <!-- User Header Box -->
                                <div class="p-3 bg-slate-50 rounded-xl space-y-1 mb-1">
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-bold text-slate-900 text-sm truncate">{{ $user->name }}</span>
                                        <span class="shrink-0 inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase
                                            @if($user->role === 'admin') bg-purple-100 text-purple-700
                                            @elseif($user->role === 'pimpinan') bg-amber-100 text-amber-800
                                            @else bg-blue-100 text-blue-700 @endif">
                                            {{ $user->role }}
                                        </span>
                                    </div>
                                    <span class="block text-[11px] text-slate-500 truncate">{{ $user->email }}</span>
                                    @if($org)
                                        <span class="block text-[10px] text-slate-400 truncate pt-1 border-t border-slate-200/60 mt-1">
                                            🏢 {{ $org->name }}
                                        </span>
                                    @endif
                                </div>

                                <!-- Links -->
                                <div class="space-y-0.5">
                                    <a
                                        href="{{ route('dashboard') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-100 hover:text-indigo-600 transition font-medium"
                                    >
                                        <span class="text-sm">📊</span>
                                        <span>Dashboard</span>
                                    </a>

                                    <a
                                        href="{{ route('meetings.index') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-100 hover:text-indigo-600 transition font-medium"
                                    >
                                        <span class="text-sm">📅</span>
                                        <span>Daftar Rapat</span>
                                    </a>

                                    @if($org && $user->can('update', $org))
                                        <a
                                            href="{{ route('organization.kop.show') }}"
                                            class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-100 hover:text-indigo-600 transition font-medium"
                                        >
                                            <span class="text-sm">🏢</span>
                                            <span>Atur Kop Surat</span>
                                        </a>
                                    @endif

                                    <a
                                        href="{{ route('password.edit') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-100 hover:text-indigo-600 transition font-medium"
                                    >
                                        <span class="text-sm">🔑</span>
                                        <span>Ubah Password</span>
                                    </a>

                                    <a
                                        href="{{ route('audit_logs.index') }}"
                                        class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-slate-700 hover:bg-slate-100 hover:text-indigo-600 transition font-medium"
                                    >
                                        <span class="text-sm">📜</span>
                                        <span>Log Akses</span>
                                    </a>
                                </div>

                                <div class="border-t border-slate-100 my-1"></div>

                                <!-- Logout -->
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button
                                        type="submit"
                                        class="flex w-full items-center gap-2.5 px-3 py-2 rounded-xl text-rose-600 hover:bg-rose-50 hover:text-rose-700 transition font-semibold cursor-pointer"
                                    >
                                        <span class="text-sm">🚪</span>
                                        <span>Keluar</span>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Mobile Hamburger Button (Below lg) -->
                        <button
                            type="button"
                            @click="mobileNavOpen = !mobileNavOpen"
                            class="lg:hidden inline-flex items-center justify-center rounded-xl p-2 text-slate-600 hover:bg-slate-100 transition"
                            aria-label="Toggle Navigation"
                        >
                            <svg x-show="!mobileNavOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            </svg>
                            <svg x-show="mobileNavOpen" x-cloak class="h-6 w-6 text-slate-800" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    @else
                        <!-- Guest State -->
                        @if(!request()->routeIs('login'))
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-indigo-700 transition"
                            >
                                <span>Masuk</span>
                            </a>
                        @endif
                    @endauth
                </div>
            </div>

            <!-- Mobile Navigation Drawer -->
            @auth
                <div
                    x-show="mobileNavOpen"
                    x-cloak
                    @click.outside="mobileNavOpen = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 translate-y-0"
                    x-transition:leave-end="opacity-0 -translate-y-2"
                    class="lg:hidden border-t border-slate-200 bg-white/98 backdrop-blur-md px-4 py-3 space-y-1 text-xs shadow-md"
                >
                    <a
                        href="{{ route('dashboard') }}"
                        class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl font-semibold transition {{ request()->routeIs('dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-100' }}"
                    >
                        <span>📊</span>
                        <span>Dashboard</span>
                    </a>

                    <a
                        href="{{ route('meetings.index') }}"
                        class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl font-semibold transition {{ request()->routeIs('meetings.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-100' }}"
                    >
                        <span>📅</span>
                        <span>Daftar Rapat</span>
                    </a>

                    @can('viewAny', App\Models\QuotaOrder::class)
                        <a
                            href="{{ route('quota.index') }}"
                            class="flex items-center justify-between px-3 py-2.5 rounded-xl font-semibold transition {{ request()->routeIs('quota.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-100' }}"
                        >
                            <span class="flex items-center gap-2.5">
                                <span>⏱️</span>
                                <span>Beli / Kuota Rekam</span>
                            </span>
                            @if($org)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                    {{ $org->availableQuotaHours() }} Jam
                                </span>
                            @endif
                        </a>
                    @endcan

                    @can('create', App\Models\User::class)
                        <a
                            href="{{ route('users.index') }}"
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl font-semibold transition {{ request()->routeIs('users.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-100' }}"
                        >
                            <span>👥</span>
                            <span>Manajemen Pengguna</span>
                        </a>
                    @endcan

                    <a
                        href="{{ route('audit_logs.index') }}"
                        class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl font-semibold transition {{ request()->routeIs('audit_logs.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-100' }}"
                    >
                        <span>📜</span>
                        <span>Log Akses</span>
                    </a>

                    @if($org && $user->can('update', $org))
                        <a
                            href="{{ route('organization.kop.show') }}"
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl font-semibold transition {{ request()->routeIs('organization.kop.*') ? 'bg-indigo-50 text-indigo-700' : 'text-slate-700 hover:bg-slate-100' }}"
                        >
                            <span>🏢</span>
                            <span>Atur Kop Surat</span>
                        </a>
                    @endif

                    <div class="border-t border-slate-100 my-1 pt-1">
                        <a
                            href="{{ route('password.edit') }}"
                            class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl font-semibold text-slate-700 hover:bg-slate-100"
                        >
                            <span>🔑</span>
                            <span>Ubah Password</span>
                        </a>

                        <form method="POST" action="{{ route('logout') }}" class="pt-1">
                            @csrf
                            <button
                                type="submit"
                                class="flex w-full items-center gap-2.5 px-3 py-2.5 rounded-xl font-semibold text-rose-600 hover:bg-rose-50 cursor-pointer"
                            >
                                <span>🚪</span>
                                <span>Keluar dari Akun</span>
                            </button>
                        </form>
                    </div>
                </div>
            @endauth
        </header>

        <!-- ========================================================================= -->
        <!-- MAIN CONTENT CONTAINER -->
        <!-- ========================================================================= -->
        <main class="flex-1">
            <div class="mx-auto px-4 py-6 sm:px-6 lg:px-8 {{ $containerWidth }}">
                {{ $slot }}
            </div>
        </main>

        <!-- ========================================================================= -->
        <!-- BOTTOM STICKY ACTION BAR FOR MOBILE -->
        <!-- ========================================================================= -->
        @isset($bottomBar)
            <div class="sticky bottom-0 z-40 border-t border-slate-200 bg-white/95 p-3 backdrop-blur-md pb-safe shadow-lg">
                <div class="mx-auto {{ $containerWidth }}">
                    {{ $bottomBar }}
                </div>
            </div>
        @endisset
    </div>
</body>
</html>
