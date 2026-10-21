<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50 antialiased">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">

    <!-- Generic Open Graph Tags (Confidentiality) -->
    <meta property="og:title" content="Notulen Rapat">
    <meta property="og:description" content="Dokumen resmi notulensi rapat.">
    <meta property="og:type" content="article">

    <title>Status Tautan - Notulen Rapat</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-50 text-slate-800">
    <div class="sm:mx-auto sm:w-full sm:max-w-md px-4 text-center">
        <div class="rounded-2xl border border-slate-200/90 bg-white p-8 shadow-xs space-y-4">
            @if($type === 'revoked')
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 text-2xl shadow-2xs">
                    🚫
                </div>
                <h1 class="text-lg font-bold text-slate-900">Tautan Telah Dicabut</h1>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Tautan baca dokumen notulen ini telah dicabut atau dinonaktifkan oleh penyelenggara rapat. Silakan hubungi notulis atau panitia rapat untuk informasi lebih lanjut.
                </p>
            @elseif($type === 'expired')
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-amber-50 text-amber-600 text-2xl shadow-2xs">
                    ⏳
                </div>
                <h1 class="text-lg font-bold text-slate-900">Masa Berlaku Berakhir</h1>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Batas waktu membaca dokumen notulen ini telah berakhir pada
                    <strong class="text-slate-700">{{ $shareLink->expires_at?->translatedFormat('d F Y, H:i') }} WIB</strong>.
                </p>
            @else
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-700 text-2xl shadow-2xs">
                    🔒
                </div>
                <h1 class="text-lg font-bold text-slate-900">Tautan Terkunci Sementara</h1>
                <p class="text-xs text-slate-500 leading-relaxed">
                    Tautan akses ini dikunci sementara karena mendeteksi terlalu banyak percobaan verifikasi yang tidak sesuai.
                    Silakan coba kembali beberapa saat lagi.
                </p>
            @endif

            <div class="border-t border-slate-100 pt-4">
                <p class="text-[11px] text-slate-400">
                    Sistem Notulensi Rapat Resmi
                </p>
            </div>
        </div>
    </div>
</body>
</html>
