<x-layouts.app :title="'Masuk - ' . config('app.name', 'Notulensi Rapat')">
    <div class="space-y-6 pt-4">
        <div class="text-center space-y-1">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">Masuk ke Akun</h1>
            <p class="text-sm text-slate-500">Masukkan email dan kata sandi organisasi Anda</p>
        </div>

        @if (session('status'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="space-y-5">
            @csrf

            <!-- Email -->
            <div class="space-y-1.5">
                <label for="email" class="block text-sm font-medium text-slate-700">Alamat Email</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    value="{{ old('email') }}"
                    required
                    autofocus
                    autocomplete="username"
                    class="block w-full min-h-[48px] rounded-xl border @error('email') border-rose-300 bg-rose-50/30 text-rose-900 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 @enderror px-3.5 py-2.5 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-1"
                    placeholder="nama@organisasi.id"
                />
                @error('email')
                    <p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Password -->
            <div class="space-y-1.5">
                <label for="password" class="block text-sm font-medium text-slate-700">Kata Sandi</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    class="block w-full min-h-[48px] rounded-xl border @error('password') border-rose-300 bg-rose-50/30 text-rose-900 focus:border-rose-500 focus:ring-rose-500 @else border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 @enderror px-3.5 py-2.5 text-base shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-1"
                    placeholder="••••••••"
                />
                @error('password')
                    <p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Remember me -->
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2.5 text-sm text-slate-600 cursor-pointer select-none">
                    <input
                        type="checkbox"
                        name="remember"
                        class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                    />
                    <span>Ingat saya di perangkat ini</span>
                </label>
            </div>

            <!-- Submit Button (Touch-first min-h-12) -->
            <div>
                <button
                    type="submit"
                    class="flex w-full min-h-[50px] items-center justify-center rounded-xl bg-indigo-600 px-4 py-3 text-base font-semibold text-white shadow-sm hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 active:scale-[0.99] transition-transform"
                >
                    Masuk
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
