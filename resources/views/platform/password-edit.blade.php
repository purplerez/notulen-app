@extends('platform.layouts.app')

@section('title', 'Ubah Password Super Admin')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <!-- Header Section -->
    <div class="flex items-center justify-between gap-4">
        <div>
            <a href="{{ route('platform.dashboard') }}" class="text-xs font-semibold text-indigo-400 hover:text-indigo-300 transition">
                ← Kembali ke Platform Console
            </a>
            <h1 class="text-xl font-bold tracking-tight text-white mt-1">Ubah Password Super Admin</h1>
            <p class="text-xs text-slate-400">Perbarui kata sandi akun kendali pusat platform.</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-800/60 bg-rose-950/40 p-4 text-xs font-medium text-rose-300 space-y-1">
            @foreach ($errors->all() as $error)
                <p>⚠️ {{ $error }}</p>
            @endforeach
        </div>
    @endif

    <!-- Form Card -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-6 shadow-md space-y-5">
        <div class="flex items-center justify-between border-b border-slate-800 pb-4">
            <div>
                <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Akun Platform</span>
                <span class="block text-sm font-bold text-white">{{ $superAdmin->name }}</span>
                <span class="block text-xs text-slate-400">{{ $superAdmin->email }}</span>
            </div>
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-500/30 bg-indigo-950/40 px-3 py-1 text-xs font-bold text-indigo-300">
                🛡️ Platform Guard
            </span>
        </div>

        <form method="POST" action="{{ route('platform.password.update') }}" class="space-y-4" x-data="{ showCurrent: false, showNew: false }">
            @csrf
            @method('PUT')

            <!-- Current Password -->
            <div class="space-y-1.5">
                <label for="current_password" class="block text-xs font-semibold text-slate-300">
                    Password Saat Ini <span class="text-rose-400">*</span>
                </label>
                <div class="relative">
                    <input
                        :type="showCurrent ? 'text' : 'password'"
                        id="current_password"
                        name="current_password"
                        required
                        autocomplete="current-password"
                        placeholder="Masukkan password super admin saat ini"
                        class="block w-full min-h-[44px] rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 pr-10 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                    />
                    <button
                        type="button"
                        @click="showCurrent = !showCurrent"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-200"
                        tabindex="-1"
                    >
                        <span x-show="!showCurrent" class="text-xs">👁️</span>
                        <span x-show="showCurrent" class="text-xs" style="display: none;">🔒</span>
                    </button>
                </div>
            </div>

            <!-- New Password -->
            <div class="space-y-1.5">
                <label for="password" class="block text-xs font-semibold text-slate-300">
                    Password Baru <span class="text-rose-400">*</span>
                </label>
                <div class="relative">
                    <input
                        :type="showNew ? 'text' : 'password'"
                        id="password"
                        name="password"
                        required
                        autocomplete="new-password"
                        placeholder="Minimal 8 karakter"
                        class="block w-full min-h-[44px] rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 pr-10 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                    />
                    <button
                        type="button"
                        @click="showNew = !showNew"
                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 hover:text-slate-200"
                        tabindex="-1"
                    >
                        <span x-show="!showNew" class="text-xs">👁️</span>
                        <span x-show="showNew" class="text-xs" style="display: none;">🔒</span>
                    </button>
                </div>
                <p class="text-[11px] text-slate-500">Gunakan kombinasi yang kuat untuk menjaga keamanan platform utama.</p>
            </div>

            <!-- Confirm New Password -->
            <div class="space-y-1.5">
                <label for="password_confirmation" class="block text-xs font-semibold text-slate-300">
                    Konfirmasi Password Baru <span class="text-rose-400">*</span>
                </label>
                <input
                    :type="showNew ? 'text' : 'password'"
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    placeholder="Ulangi password baru"
                    class="block w-full min-h-[44px] rounded-xl border border-slate-700 bg-slate-950 px-3.5 py-2.5 text-sm text-slate-100 placeholder-slate-500 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500/20"
                />
            </div>

            <!-- Actions -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-800">
                <a
                    href="{{ route('platform.dashboard') }}"
                    class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-700 transition"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="rounded-xl bg-indigo-600 px-5 py-2 text-xs font-bold text-white shadow-sm hover:bg-indigo-500 active:scale-95 transition"
                >
                    Simpan Password Baru
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

