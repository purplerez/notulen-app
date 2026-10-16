<x-layouts.app :title="'Manajemen Pengguna - ' . config('app.name', 'Notulensi Rapat')">
    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Dashboard
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                    Pengguna Organisasi
                </h1>
            </div>

            @can('create', App\Models\User::class)
                <a
                    href="{{ route('users.create') }}"
                    class="inline-flex min-h-[44px] items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700"
                >
                    + Tambah Notulis
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-2xs">
            <ul role="list" class="divide-y divide-slate-100">
                @forelse($users as $user)
                    <li class="flex items-center justify-between p-4 sm:px-6">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $user->name }}</p>
                            <p class="truncate text-xs text-slate-500">{{ $user->email }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold capitalize
                                @if($user->role === 'admin') bg-purple-50 text-purple-700 ring-1 ring-purple-600/10
                                @elseif($user->role === 'pimpinan') bg-amber-50 text-amber-700 ring-1 ring-amber-600/10
                                @else bg-blue-50 text-blue-700 ring-1 ring-blue-600/10 @endif">
                                {{ $user->role }}
                            </span>
                            @if(!$user->is_active)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">
                                    Nonaktif
                                </span>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="p-6 text-center text-sm text-slate-500">
                        Belum ada pengguna lain di organisasi ini.
                    </li>
                @endforelse
            </ul>
        </div>
    </div>
</x-layouts.app>
