<x-layouts.app :title="'Dashboard - ' . config('app.name', 'Notulensi Rapat')">
    <x-slot:headerActions>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button
                type="submit"
                class="inline-flex min-h-[38px] items-center rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50"
            >
                Keluar
            </button>
        </form>
    </x-slot:headerActions>

    <div class="space-y-6">
        <!-- Welcome Card -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Organisasi</p>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                        {{ $organization?->name ?? 'Organisasi' }}
                    </h1>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize
                    @if(auth()->user()->role === 'admin') bg-purple-50 text-purple-700 ring-1 ring-purple-600/10
                    @elseif(auth()->user()->role === 'pimpinan') bg-amber-50 text-amber-700 ring-1 ring-amber-600/10
                    @else bg-blue-50 text-blue-700 ring-1 ring-blue-600/10 @endif">
                    {{ auth()->user()->role }}
                </span>
            </div>

            <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between text-sm">
                <span class="text-slate-500">Masuk sebagai: <strong class="text-slate-700">{{ auth()->user()->name }}</strong> ({{ auth()->user()->email }})</span>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <!-- Tenant Action Cards -->
        <div class="space-y-3">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-400">Menu Utama</h2>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                @can('create', App\Models\User::class)
                    <a
                        href="{{ route('users.index') }}"
                        class="flex items-center justify-between rounded-xl border border-slate-200/90 bg-white p-4 shadow-2xs hover:border-indigo-300 hover:bg-slate-50/60 transition"
                    >
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 font-bold">
                                👥
                            </span>
                            <div>
                                <h3 class="font-semibold text-slate-900 text-sm">Manajemen Pengguna</h3>
                                <p class="text-xs text-slate-500">Kelola akun notulis organisasi</p>
                            </div>
                        </div>
                        <span class="text-slate-400 text-sm">→</span>
                    </a>
                @endcan

                <div class="flex items-center justify-between rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-4">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-500 font-bold">
                            📝
                        </span>
                        <div>
                            <h3 class="font-semibold text-slate-700 text-sm">Daftar Rapat</h3>
                            <p class="text-xs text-slate-400">Fitur sedang dalam pengembangan</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
