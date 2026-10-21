<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">

    <!-- Generic Open Graph Tags (Confidentiality: Meeting title is NEVER leaked) -->
    <meta property="og:title" content="Notulen Rapat">
    <meta property="og:description" content="Dokumen resmi notulensi rapat.">
    <meta property="og:type" content="article">

    <title>Verifikasi Akses - Notulen Rapat</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-50 text-slate-800">
    <div class="sm:mx-auto sm:w-full sm:max-w-md px-4">
        <!-- Logo / Icon -->
        <div class="text-center space-y-2">
            <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-600 text-white font-bold text-xl shadow-md">
                N
            </span>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                Verifikasi Akses Dokumen
            </h1>
            <p class="text-xs text-slate-500">
                Dokumen notulensi rapat ini dilindungi dan hanya dapat diakses oleh peserta terdaftar.
            </p>
        </div>

        <div class="mt-6">
            <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs sm:p-8 space-y-5">
                @if ($errors->has('gate'))
                    <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-xs font-semibold text-rose-800 flex items-start gap-2">
                        <span>⚠️</span>
                        <span>{{ $errors->first('gate') }}</span>
                    </div>
                @endif

                <form method="POST" action="{{ route('public_share.verify', ['token' => $token]) }}" class="space-y-4">
                    @csrf

                    @if($shareLink->access_mode === 'phone')
                        <div class="space-y-1.5">
                            <label for="phone" class="block text-xs font-bold text-slate-700">
                                Nomor WhatsApp Terdaftar <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="tel"
                                id="phone"
                                name="phone"
                                required
                                autofocus
                                value="{{ old('phone') }}"
                                placeholder="Contoh: 081234567890"
                                class="block w-full min-h-[44px] rounded-xl border border-slate-300 px-3.5 py-2.5 text-sm shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600"
                            />
                            <p class="text-[11px] text-slate-400">
                                Masukkan nomor WhatsApp yang tercantum pada daftar undangan rapat Anda.
                            </p>
                        </div>
                    @else
                        <div class="space-y-1.5">
                            <label for="code" class="block text-xs font-bold text-slate-700">
                                Kode PIN Akses Rapat <span class="text-rose-500">*</span>
                            </label>
                            <input
                                type="text"
                                id="code"
                                name="code"
                                required
                                autofocus
                                maxlength="6"
                                inputmode="numeric"
                                placeholder="6 Digit Angka PIN"
                                class="block w-full min-h-[44px] rounded-xl border border-slate-300 px-3.5 py-2.5 text-center text-lg font-bold tracking-widest shadow-2xs focus:border-indigo-600 focus:ring-2 focus:ring-indigo-600"
                            />
                            <p class="text-[11px] text-slate-400">
                                Masukkan 6 digit kode PIN yang dibagikan oleh notulis atau ketua rapat.
                            </p>
                        </div>
                    @endif

                    <div class="pt-2">
                        <button
                            type="submit"
                            class="w-full inline-flex min-h-[44px] items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-indigo-700 active:scale-[0.98] transition cursor-pointer"
                        >
                            <span>Buka Notulen Rapat →</span>
                        </button>
                    </div>
                </form>

                <div class="border-t border-slate-100 pt-4 text-center">
                    <p class="text-[11px] text-slate-400">
                        🔒 Akses tercatat dalam audit log sistem demi keamanan informasi rapat.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
