<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-950 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>Verifikasi 2FA - Platform Console</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-950 text-slate-100 font-sans">
    <div class="sm:mx-auto sm:w-full sm:max-w-md px-4 text-center space-y-2">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600/20 border border-indigo-500/30 text-indigo-400 text-2xl font-bold">
            🔑
        </div>
        <h1 class="text-xl font-bold tracking-tight text-white">Verifikasi Dua Langkah (TOTP)</h1>
        <p class="text-xs text-slate-400">Wajib untuk seluruh akun Super Admin Platform</p>
    </div>

    <div class="mt-6 sm:mx-auto sm:w-full sm:max-w-md px-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-8 shadow-xl backdrop-blur-md space-y-6">
            @if ($isSetup)
                <!-- Setup Guide for First-time 2FA -->
                <div class="rounded-xl border border-indigo-900/60 bg-indigo-950/30 p-4 space-y-3">
                    <div class="flex items-center gap-2">
                        <span class="text-base">📲</span>
                        <h2 class="text-xs font-bold text-indigo-300">Konfigurasi Awal Aplikasi Authenticator</h2>
                    </div>

                    <p class="text-[11px] text-slate-300 leading-relaxed">
                        Buka aplikasi authenticator Anda (seperti Google Authenticator, Microsoft Authenticator, Bitwarden, atau 1Password), lalu masukkan Secret Key berikut:
                    </p>

                    <div class="rounded-lg border border-slate-700 bg-slate-950/80 p-3 text-center space-y-1">
                        <span class="text-[10px] text-slate-400 font-medium block">Secret Key (Base32):</span>
                        <code class="text-sm font-mono font-bold tracking-widest text-indigo-400 select-all block">{{ $secret }}</code>
                    </div>

                    <p class="text-[10px] text-slate-400 italic">
                        Tip: Jika menggunakan aplikasi yang mendukung URI, Anda juga dapat mengimpor via: <span class="text-slate-300 break-all select-all font-mono text-[9px]">{{ $otpUri }}</span>
                    </p>
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-xl border border-rose-800/60 bg-rose-950/40 p-3.5 text-xs text-rose-300 space-y-1">
                    <p class="font-bold">Verifikasi Gagal:</p>
                    <ul class="list-disc list-inside text-[11px] text-rose-300/90 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('platform.verify_2fa') }}" class="space-y-4">
                @csrf

                <div class="space-y-1.5 text-center">
                    <label for="code" class="block text-xs font-semibold text-slate-300">Masukkan 6-Digit Kode OTP</label>
                    <input
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="6"
                        id="code"
                        name="code"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        class="block w-full text-center rounded-xl border border-slate-700 bg-slate-800/80 px-3.5 py-3 text-2xl font-mono tracking-[0.5em] text-white placeholder-slate-600 shadow-xs focus:border-indigo-500 focus:ring-2 focus:ring-indigo-500"
                        placeholder="000000"
                    />
                    <p class="text-[11px] text-slate-400">Kode OTP diperbarui otomatis setiap 30 detik.</p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md hover:bg-indigo-500 transition-colors cursor-pointer"
                >
                    Verifikasi & Masuk Konsol →
                </button>
            </form>

            <div class="border-t border-slate-800 pt-4 text-center">
                <a href="{{ route('platform.login') }}" class="text-[11px] text-slate-400 hover:text-slate-200">
                    ← Kembali ke Halaman Login
                </a>
            </div>
        </div>
    </div>
</body>
</html>
