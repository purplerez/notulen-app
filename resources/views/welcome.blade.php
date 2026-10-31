<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>NotulenApp - Aplikasi Notulensi Rapat & Transkripsi AI Modern</title>
    <meta name="description" content="Aplikasi notulensi rapat cerdas untuk sekolah, lembaga pendidikan, dan instansi. Perekaman audio mobile-first anti-hilang, presensi QR instan, transkripsi AI otomatis, dan ekspor notulen berstandar dinas.">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    <!-- Script Pencegah Flash Tema (Auto Dark/Light Berdasarkan Waktu) -->
    <script>
        (function() {
            function updateTheme() {
                const preference = localStorage.getItem('theme-preference') || 'auto';
                const hour = new Date().getHours();
                // Siang: 06:00 - 17:59, Malam: 18:00 - 05:59
                const isNight = hour < 6 || hour >= 18;

                if (preference === 'dark' || (preference === 'auto' && isNight)) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            }
            updateTheme();
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    x-data="{
        themePreference: localStorage.getItem('theme-preference') || 'auto',
        currentTimeStr: '',
        isNightTime: false,
        activeTab: 'perekaman',
        mobileMenuOpen: false,

        init() {
            this.checkTime();
            setInterval(() => this.checkTime(), 30000);
            this.applyTheme();
        },

        checkTime() {
            const now = new Date();
            const h = now.getHours();
            this.isNightTime = (h < 6 || h >= 18);
            this.currentTimeStr = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
        },

        setTheme(mode) {
            this.themePreference = mode;
            localStorage.setItem('theme-preference', mode);
            this.applyTheme();
        },

        applyTheme() {
            if (this.themePreference === 'dark' || (this.themePreference === 'auto' && this.isNightTime)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    }"
    class="min-h-full bg-slate-50 text-slate-900 transition-colors duration-300 dark:bg-slate-950 dark:text-slate-100 flex flex-col font-sans selection:bg-indigo-500 selection:text-white"
>
    <!-- Background Ambient Glow & Grid -->
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden opacity-40 dark:opacity-30">
        <div class="absolute -top-[300px] left-1/2 h-[600px] w-[800px] -translate-x-1/2 rounded-full bg-gradient-to-tr from-indigo-500/30 to-violet-500/20 blur-[130px]"></div>
        <div class="absolute top-[800px] -left-[200px] h-[500px] w-[500px] rounded-full bg-emerald-500/20 blur-[120px]"></div>
    </div>

    <!-- ========================================================================= -->
    <!-- HEADER & NAVBAR -->
    <!-- ========================================================================= -->
    <header class="sticky top-0 z-50 border-b border-slate-200/80 bg-white/80 backdrop-blur-md transition-colors duration-300 dark:border-slate-800/80 dark:bg-slate-950/80">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            <!-- Brand Logo -->
            <a href="/" class="flex items-center gap-2.5 group">
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-600 to-violet-600 text-white font-bold shadow-md shadow-indigo-500/20 transition group-hover:scale-105">
                    N
                </span>
                <div class="flex flex-col">
                    <span class="text-lg font-bold tracking-tight text-slate-900 dark:text-white leading-none">
                        Notulen<span class="text-indigo-600 dark:text-indigo-400">App</span>
                    </span>
                    <span class="text-[10px] font-medium text-slate-500 dark:text-slate-400">Sistem Notulensi Rapat AI</span>
                </div>
            </a>

            <!-- Desktop Nav Links -->
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-slate-600 dark:text-slate-300">
                <a href="#fitur" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Fitur Unggulan</a>
                <a href="#alur-kerja" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Cara Kerja</a>
                <a href="#keamanan" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Privasi & Keamanan</a>
                <a href="#faq" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Tanya Jawab</a>
            </nav>

            <!-- Actions: Auto Time Dark/Light Switcher & Login CTA -->
            <div class="flex items-center gap-3">
                <!-- Theme Switcher Pill -->
                <div class="relative inline-flex items-center rounded-full bg-slate-100 p-1 border border-slate-200 dark:bg-slate-900 dark:border-slate-800 text-xs">
                    <button
                        type="button"
                        @click="setTheme('auto')"
                        :class="themePreference === 'auto' ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800 dark:text-indigo-300 font-semibold' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="flex items-center gap-1.5 rounded-full px-2.5 py-1 transition"
                        title="Mode Waktu Otomatis (Siang: Terang, Malam: Gelap)"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="hidden sm:inline">Auto</span>
                        <span class="inline sm:hidden" x-text="isNightTime ? '🌙' : '☀️'"></span>
                    </button>

                    <button
                        type="button"
                        @click="setTheme('light')"
                        :class="themePreference === 'light' ? 'bg-white text-amber-600 shadow-sm dark:bg-slate-800 dark:text-amber-400 font-semibold' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="rounded-full p-1.5 transition"
                        title="Selalu Mode Terang"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" />
                        </svg>
                    </button>

                    <button
                        type="button"
                        @click="setTheme('dark')"
                        :class="themePreference === 'dark' ? 'bg-white text-indigo-600 shadow-sm dark:bg-slate-800 dark:text-indigo-300 font-semibold' : 'text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200'"
                        class="rounded-full p-1.5 transition"
                        title="Selalu Mode Gelap"
                    >
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 009.002-5.998z" />
                        </svg>
                    </button>
                </div>

                <!-- Auth Button -->
                @auth
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    >
                        Dashboard
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    >
                        Masuk Akun
                    </a>
                @endauth

                <!-- Mobile Menu Button -->
                <button
                    type="button"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="md:hidden inline-flex items-center justify-center p-2 rounded-xl text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white"
                >
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path x-show="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        <path x-show="mobileMenuOpen" x-cloak stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Mobile Nav Menu Dropdown -->
        <div x-show="mobileMenuOpen" x-cloak class="md:hidden border-b border-slate-200 bg-white px-4 py-4 space-y-3 dark:border-slate-800 dark:bg-slate-950">
            <a @click="mobileMenuOpen = false" href="#fitur" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Fitur Unggulan</a>
            <a @click="mobileMenuOpen = false" href="#alur-kerja" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Cara Kerja</a>
            <a @click="mobileMenuOpen = false" href="#keamanan" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Privasi & Keamanan</a>
            <a @click="mobileMenuOpen = false" href="#faq" class="block text-sm font-medium text-slate-700 dark:text-slate-200">Tanya Jawab</a>
        </div>
    </header>

    <!-- ========================================================================= -->
    <!-- HERO SECTION -->
    <!-- ========================================================================= -->
    <main class="relative z-10 flex-1">
        <section class="relative overflow-hidden pt-12 pb-20 sm:pt-20 sm:pb-28">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto space-y-6">
                    <!-- Status Badge -->
                    <div class="inline-flex items-center gap-2 rounded-full border border-indigo-200 bg-indigo-50/80 px-3.5 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm backdrop-blur dark:border-indigo-800/80 dark:bg-indigo-950/60 dark:text-indigo-300">
                        <span class="flex h-2 w-2 rounded-full bg-indigo-600 animate-ping"></span>
                        <span>Notulensi Era AI • Standar Dinas & Sekolah</span>
                        <span class="text-indigo-400 dark:text-indigo-600">•</span>
                        <span class="text-slate-600 dark:text-slate-400" x-text="'Waktu: ' + currentTimeStr + ' (' + (isNightTime ? '🌙 Mode Malam' : '☀️ Mode Siang') + ')'"></span>
                    </div>

                    <!-- Main Headline -->
                    <h1 class="text-4xl font-extrabold tracking-tight sm:text-6xl text-slate-900 dark:text-white leading-[1.15]">
                        Rekam Rapat, Transkripsi Otomatis, <span class="bg-gradient-to-r from-indigo-600 to-violet-500 bg-clip-text text-transparent">Notulen Siap Cetak</span>
                    </h1>

                    <!-- Subtitle -->
                    <p class="text-lg leading-relaxed text-slate-600 dark:text-slate-300 sm:text-xl">
                        Aplikasi perekaman rapat tatap muka <strong>mobile-first</strong> tanpa takut audio hilang. Dilengkapi presensi QR instan, transkripsi otomatis kecerdasan buatan, dan tanda tangan digital berstandar resmi organisasi.
                    </p>

                    <!-- CTA Buttons -->
                    <div class="pt-4 flex flex-col sm:flex-row items-center justify-center gap-4">
                        @auth
                            <a
                                href="{{ route('dashboard') }}"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-7 py-3.5 text-base font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-500 hover:shadow-indigo-600/40 transition active:scale-95"
                            >
                                <span>Buka Dashboard Notulen</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        @else
                            <a
                                href="{{ route('login') }}"
                                class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-7 py-3.5 text-base font-semibold text-white shadow-lg shadow-indigo-600/30 hover:bg-indigo-500 hover:shadow-indigo-600/40 transition active:scale-95"
                            >
                                <span>Mulai Masuk Aplikasi</span>
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
                                </svg>
                            </a>
                        @endauth

                        <a
                            href="#fitur"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-7 py-3.5 text-base font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 transition"
                        >
                            <span>Eksplorasi Fitur</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- INTERACTIVE PRODUCT MOCKUP / STUDIO PREVIEW -->
                <!-- ========================================================================= -->
                <div class="mt-14 max-w-4xl mx-auto rounded-3xl border border-slate-200/90 bg-white/70 p-3 shadow-2xl backdrop-blur-xl dark:border-slate-800 dark:bg-slate-900/80">
                    <div class="rounded-2xl border border-slate-200/80 bg-slate-900 p-6 text-white dark:border-slate-800">
                        <!-- Top status bar mockup -->
                        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800 pb-4">
                            <div class="flex items-center gap-3">
                                <span class="h-3 w-3 rounded-full bg-rose-500 animate-pulse"></span>
                                <span class="text-sm font-semibold tracking-wide text-slate-200 uppercase">Rapat Dewan Guru & Kurikulum</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs text-slate-400">
                                <span class="rounded-md bg-slate-800 px-2 py-1 text-emerald-400 font-mono">01:14:28</span>
                                <span class="rounded-md bg-emerald-500/10 text-emerald-300 px-2 py-1 border border-emerald-500/30">Auto-Chunking Aktif</span>
                            </div>
                        </div>

                        <!-- Live Waveform & Marker Buttons Simulation -->
                        <div class="py-6 space-y-6">
                            <!-- Visualizer Mockup -->
                            <div class="flex items-center justify-center gap-1.5 h-12 px-4">
                                <div class="w-1.5 h-4 bg-indigo-500 rounded-full animate-bounce"></div>
                                <div class="w-1.5 h-8 bg-indigo-400 rounded-full animate-pulse"></div>
                                <div class="w-1.5 h-11 bg-indigo-500 rounded-full animate-bounce"></div>
                                <div class="w-1.5 h-6 bg-violet-400 rounded-full animate-pulse"></div>
                                <div class="w-1.5 h-10 bg-indigo-500 rounded-full animate-bounce"></div>
                                <div class="w-1.5 h-7 bg-violet-400 rounded-full animate-pulse"></div>
                                <div class="w-1.5 h-12 bg-indigo-500 rounded-full animate-bounce"></div>
                                <div class="w-1.5 h-5 bg-indigo-400 rounded-full animate-pulse"></div>
                                <div class="w-1.5 h-9 bg-indigo-500 rounded-full animate-bounce"></div>
                                <div class="w-1.5 h-3 bg-violet-400 rounded-full animate-pulse"></div>
                            </div>

                            <!-- Live Markers Trigger Mockup -->
                            <div class="grid grid-cols-3 gap-3">
                                <div class="rounded-xl bg-slate-800/80 p-3 border border-slate-700/60 text-center">
                                    <span class="block text-xs font-semibold text-emerald-400">⚡ Keputusan Rapat</span>
                                    <span class="text-[11px] text-slate-400">3 Catatan Disimpan</span>
                                </div>
                                <div class="rounded-xl bg-slate-800/80 p-3 border border-slate-700/60 text-center">
                                    <span class="block text-xs font-semibold text-amber-400">📌 Tugas (Action Item)</span>
                                    <span class="text-[11px] text-slate-400">5 Tugas Ber-PIC</span>
                                </div>
                                <div class="rounded-xl bg-slate-800/80 p-3 border border-slate-700/60 text-center">
                                    <span class="block text-xs font-semibold text-sky-400">🎯 Poin Penting</span>
                                    <span class="text-[11px] text-slate-400">Otomatis Terindeks</span>
                                </div>
                            </div>

                            <!-- Snippet Transkripsi Otomatis -->
                            <div class="rounded-xl bg-slate-950/70 p-4 border border-slate-800/90 text-xs font-mono text-slate-300 space-y-2">
                                <div class="flex items-center justify-between text-slate-500 text-[10px]">
                                    <span>Transkripsi Whisper AI (Bahasa Indonesia)</span>
                                    <span>Segmen #12 • Terunggah Otomatis</span>
                                </div>
                                <p class="text-slate-200">
                                    <strong class="text-indigo-400">[01:14:10] Pimpinan:</strong> "...maka disepakati ujian praktik dimulai tanggal 15 November, dipimpin oleh Pak Budi..."
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================================================= -->
                <!-- KEY METRICS BAR -->
                <!-- ========================================================================= -->
                <div class="mt-16 grid grid-cols-2 gap-4 sm:grid-cols-4 max-w-5xl mx-auto">
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                        <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">90%</span>
                        <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-400">Hemat Waktu Pengetikan</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                        <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">&lt; 3 Detik</span>
                        <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-400">Kecepatan Transkrip AI</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                        <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">100%</span>
                        <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-400">Audio Anti-Hilang (Chunking)</p>
                    </div>
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900/60">
                        <span class="text-3xl font-extrabold text-indigo-600 dark:text-indigo-400">Resmi</span>
                        <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-400">Kop & Tanda Tangan Sah</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FITUR UNGGULAN (CORE FEATURES GRID) -->
        <!-- ========================================================================= -->
        <section id="fitur" class="py-20 bg-slate-100/60 border-y border-slate-200/80 dark:bg-slate-900/30 dark:border-slate-800/80">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto space-y-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Solusi Terintegrasi</span>
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                        Fitur Unggulan yang Dirancang Khusus untuk Rapat Nyata
                    </h2>
                    <p class="text-base text-slate-600 dark:text-slate-400">
                        Dari awal persiapan ruang rapat, perekaman audio tatap muka, hingga pengesahan notulen resmi oleh kepala lembaga.
                    </p>
                </div>

                <div class="mt-16 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Fitur 1 -->
                    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-950 dark:text-indigo-400 mb-5">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Studio Rekam Audio Anti-Hilang</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Perekaman suara berbasis web mobile-first dengan teknik <em>chunking</em> 30 detik. Jika baterai ponsel habis atau koneksi terputus tiba-tiba, segmen rekaman tetap aman di server.
                        </p>
                    </div>

                    <!-- Fitur 2 -->
                    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950 dark:text-emerald-400 mb-5">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 013.75 9.375v-4.5zM3.75 14.625c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5a1.125 1.125 0 01-1.125-1.125v-4.5zM13.5 4.875c0-.621.504-1.125 1.125-1.125h4.5c.621 0 1.125.504 1.125 1.125v4.5c0 .621-.504 1.125-1.125 1.125h-4.5A1.125 1.125 0 0113.5 9.375v-4.5z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Presensi Digital QR & WhatsApp</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Cukup tampilkan kode QR di proyektor atau bagikan link. Peserta hadir langsung tercatat otomatis, lengkap dengan status tepat waktu, terlambat, izin, atau tamu dinas luar.
                        </p>
                    </div>

                    <!-- Fitur 3 -->
                    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 dark:bg-amber-950 dark:text-amber-400 mb-5">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904L9 18.75l-.813-2.846a4.5 4.5 0 00-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 003.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 003.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 00-3.09 3.09zM18.259 8.715L18 9.75l-.259-1.035a3.375 3.375 0 00-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 002.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 002.456 2.456L21.75 6l-1.035.259a3.375 3.375 0 00-2.456 2.456z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Transkripsi Whisper AI Super Cepat</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Didukung mesin Whisper AI Groq yang mengonversi ucapan bahasa Indonesia menjadi teks akurat secara instan, lengkap dengan stempel waktu detik ke detik.
                        </p>
                    </div>

                    <!-- Fitur 4 -->
                    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-950 dark:text-rose-400 mb-5">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17.593 3.322c1.1.128 1.907 1.077 1.907 2.185V21L12 17.25 4.5 21V5.507c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Penanda Momen Real-Time (Live Markers)</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Cukup sekali ketuk tombol saat rapat berjalan untuk mengabadikan momen penting: "Keputusan Rapat", "Tugas/Action Item", atau "Poin Pembahasan Khusus".
                        </p>
                    </div>

                    <!-- Fitur 5 -->
                    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-sky-50 text-sky-600 dark:bg-sky-950 dark:text-sky-400 mb-5">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Ekspor PDF Notulen Berstandar Dinas</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Format notulen otomatis tersusun rapi: Kop surat resmi organisasi, daftar peserta hadir, tabel tindak lanjut ber-PIC dan tenggat waktu, siap cetak ke PDF dalam 1 klik.
                        </p>
                    </div>

                    <!-- Fitur 6 -->
                    <div class="rounded-3xl border border-slate-200 bg-white p-7 shadow-sm transition hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                        <div class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600 dark:bg-violet-950 dark:text-violet-400 mb-5">
                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                            </svg>
                        </div>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Tanda Tangan & Pengesahan Berjenjang</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Alur validasi bertingkat: Notulis menyusun, Ketua Rapat memeriksa, dan Pimpinan (Kepala Sekolah/Direktur) mengesahkan notulen secara digital dengan status revisi terkontrol.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- CARA KERJA (WORKFLOW 3 STEPS) -->
        <!-- ========================================================================= -->
        <section id="alur-kerja" class="py-20">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="text-center max-w-3xl mx-auto space-y-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Sederhana & Efisien</span>
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                        Dari Rekaman Suara ke Notulen Sah dalam 3 Langkah
                    </h2>
                </div>

                <div class="mt-16 grid grid-cols-1 md:grid-cols-3 gap-8 relative">
                    <!-- Step 1 -->
                    <div class="relative rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-lg mb-6">1</span>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Jadwalkan & Absensi QR</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Buat agenda rapat, tentukan pimpinan dan notulis. Tampilkan QR Code di layar proyektor untuk presensi instan peserta tanpa kertas.
                        </p>
                    </div>

                    <!-- Step 2 -->
                    <div class="relative rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-lg mb-6">2</span>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Rekam & Beri Tanda Momen</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            Mulai rekam dari smartphone atau laptop. Tekan tombol 'Keputusan' atau 'Tugas' kapan pun ada kesepakatan penting yang dicapai.
                        </p>
                    </div>

                    <!-- Step 3 -->
                    <div class="relative rounded-3xl border border-slate-200/80 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-lg mb-6">3</span>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">Otomasi Notulen & Sahkan</h3>
                        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400 leading-relaxed">
                            AI merapikan transkrip dan menyusun draf notulen. Pimpinan memeriksa, menandatangani, dan tautan hasil rapat siap dibagikan aman.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- KEAMANAN & PRIVASI (SECURITY HIGHLIGHT) -->
        <!-- ========================================================================= -->
        <section id="keamanan" class="py-20 bg-slate-900 text-white dark:bg-slate-950 border-y border-slate-800">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                    <div class="space-y-6">
                        <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">Privasi & Kedaulatan Data</span>
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl text-white">
                            Standar Keamanan Tinggi Tanpa Mengorbankan Kerahasiaan Lembaga
                        </h2>
                        <p class="text-slate-300 text-base leading-relaxed">
                            Kami memahami bahwa rekaman suara dan notulensi rapat berisi data sensitif dan strategis. NotulenApp dirancang dengan prinsip <em>security & privacy by design</em>:
                        </p>

                        <div class="space-y-4 pt-2">
                            <div class="flex items-start gap-3.5">
                                <div class="rounded-lg bg-emerald-500/20 p-2 text-emerald-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-white">Pembersihan Audio Otomatis (Auto-Purge 30 Hari)</h4>
                                    <p class="text-xs text-slate-400 mt-0.5">Berkas rekaman audio mentah dihapus permanen dari server setelah 1 bulan demi menghemat ruang dan menjaga privasi.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3.5">
                                <div class="rounded-lg bg-emerald-500/20 p-2 text-emerald-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-white">Isolasi Multitenancy Ketat</h4>
                                    <p class="text-xs text-slate-400 mt-0.5">Data antar instansi terpisah total secara database level. Tidak ada risiko data bocor antar sekolah/organisasi.</p>
                                </div>
                            </div>

                            <div class="flex items-start gap-3.5">
                                <div class="rounded-lg bg-emerald-500/20 p-2 text-emerald-400">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-semibold text-white">Tautan Publik Terproteksi OTP WhatsApp</h4>
                                    <p class="text-xs text-slate-400 mt-0.5">Membagikan notulen ke publik dilengkapi gerbang verifikasi nomor telepon dan kedaluwarsa waktu otomatis.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Visual Card -->
                    <div class="rounded-3xl border border-slate-800 bg-slate-900/80 p-8 shadow-2xl relative overflow-hidden">
                        <div class="flex items-center gap-4 mb-6">
                            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                                </svg>
                            </span>
                            <div>
                                <h4 class="font-bold text-white text-base">Audit Trail Append-Only</h4>
                                <span class="text-xs text-indigo-300">Setiap akses dokumen tercatat tidak dapat diubah</span>
                            </div>
                        </div>
                        <div class="space-y-3 font-mono text-xs text-slate-300 bg-slate-950 p-4 rounded-xl border border-slate-800/80">
                            <div class="text-emerald-400">✓ [LOG] Sesi audio diunggah via HTTPS TLS 1.3</div>
                            <div class="text-slate-400">✓ [AUTH] Otorisasi role: Admin, Notulis, Pimpinan</div>
                            <div class="text-slate-400">✓ [VERIFIKASI] Tanda tangan digital tervalidasi SHA-256</div>
                            <div class="text-indigo-400">✓ [RETENSI] Jadwal pembersihan audio otomatis aktif</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FAQ SECTION -->
        <!-- ========================================================================= -->
        <section id="faq" class="py-20">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="text-center space-y-4 mb-14">
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-600 dark:text-indigo-400">Pertanyaan Populer</span>
                    <h2 class="text-3xl font-bold tracking-tight text-slate-900 dark:text-white sm:text-4xl">
                        Pertanyaan yang Sering Diajukan (FAQ)
                    </h2>
                </div>

                <div class="space-y-4" x-data="{ openFaq: 1 }">
                    <!-- FAQ 1 -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            @click="openFaq = openFaq === 1 ? null : 1"
                            class="flex w-full items-center justify-between text-left text-base font-semibold text-slate-900 dark:text-white"
                        >
                            <span>Apakah peserta rapat harus menginstal aplikasi di Play Store atau App Store?</span>
                            <span class="ml-4 text-slate-400 font-bold" x-text="openFaq === 1 ? '−' : '+'"></span>
                        </button>
                        <div x-show="openFaq === 1" x-cloak class="mt-3 text-sm text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-100 pt-3 dark:border-slate-800">
                            Tidak perlu sama sekali. NotulenApp adalah aplikasi berbasis web yang responsif (*Progressive Web App*). Peserta cukup membuka browser dari smartphone atau memindai kode QR untuk langsung mengisi presensi dan membaca notulen.
                        </div>
                    </div>

                    <!-- FAQ 2 -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            @click="openFaq = openFaq === 2 ? null : 2"
                            class="flex w-full items-center justify-between text-left text-base font-semibold text-slate-900 dark:text-white"
                        >
                            <span>Bagaimana jika koneksi internet terputus saat proses perekaman rapat berlangsung?</span>
                            <span class="ml-4 text-slate-400 font-bold" x-text="openFaq === 2 ? '−' : '+'"></span>
                        </button>
                        <div x-show="openFaq === 2" x-cloak class="mt-3 text-sm text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-100 pt-3 dark:border-slate-800">
                            Sistem studio kami memotong rekaman audio ke dalam segmen-segmen kecil (30 detik). Jika koneksi internet terputus, rekaman tetap berjalan di penyimpanan sementara browser perangkat Anda dan otomatis diunggah kembali saat koneksi pulih.
                        </div>
                    </div>

                    <!-- FAQ 3 -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            @click="openFaq = openFaq === 3 ? null : 3"
                            class="flex w-full items-center justify-between text-left text-base font-semibold text-slate-900 dark:text-white"
                        >
                            <span>Apakah notulen yang dihasilkan sudah memenuhi standar dinas / resmi?</span>
                            <span class="ml-4 text-slate-400 font-bold" x-text="openFaq === 3 ? '−' : '+'"></span>
                        </button>
                        <div x-show="openFaq === 3" x-cloak class="mt-3 text-sm text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-100 pt-3 dark:border-slate-800">
                            Ya, format ekspor PDF telah dirancang mengikuti tata naskah dinas resmi: menyertakan Kop Surat berlogo lembaga, hari/tanggal, waktu, daftar hadir lengkap, susunan acara, hasil kesimpulan, serta kolom tanda tangan digital berjenjang (Notulis, Ketua Rapat, dan Mengetahui Pimpinan).
                        </div>
                    </div>

                    <!-- FAQ 4 -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <button
                            type="button"
                            @click="openFaq = openFaq === 4 ? null : 4"
                            class="flex w-full items-center justify-between text-left text-base font-semibold text-slate-900 dark:text-white"
                        >
                            <span>Bagaimana jika kuota rekam habis di tengah-tengah rapat yang belum selesai?</span>
                            <span class="ml-4 text-slate-400 font-bold" x-text="openFaq === 4 ? '−' : '+'"></span>
                        </button>
                        <div x-show="openFaq === 4" x-cloak class="mt-3 text-sm text-slate-600 dark:text-slate-400 leading-relaxed border-t border-slate-100 pt-3 dark:border-slate-800">
                            NotulenApp memiliki perlindungan anti-pemotongan paksa (*anti-hard-cut*). Anda diberikan masa tenggang ekstra (*grace period* 15 menit) dan notifikasi peringatan di layar sehingga admin dapat menambah kuota tanpa mengganggu jalannya rapat yang sedang direkam.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================================================= -->
        <!-- FINAL CTA BANNER -->
        <!-- ========================================================================= -->
        <section class="py-16">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-3xl bg-gradient-to-br from-indigo-600 via-indigo-700 to-violet-800 p-8 sm:p-14 text-center text-white shadow-2xl relative overflow-hidden">
                    <div class="max-w-2xl mx-auto space-y-6 relative z-10">
                        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl text-white">
                            Siap Mengubah Rapat Lembaga Anda Menjadi Jauh Lebih Efisien?
                        </h2>
                        <p class="text-indigo-100 text-base leading-relaxed">
                            Tinggalkan pengetikan manual berjam-jam. Mulai gunakan NotulenApp untuk pencatatan rapat yang akurat, cepat, dan profesional.
                        </p>
                        <div class="pt-2">
                            <a
                                href="{{ route('login') }}"
                                class="inline-flex items-center justify-center rounded-2xl bg-white px-8 py-4 text-base font-bold text-indigo-700 shadow-lg hover:bg-slate-100 transition active:scale-95"
                            >
                                Masuk ke Aplikasi Sekarang
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ========================================================================= -->
    <!-- FOOTER -->
    <!-- ========================================================================= -->
    <footer class="border-t border-slate-200 bg-white py-12 transition-colors duration-300 dark:border-slate-800 dark:bg-slate-950">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-600 text-white font-bold text-sm">
                        N
                    </span>
                    <span class="text-base font-bold text-slate-900 dark:text-white">
                        Notulen<span class="text-indigo-600 dark:text-indigo-400">App</span>
                    </span>
                    <span class="text-xs text-slate-400">• v1.2 AI Edition</span>
                </div>

                <div class="flex flex-wrap items-center justify-center gap-6 text-xs text-slate-500 dark:text-slate-400">
                    <a href="#fitur" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Fitur</a>
                    <a href="#alur-kerja" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Alur Kerja</a>
                    <a href="#keamanan" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Keamanan Data</a>
                    <a href="{{ route('login') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Login Notulis</a>
                    <a href="{{ route('platform.login') }}" class="hover:text-indigo-600 dark:hover:text-indigo-400 transition">Portal Platform</a>
                </div>

                <p class="text-xs text-slate-400 dark:text-slate-500 text-center md:text-right">
                    &copy; {{ date('Y') }} NotulenApp. All rights reserved.
                </p>
            </div>
        </div>
    </footer>
</body>
</html>
