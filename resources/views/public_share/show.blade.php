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

    <title>Notulen: {{ $meeting->title }}</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-full flex-col text-slate-800 selection:bg-indigo-500 selection:text-white pt-safe pb-safe pl-safe pr-safe bg-slate-50">
    <div class="flex min-h-screen flex-col">
        <!-- Top Header Bar -->
        <header class="sticky top-0 z-40 w-full border-b border-slate-200/80 bg-white/95 backdrop-blur backdrop-filter">
            <div class="mx-auto flex h-16 max-w-3xl items-center justify-between px-4 sm:px-6">
                <div class="flex items-center gap-2">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-sm shadow-xs">
                        N
                    </span>
                    <span class="text-sm font-semibold tracking-tight text-slate-900 truncate max-w-[200px] sm:max-w-md">
                        {{ $meeting->organization?->name ?? 'Notulensi Rapat' }}
                    </span>
                </div>

                <div>
                    <a
                        href="{{ route('public_share.pdf', ['token' => $token]) }}"
                        class="inline-flex min-h-[38px] items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700 active:scale-[0.98] transition cursor-pointer"
                    >
                        <span>📄</span>
                        <span>Unduh PDF</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-1 py-6 sm:py-8">
            <div class="mx-auto max-w-3xl px-4 sm:px-6 space-y-6">

                <!-- Title & Metadata Card -->
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-600/20">
                                ✅ Versi {{ $minute->version }} (Final)
                            </span>
                            @if($isRevised)
                                <span class="inline-flex items-center rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-600/20">
                                    📝 Hasil Revisi
                                </span>
                            @endif
                        </div>

                        <span class="text-[11px] text-slate-400">
                            Disahkan: {{ $minute->finalized_at?->translatedFormat('d M Y H:i') }} WIB
                        </span>
                    </div>

                    <div>
                        <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                            {{ $meeting->title }}
                        </h1>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500 pt-2">
                            <span>📅 {{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('l, d F Y') }}</span>
                            @if($meeting->start_time)
                                <span>⏰ {{ substr($meeting->start_time, 0, 5) }} WIB</span>
                            @endif
                            @if($meeting->location)
                                <span>📍 {{ $meeting->location }}</span>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Main Minutes Content Body -->
                <div class="rounded-2xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-2xs space-y-7">
                    <!-- 1. Ringkasan Eksekutif -->
                    <section class="space-y-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                            <span>📌</span> I. Ringkasan Rapat
                        </h2>
                        <div class="rounded-xl bg-slate-50/70 p-4 text-sm text-slate-800 leading-relaxed border border-slate-100">
                            {{ $minute->getSection('ringkasan') ?: 'Tidak ada ringkasan yang dicatat.' }}
                        </div>
                    </section>

                    <!-- 2. Agenda Rapat (jika ada) -->
                    @php $agenda = $minute->getSection('agenda', []); @endphp
                    @if(! empty($agenda))
                        <section class="space-y-2">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                                <span>📋</span> II. Agenda Rapat
                            </h2>
                            <ul class="list-disc list-inside space-y-1.5 text-sm text-slate-800 pl-2">
                                @foreach($agenda as $item)
                                    <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                        </section>
                    @endif

                    <!-- 3. Pembahasan (jika ada) -->
                    @php $pembahasan = $minute->getSection('pembahasan', ''); @endphp
                    @if(! empty($pembahasan))
                        <section class="space-y-2">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                                <span>💬</span> III. Pembahasan
                            </h2>
                            <div class="text-sm text-slate-800 leading-relaxed space-y-2 whitespace-pre-line pl-1">
                                {{ $pembahasan }}
                            </div>
                        </section>
                    @endif

                    <!-- 4. Keputusan Rapat -->
                    <section class="space-y-2">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                            <span>⚖️</span> IV. Keputusan Rapat
                        </h2>
                        @php $keputusan = $minute->getSection('keputusan', []); @endphp
                        @if(! empty($keputusan))
                            <div class="space-y-2">
                                @foreach($keputusan as $idx => $k)
                                    <div class="flex items-start gap-3 rounded-xl border border-slate-200/80 bg-white p-3.5 shadow-2xs">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-xs font-bold text-indigo-700">
                                            {{ $idx + 1 }}
                                        </span>
                                        <p class="text-sm font-medium text-slate-900 pt-0.5">{{ $k }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-slate-400 italic">Tidak ada butir keputusan spesifik yang dicatat.</p>
                        @endif
                    </section>

                    <!-- 5. Tindak Lanjut (jika use_followup aktif) -->
                    @if($meeting->use_followup)
                        <section class="space-y-3">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                                <span>🎯</span> V. Tindak Lanjut (Action Items)
                            </h2>
                            @if($minute->actionItems->isNotEmpty())
                                <div class="overflow-x-auto rounded-xl border border-slate-200">
                                    <table class="w-full text-left text-xs text-slate-700">
                                        <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-500 border-b border-slate-200">
                                            <tr>
                                                <th class="px-4 py-3">Uraian Tugas</th>
                                                <th class="px-4 py-3">Penanggung Jawab</th>
                                                <th class="px-4 py-3">Tenggat Waktu</th>
                                                <th class="px-4 py-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-slate-100">
                                            @foreach($minute->actionItems as $item)
                                                <tr class="hover:bg-slate-50/50">
                                                    <td class="px-4 py-3 font-medium text-slate-900">{{ $item->description }}</td>
                                                    <td class="px-4 py-3">{{ $item->assignee_name ?: '-' }}</td>
                                                    <td class="px-4 py-3">{{ $item->due_date ? \Carbon\Carbon::parse($item->due_date)->translatedFormat('d M Y') : '-' }}</td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[10px] font-bold uppercase
                                                            @if($item->status === 'completed') bg-emerald-50 text-emerald-700
                                                            @elseif($item->status === 'in_progress') bg-blue-50 text-blue-700
                                                            @elseif($item->status === 'cancelled') bg-slate-100 text-slate-600
                                                            @else bg-amber-50 text-amber-700 @endif">
                                                            {{ str_replace('_', ' ', $item->status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-xs text-slate-400 italic">Tidak ada tindak lanjut yang ditentukan.</p>
                            @endif
                        </section>
                    @endif
                </div>

                <!-- Footer Disclaimers -->
                <div class="text-center text-xs text-slate-400 py-4">
                    <p>Dokumen ini disajikan secara resmi melalui sistem notulensi rapat organisasi.</p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
