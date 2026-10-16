<x-layouts.app>
    <x-slot:title>
        Beranda - Notulensi Rapat
    </x-slot:title>

    <div class="space-y-6">
        <!-- Hero Card -->
        <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/80">
            <div class="inline-flex rounded-lg bg-indigo-50 p-2.5 text-indigo-700">
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 18.75a6 6 0 006-6v-1.5m-6 7.5a6 6 0 01-6-6v-1.5m6 7.5v3.75m-3.75 0h7.5M12 15.75a3 3 0 01-3-3V4.5a3 3 0 116 0v8.25a3 3 0 01-3 3z" />
                </svg>
            </div>
            <h1 class="mt-4 text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                Aplikasi Notulensi Rapat
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-600">
                Perekaman rapat tatap muka mobile-first, transkripsi otomatis, dan penyusunan notulen terstruktur untuk lembaga dan sekolah.
            </p>

            <div class="mt-6">
                @auth
                    <a
                        href="{{ route('dashboard') }}"
                        class="flex w-full min-h-[48px] items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    >
                        Buka Dashboard
                    </a>
                @else
                    <a
                        href="{{ route('login') }}"
                        class="flex w-full min-h-[48px] items-center justify-center rounded-xl bg-indigo-600 px-5 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-500 transition"
                    >
                        Masuk ke Akun
                    </a>
                @endauth
            </div>
        </div>

        <!-- Alpine.js Demonstration & Large Touch-Friendly Buttons -->
        <div x-data="{ ready: true, count: 0 }" class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200/80">
            <h2 class="text-base font-semibold text-slate-900">
                Kesiapan Sistem
            </h2>
            <div class="mt-3 flex items-center gap-2 text-xs font-medium text-emerald-700">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Framework Laravel 13, Tailwind CSS & Alpine.js Siap</span>
            </div>

            <div class="mt-5 space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div class="rounded-xl bg-slate-50 p-3.5 text-center border border-slate-100">
                        <span class="block text-xs text-slate-500">Database</span>
                        <span class="text-sm font-semibold text-slate-800">MySQL 8 / 9</span>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3.5 text-center border border-slate-100">
                        <span class="block text-xs text-slate-500">Antrean</span>
                        <span class="text-sm font-semibold text-slate-800">Database</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
