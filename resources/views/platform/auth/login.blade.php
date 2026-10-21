<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Login Super Admin - Platform Console</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-950 text-slate-100 font-sans">
    <div class="sm:mx-auto sm:w-full sm:max-w-md px-4 text-center space-y-2">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-white text-2xl font-black shadow-lg shadow-indigo-600/30">
            ⚡
        </div>
        <h1 class="text-xl font-bold tracking-tight text-white">Platform Super Admin</h1>
        <p class="text-xs text-slate-400">Area Terisolasi Pemantauan Biaya & Operasional</p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md px-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-8 shadow-xl backdrop-blur-md space-y-6">
            <!-- Warning Banner -->
            <div class="rounded-xl border border-amber-900/50 bg-amber-950/30 p-3 text-xs text-amber-300 flex items-start gap-2.5">
                <span class="text-base leading-none">🛡️</span>
                <div class="space-y-0.5">
                    <strong class="font-semibold block text-amber-200">Akses Terproteksi 2FA</strong>
                    <span class="text-[11px] text-amber-400/90 leading-relaxed block">
                        Super Admin tidak memiliki fitur impersonasi dan tidak dapat melihat data konten rapat lembaga.
                    </span>
                </div>
            </div>

            @if ($errors->any())
                <div class="rounded-xl border border-rose-800/60 bg-rose-950/40 p-3.5 text-xs text-rose-300 space-y-1">
                    <p class="font-bold">Gagal Masuk:</p>
                    <ul class="list-disc list-inside text-[11px] text-rose-300/90 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('platform.login') }}" class="space-y-4">
                @csrf

                <div class="space-y-1.5">
                    <label for="email" class="block text-xs font-semibold text-slate-300">Email Super Admin</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="block w-full rounded-xl border border-slate-700 bg-slate-800/80 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                        placeholder="admin@platform.test"
                    />
                </div>

                <div class="space-y-1.5">
                    <label for="password" class="block text-xs font-semibold text-slate-300">Password</label>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        class="block w-full rounded-xl border border-slate-700 bg-slate-800/80 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                        placeholder="••••••••"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-indigo-500 transition-colors cursor-pointer"
                >
                    Lanjutkan ke Verifikasi 2FA →
                </button>
            </form>

            <div class="border-t border-slate-800 pt-4 text-center">
                <a href="{{ route('login') }}" class="text-[11px] text-slate-400 hover:text-slate-200">
                    ← Kembali ke Login Pengguna Lembaga
                </a>
            </div>
        </div>
    </div>
</body>
</html>
