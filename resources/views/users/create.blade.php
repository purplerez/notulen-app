<x-layouts.app :title="'Tambah Notulis - ' . config('app.name', 'Notulensi Rapat')">
    <div class="space-y-6">
        <div>
            <a href="{{ route('users.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                ← Kembali ke Daftar Pengguna
            </a>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                Tambah Akun Notulis
            </h1>
            <p class="text-sm text-slate-500 mt-1">
                Buat akun notulis baru untuk mencatat dan mengelola rapat organisasi.
            </p>
        </div>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6">
            <form method="POST" action="{{ route('users.store') }}" class="space-y-5">
                @csrf

                <!-- Name -->
                <div class="space-y-1.5">
                    <label for="name" class="block text-sm font-medium text-slate-700">Nama Lengkap</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        autofocus
                        class="block w-full min-h-[48px] rounded-xl border @error('name') border-rose-300 bg-rose-50/30 text-rose-900 @else border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 @enderror px-3.5 py-2.5 text-base shadow-xs focus:outline-none focus:ring-2 focus:ring-offset-1"
                        placeholder="Contoh: Budi Prasetyo"
                    />
                    @error('name')
                        <p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Email -->
                <div class="space-y-1.5">
                    <label for="email" class="block text-sm font-medium text-slate-700">Alamat Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="block w-full min-h-[48px] rounded-xl border @error('email') border-rose-300 bg-rose-50/30 text-rose-900 @else border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 @enderror px-3.5 py-2.5 text-base shadow-xs focus:outline-none focus:ring-2 focus:ring-offset-1"
                        placeholder="notulis@organisasi.id"
                    />
                    @error('email')
                        <p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password -->
                <div class="space-y-1.5">
                    <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi (min. 8 karakter)</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        class="block w-full min-h-[48px] rounded-xl border @error('password') border-rose-300 bg-rose-50/30 text-rose-900 @else border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 @enderror px-3.5 py-2.5 text-base shadow-xs focus:outline-none focus:ring-2 focus:ring-offset-1"
                        placeholder="••••••••"
                    />
                    @error('password')
                        <p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Password Confirmation -->
                <div class="space-y-1.5">
                    <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Konfirmasi Kata Sandi</label>
                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        required
                        class="block w-full min-h-[48px] rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 px-3.5 py-2.5 text-base shadow-xs focus:outline-none focus:ring-2 focus:ring-offset-1"
                        placeholder="••••••••"
                    />
                </div>

                <div class="rounded-xl bg-slate-50 p-3.5 border border-slate-200/80 text-xs text-slate-600">
                    ℹ️ Peran akun yang dibuat otomatis adalah <strong>Notulis</strong>. Akun Administrator dan Pimpinan dikelola melalui operator sistem.
                </div>

                <div class="flex items-center justify-end gap-3 pt-2">
                    <a
                        href="{{ route('users.index') }}"
                        class="inline-flex min-h-[46px] items-center rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Batal
                    </a>
                    <button
                        type="submit"
                        class="inline-flex min-h-[46px] items-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700 active:scale-[0.99] transition-transform"
                    >
                        Simpan Notulis
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
