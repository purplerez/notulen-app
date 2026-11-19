<x-layouts.app :title="'Ubah Password - ' . config('app.name', 'Notulensi Rapat')" :narrow="true">
    <div class="space-y-6">
        <!-- Header & Back Navigation -->
        <div class="flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition">
                    ← Kembali ke Dashboard
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-0.5">
                    Ubah Password Akun
                </h1>
                <p class="text-xs text-slate-500">Perbarui kata sandi untuk mengamankan akun Anda</p>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                ✓ {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-xs text-rose-800 space-y-1">
                @foreach ($errors->all() as $error)
                    <p>⚠️ {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- User Information Card -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6 space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <span class="block text-xs font-medium uppercase tracking-wider text-slate-400">Pengguna</span>
                    <span class="block text-base font-bold text-slate-900">{{ $user->name }}</span>
                    <span class="block text-xs text-slate-500">{{ $user->email }}</span>
                </div>
                <div>
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider
                        @if($user->role === 'admin') bg-purple-50 text-purple-700 ring-1 ring-purple-600/20
                        @elseif($user->role === 'pimpinan') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                        @else bg-blue-50 text-blue-700 ring-1 ring-blue-600/20 @endif">
                        Peran: {{ $user->role }}
                    </span>
                </div>
            </div>

            <!-- Password Change Form -->
            <form method="POST" action="{{ route('password.update') }}" class="space-y-4" x-data="{ showCurrent: false, showNew: false }">
                @csrf
                @method('PUT')

                <!-- Current Password -->
                <div class="space-y-1.5">
                    <label for="current_password" class="block text-xs font-semibold text-slate-700">
                        Password Saat Ini <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            :type="showCurrent ? 'text' : 'password'"
                            id="current_password"
                            name="current_password"
                            required
                            autocomplete="current-password"
                            placeholder="Masukkan password Anda saat ini"
                            class="block w-full min-h-[46px] rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 pr-10 text-sm shadow-2xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                        />
                        <button
                            type="button"
                            @click="showCurrent = !showCurrent"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                            tabindex="-1"
                        >
                            <span x-show="!showCurrent" class="text-xs">👁️</span>
                            <span x-show="showCurrent" class="text-xs" style="display: none;">🔒</span>
                        </button>
                    </div>
                </div>

                <!-- New Password -->
                <div class="space-y-1.5">
                    <label for="password" class="block text-xs font-semibold text-slate-700">
                        Password Baru <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input
                            :type="showNew ? 'text' : 'password'"
                            id="password"
                            name="password"
                            required
                            autocomplete="new-password"
                            placeholder="Minimal 8 karakter"
                            class="block w-full min-h-[46px] rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 pr-10 text-sm shadow-2xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                        />
                        <button
                            type="button"
                            @click="showNew = !showNew"
                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-600"
                            tabindex="-1"
                        >
                            <span x-show="!showNew" class="text-xs">👁️</span>
                            <span x-show="showNew" class="text-xs" style="display: none;">🔒</span>
                        </button>
                    </div>
                    <p class="text-[11px] text-slate-400">Gunakan kombinasi huruf, angka, atau simbol agar lebih aman.</p>
                </div>

                <!-- Confirm New Password -->
                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-xs font-semibold text-slate-700">
                        Konfirmasi Password Baru <span class="text-rose-500">*</span>
                    </label>
                    <input
                        :type="showNew ? 'text' : 'password'"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        autocomplete="new-password"
                        placeholder="Ulangi password baru Anda"
                        class="block w-full min-h-[46px] rounded-xl border border-slate-300 bg-white px-3.5 py-2.5 text-sm shadow-2xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                    />
                </div>

                <!-- Action Buttons -->
                <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                    <a
                        href="{{ route('dashboard') }}"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        class="inline-flex min-h-[42px] items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-indigo-700 active:scale-[0.98] transition cursor-pointer"
                    >
                        Simpan Password Baru
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>

