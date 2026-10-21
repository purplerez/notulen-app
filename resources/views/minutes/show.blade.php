<x-layouts.app :title="'Notulen - ' . $meeting->title">
    <div x-data="{ reviseModalOpen: false, shareModalOpen: false, copied: false, clickedWa: {} }" class="space-y-6">
        <!-- Top Navigation -->
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200/80 pb-4">
            <div>
                <a href="{{ route('meetings.show', $meeting) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Ruang Rapat
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                    Notulen: {{ $meeting->title }}
                </h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 pt-1">
                    <span>📅 {{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('l, d F Y') }}</span>
                    @if($meeting->start_time)
                        <span>⏰ {{ substr($meeting->start_time, 0, 5) }} WIB</span>
                    @endif
                    @if($meeting->location)
                        <span>📍 {{ $meeting->location }}</span>
                    @endif
                </div>
            </div>

            <!-- Version Selector & Status Badge -->
            <div class="flex items-center gap-2">
                @if($minute->isFinal())
                    <span class="inline-flex items-center rounded-lg bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-800 ring-1 ring-emerald-600/20">
                        ✅ Versi {{ $minute->version }} (Final)
                    </span>
                @else
                    <span class="inline-flex items-center rounded-lg bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-800 ring-1 ring-amber-600/20">
                        ⏳ Versi {{ $minute->version }} (Draf)
                    </span>
                @endif

                @if($allVersions->count() > 1)
                    <div class="relative" x-data="{ open: false }">
                        <button
                            type="button"
                            @click="open = !open"
                            class="inline-flex items-center gap-1 rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
                        >
                            <span>Riwayat Versi ({{ $allVersions->count() }})</span>
                            <span class="text-[10px]">▼</span>
                        </button>
                        <div
                            x-show="open"
                            @click.away="open = false"
                            class="absolute right-0 mt-1 w-56 rounded-xl border border-slate-200 bg-white p-1.5 shadow-lg z-20 space-y-1 text-xs"
                            style="display: none;"
                        >
                            @foreach($allVersions as $ver)
                                <a
                                    href="{{ route('meetings.minutes.show', ['meeting' => $meeting, 'v' => $ver->version]) }}"
                                    class="block rounded-lg px-3 py-2 transition {{ (int)$ver->version === (int)$minute->version ? 'bg-indigo-50 font-bold text-indigo-700' : 'text-slate-700 hover:bg-slate-50' }}"
                                >
                                    <div class="flex items-center justify-between">
                                        <span>Versi {{ $ver->version }} ({{ ucfirst($ver->status) }})</span>
                                        @if((int)$ver->version === (int)$latestFinal?->version)
                                            <span class="text-[10px] text-emerald-600 font-bold">Terbaru</span>
                                        @endif
                                    </div>
                                    @if($ver->revision_reason)
                                        <p class="text-[10px] text-slate-400 truncate mt-0.5">{{ $ver->revision_reason }}</p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if (session('info'))
            <div class="rounded-xl bg-blue-50 p-4 border border-blue-200 text-sm text-blue-800">
                {{ session('info') }}
            </div>
        @endif

        <!-- Banner: Alert Ada Revisi Baru -->
        @if($hasNewerFinalRevision)
            <div class="rounded-2xl border border-amber-300 bg-amber-50/80 p-4 text-xs text-amber-900 flex items-center justify-between gap-3">
                <div class="space-y-0.5">
                    <p class="font-bold">⚠️ Ada Revisi Baru (Versi {{ $latestFinal->version }})</p>
                    <p class="text-amber-700">Anda sedang melihat arsip versi {{ $minute->version }}. Versi final terbaru telah dipublikasikan.</p>
                </div>
                <a
                    href="{{ route('meetings.minutes.show', ['meeting' => $meeting, 'v' => $latestFinal->version]) }}"
                    class="shrink-0 rounded-xl bg-amber-600 px-3 py-2 font-bold text-white shadow-2xs hover:bg-amber-700 transition"
                >
                    Lihat Versi {{ $latestFinal->version }}
                </a>
            </div>
        @endif

        <!-- Banner: Draft Notification -->
        @if($minute->isDraft())
            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 text-xs text-amber-900 flex flex-wrap items-center justify-between gap-3">
                <div class="space-y-0.5">
                    <p class="font-bold">⏳ Draf Notulen Belum Difinalkan</p>
                    <p class="text-amber-700">
                        @if($minute->version > 1)
                            Draf revisi dibuat oleh <strong>{{ $minute->creator->name }}</strong> dengan alasan: <em>"{{ $minute->revision_reason }}"</em>.
                        @else
                            Draf awal otomatis dari transkrip rapat. Hanya notulis pembuat yang dapat menyunting dan memfinalkannya.
                        @endif
                    </p>
                </div>
                @if($canEditCurrent)
                    <a
                        href="{{ route('meetings.minutes.edit', ['meeting' => $meeting, 'v' => $minute->version]) }}"
                        class="shrink-0 rounded-xl bg-amber-600 px-3.5 py-2 font-bold text-white shadow-2xs hover:bg-amber-700 transition"
                    >
                        ✏️ Lanjutkan Penyuntingan Draf
                    </a>
                @endif
            </div>
        @endif

        <!-- Banner: Active In-Progress Draft Revision Warning (only for notulis/admin who can edit) -->
        @if($minute->isFinal() && $draftRevision && auth()->user()?->can('update', $draftRevision))
            <div class="rounded-2xl border border-blue-200 bg-blue-50/70 p-4 text-xs text-blue-900 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="font-bold">ℹ️ Draf Revisi Versi {{ $draftRevision->version }} Sedang Dikerjakan</p>
                    <p class="text-blue-700">Oleh {{ $draftRevision->creator->name }}: <em>"{{ $draftRevision->revision_reason }}"</em>. Versi final saat ini (versi {{ $minute->version }}) tetap tampil ke pembaca hingga revisi difinalkan.</p>
                </div>
                <a
                    href="{{ route('meetings.minutes.edit', ['meeting' => $meeting, 'v' => $draftRevision->version]) }}"
                    class="shrink-0 rounded-xl bg-blue-600 px-3 py-1.5 font-bold text-white shadow-2xs hover:bg-blue-700"
                >
                    Buka Draf Revisi
                </a>
            </div>
        @endif

        <!-- Section: Pimpinan Acknowledgement Card -->
        @if($minute->isFinal())
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900">Konfirmasi 'Diketahui' Pimpinan</h2>
                        <p class="text-xs text-slate-500">Tanda konfirmasi bahwa pimpinan lembaga telah membaca dan menyetujui versi notulen ini</p>
                    </div>

                    @if($canAcknowledge)
                        @if($isAcknowledgedByMe)
                            <span class="inline-flex items-center gap-1 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-bold text-emerald-800 ring-1 ring-emerald-600/20">
                                <span>✅</span>
                                <span>Diketahui (Versi {{ $minute->version }}, {{ $minute->acknowledgements->where('user_id', auth()->id())->first()?->acknowledged_at?->translatedFormat('d M Y H:i') }})</span>
                            </span>
                        @else
                            <form method="POST" action="{{ route('meetings.minutes.acknowledge', ['meeting' => $meeting, 'minute' => $minute]) }}">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700 active:scale-[0.98] transition cursor-pointer"
                                >
                                    <span>✍️</span>
                                    <span>Tandai Diketahui (Versi {{ $minute->version }})</span>
                                </button>
                            </form>
                        @endif
                    @endif
                </div>

                <!-- Acknowledgement List -->
                @if($minute->acknowledgements->isNotEmpty())
                    <div class="pt-2 border-t border-slate-100 flex flex-wrap gap-2 text-xs">
                        <span class="text-slate-400 text-xs">Dikonfirmasi oleh:</span>
                        @foreach($minute->acknowledgements as $ack)
                            <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-0.5 text-slate-700 font-medium">
                                👤 {{ $ack->user->name }} ({{ \Carbon\Carbon::parse($ack->acknowledged_at)->translatedFormat('d M Y H:i') }})
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- Action Bar: PDF Download & Revise Button -->
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <a
                    href="{{ route('meetings.minutes.version.pdf', ['meeting' => $meeting, 'minute' => $minute]) }}"
                    target="_blank"
                    class="inline-flex min-h-[42px] items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-4 py-2 text-xs font-bold text-indigo-700 shadow-2xs hover:bg-indigo-100 transition"
                >
                    <span>📄</span>
                    <span>Cetak / Unduh PDF</span>
                </a>
            </div>

            @if($minute->isFinal() && $canRevise && ! $draftRevision)
                <div>
                    <button
                        type="button"
                        @click="reviseModalOpen = true"
                        class="inline-flex min-h-[42px] items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-2xs hover:bg-slate-50 transition cursor-pointer"
                    >
                        <span>📝</span>
                        <span>Revisi Notulen (Buat Versi {{ $minute->version + 1 }})</span>
                    </button>
                </div>
            @endif
        </div>

        <!-- Main Minutes Content Body -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-2xs space-y-8">
            <!-- Kop / Meta header -->
            <div class="border-b border-slate-200 pb-5 space-y-1">
                <div class="flex items-center justify-between text-xs text-slate-400">
                    <span>Gaya Notulen: <strong class="text-slate-700 capitalize">{{ $meeting->style }}</strong></span>
                    @if($meeting->share_expires_at)
                        <span>Batas Baca Peserta: <strong class="text-slate-700">{{ \Carbon\Carbon::parse($meeting->share_expires_at)->translatedFormat('d M Y H:i') }}</strong></span>
                    @endif
                </div>
            </div>

            <!-- 1. Ringkasan -->
            <section class="space-y-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                    <span>📌</span> Ringkasan Eksekutif
                </h2>
                <div class="rounded-xl bg-slate-50/70 p-4 text-sm text-slate-800 leading-relaxed border border-slate-100">
                    {{ $minute->getSection('ringkasan') ?: 'Tidak ada ringkasan yang dicatat.' }}
                </div>
            </section>

            <!-- 2. Agenda (if present) -->
            @php $agenda = $minute->getSection('agenda', []); @endphp
            @if(! empty($agenda))
                <section class="space-y-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                        <span>📋</span> Agenda Rapat
                    </h2>
                    <ul class="list-disc list-inside space-y-1.5 text-sm text-slate-800 pl-2">
                        @foreach($agenda as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            <!-- 3. Pembahasan (if present) -->
            @php $pembahasan = $minute->getSection('pembahasan', ''); @endphp
            @if(! empty($pembahasan))
                <section class="space-y-2">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                        <span>💬</span> Pembahasan
                    </h2>
                    <div class="text-sm text-slate-800 leading-relaxed space-y-2 whitespace-pre-line pl-1">
                        {{ $pembahasan }}
                    </div>
                </section>
            @endif

            <!-- 4. Keputusan -->
            <section class="space-y-2">
                <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                    <span>⚖️</span> Keputusan Rapat
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

            <!-- 5. Tindak Lanjut / Action Items (if use_followup) -->
            @if($meeting->use_followup)
                <section class="space-y-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-indigo-700 flex items-center gap-2">
                        <span>🎯</span> Matriks Tindak Lanjut (Action Items)
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

        <!-- Section: Distribusi & Tautan Berbagi Peserta -->
        @if($minute->isFinal() && (auth()->user()->isNotulis() || auth()->user()->isAdmin()))
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-5">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 pb-4">
                    <div>
                        <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                            <span>🔗</span> Distribusi & Tautan Baca Peserta
                        </h2>
                        <p class="text-xs text-slate-500">
                            Bagikan notulen final kepada peserta rapat melalui tautan aman dengan verifikasi WhatsApp atau PIN.
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        @if($activeShareLink)
                            <form method="POST" action="{{ route('meetings.share_links.revoke', ['meeting' => $meeting, 'shareLink' => $activeShareLink]) }}" onsubmit="return confirm('Apakah Anda yakin ingin mencabut tautan publik ini? Peserta tidak akan bisa lagi mengakses lewat tautan ini.');">
                                @csrf
                                <button
                                    type="submit"
                                    class="inline-flex min-h-[38px] items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3.5 py-1.5 text-xs font-bold text-rose-700 hover:bg-rose-100 transition cursor-pointer"
                                >
                                    <span>🚫 Cabut Tautan</span>
                                </button>
                            </form>
                        @endif

                        <button
                            type="button"
                            @click="shareModalOpen = true"
                            class="inline-flex min-h-[38px] items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700 active:scale-[0.98] transition cursor-pointer"
                        >
                            <span>{{ $activeShareLink ? '🔄 Buat Ulang Tautan' : '+ Buat Tautan Berbagi' }}</span>
                        </button>
                    </div>
                </div>

                @if(session('plain_share_token') || session('plain_share_pin'))
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-900 space-y-2">
                        <div class="flex items-center gap-1.5 font-bold">
                            <span>🎉</span> Tautan Baca Publik Berhasil Dibuat!
                        </div>
                        @if(session('plain_share_pin'))
                            <div class="p-2.5 bg-white rounded-lg border border-emerald-200 font-semibold">
                                🔑 Kode PIN Akses: <strong class="text-base text-indigo-700 tracking-wider">{{ session('plain_share_pin') }}</strong>
                                <span class="block text-[11px] text-slate-500 font-normal mt-0.5">Catat PIN ini dan bagikan kepada peserta. PIN hanya ditampilkan satu kali.</span>
                            </div>
                        @endif
                    </div>
                @endif

                @if($activeShareLink)
                    <!-- Active Link Information -->
                    <div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 font-bold text-emerald-800 text-[11px]">
                                    🟢 Tautan Aktif
                                </span>
                                <span class="text-slate-600">
                                    Mode: <strong>{{ $activeShareLink->access_mode === 'phone' ? 'Nomor WhatsApp' : 'PIN 6 Digit' }}</strong>
                                </span>
                                <span class="text-slate-400">•</span>
                                <span class="text-slate-600">
                                    Penerima: <strong>{{ $activeShareLink->recipients_scope === 'hadir' ? 'Peserta Hadir Saja' : 'Seluruh Undangan' }}</strong>
                                </span>
                            </div>

                            <span class="text-slate-500 text-[11px]">
                                Berlaku s.d. <strong>{{ $activeShareLink->expires_at->translatedFormat('d M Y H:i') }} WIB</strong> ({{ $activeShareLink->expires_at->diffForHumans() }})
                            </span>
                        </div>

                        <!-- Copy Link Input -->
                        @php
                            $currentShareUrl = $cachedPlainToken ? route('public_share.show', ['token' => $cachedPlainToken]) : null;
                        @endphp

                        @if($currentShareUrl)
                            <div class="flex items-center gap-2">
                                <input
                                    type="text"
                                    readonly
                                    x-ref="shareInput"
                                    value="{{ $currentShareUrl }}"
                                    class="block w-full min-h-[38px] rounded-xl border border-indigo-200 bg-white px-3 py-2 text-xs font-mono text-slate-700 shadow-2xs"
                                />
                                <button
                                    type="button"
                                    @click="navigator.clipboard.writeText($refs.shareInput.value); copied = true; setTimeout(() => copied = false, 2500)"
                                    class="inline-flex min-h-[38px] shrink-0 items-center gap-1.5 rounded-xl border border-indigo-300 bg-white px-3.5 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-50 transition cursor-pointer"
                                >
                                    <span x-show="!copied">📋 Salin Tautan</span>
                                    <span x-show="copied" class="text-emerald-700 font-bold" style="display: none;">✅ Tersalin!</span>
                                </button>
                            </div>
                        @else
                            <p class="text-xs text-slate-500">
                                ℹ️ Token rahasia tautan disimpan dalam format hash kriptografis demi keamanan. Tautan tetap aktif bagi peserta. Jika Anda belum menyalin atau ingin mendistribusikan ulang, silakan klik <strong>Buat Ulang Tautan</strong> di atas.
                            </p>
                        @endif
                    </div>

                    <!-- Recipients Table with wa.me buttons -->
                    @if($recipients->isNotEmpty())
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-700">
                                    Daftar Penerima Notulen ({{ $recipients->count() }} orang)
                                </h3>
                                <span class="text-[11px] text-slate-400">
                                    Status 'Dibuka' diperbarui otomatis saat peserta lolos gerbang
                                </span>
                            </div>

                            <div class="overflow-x-auto rounded-xl border border-slate-200">
                                <table class="w-full text-left text-xs text-slate-700">
                                    <thead class="bg-slate-50 text-[11px] font-bold uppercase text-slate-500 border-b border-slate-200">
                                        <tr>
                                            <th class="px-4 py-3">Nama Peserta</th>
                                            <th class="px-4 py-3">Jabatan</th>
                                            <th class="px-4 py-3">Nomor WhatsApp</th>
                                            <th class="px-4 py-3 text-center">Status Notulen</th>
                                            <th class="px-4 py-3 text-right">Kirim Notulen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($recipients as $rec)
                                            @php
                                                $cleanPhone = ltrim(\App\Support\PhoneNumber::normalize($rec->phone_e164), '+');
                                                $linkTarget = $currentShareUrl ?? url('/n/[tautan-dibagikan]');
                                                $waMessage = "Yth. " . $rec->name . ",\n\nBerikut tautan dokumen notulen resmi untuk rapat:\n*" . $meeting->title . "*\n\nSilakan buka tautan berikut untuk membaca dan mengunduh notulen:\n" . $linkTarget . "\n\nTerima kasih.";
                                                $waUrl = "https://wa.me/{$cleanPhone}?text=" . rawurlencode($waMessage);
                                                $hasOpened = isset($openedLogMap[$rec->id]);
                                            @endphp
                                            <tr class="hover:bg-slate-50/50">
                                                <td class="px-4 py-3 font-semibold text-slate-900">
                                                    {{ $rec->name }}
                                                    @if($rec->is_guest)
                                                        <span class="text-[10px] text-slate-400 font-normal">(Tamu)</span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-slate-600">{{ $rec->position ?: '-' }}</td>
                                                <td class="px-4 py-3 font-mono text-slate-600">+{{ $rec->phone_e164 }}</td>
                                                <td class="px-4 py-3 text-center">
                                                    @if($hasOpened)
                                                        <span class="inline-flex items-center gap-1 rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-800 ring-1 ring-emerald-600/20">
                                                            <span>👁️</span>
                                                            <span>Dibuka ({{ \Carbon\Carbon::parse($openedLogMap[$rec->id])->translatedFormat('d M H:i') }})</span>
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">
                                                            Belum Dibuka
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-4 py-3 text-right">
                                                    <div class="flex items-center justify-end gap-1.5">
                                                        <span
                                                            x-show="clickedWa[{{ $rec->id }}]"
                                                            class="text-[10px] text-emerald-600 font-medium"
                                                            style="display: none;"
                                                        >
                                                            ✓ Sudah Diklik
                                                        </span>
                                                        <a
                                                            href="{{ $waUrl }}"
                                                            target="_blank"
                                                            @click="clickedWa[{{ $rec->id }}] = true"
                                                            class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 hover:bg-emerald-100 transition"
                                                        >
                                                            <span>💬</span>
                                                            <span>Kirim WA</span>
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            <p class="text-[11px] text-slate-400 italic pt-1">
                                ℹ️ Catatan: Tombol 'Kirim WA' membuka obrolan WhatsApp di aplikasi Anda. Status 'Sudah Diklik' hanya penanda interaksi lokal; sistem tidak dapat memastikan pesan terkirim secara eksternal. Status 'Dibuka' diperbarui saat penerima sukses memverifikasi akses.
                            </p>
                        </div>
                    @endif
                @else
                    <div class="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-6 text-center space-y-2">
                        <span class="text-2xl">🔗</span>
                        <h3 class="text-xs font-bold text-slate-700">Belum Ada Tautan Publik yang Aktif</h3>
                        <p class="text-[11px] text-slate-500 max-w-sm mx-auto">
                            Buat tautan baca peserta agar notulen final dapat diakses oleh pihak yang berhak melalui verifikasi nomor WhatsApp atau PIN.
                        </p>
                    </div>
                @endif
            </div>

            <!-- Share Link Creation Modal -->
            <div
                x-show="shareModalOpen"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
                style="display: none;"
            >
                <div
                    @click.away="shareModalOpen = false"
                    class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl space-y-4"
                >
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <h3 class="text-base font-bold text-slate-900">Buat Tautan Berbagi Peserta</h3>
                        <button type="button" @click="shareModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                    </div>

                    <form method="POST" action="{{ route('meetings.share_links.store', $meeting) }}" class="space-y-4">
                        @csrf

                        <div class="space-y-1.5">
                            <label for="modal_access_mode" class="block text-xs font-bold text-slate-700">Mode Gerbang Akses <span class="text-rose-500">*</span></label>
                            <select
                                id="modal_access_mode"
                                name="access_mode"
                                class="block w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            >
                                <option value="phone" selected>Nomor WhatsApp (Peserta mengetik no WA terdaftar)</option>
                                <option value="code">Kode PIN 6 Digit (PIN acak rahasia yang dibagikan)</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label for="modal_recipients_scope" class="block text-xs font-bold text-slate-700">Cakupan Penerima <span class="text-rose-500">*</span></label>
                            <select
                                id="modal_recipients_scope"
                                name="recipients_scope"
                                class="block w-full rounded-xl border border-slate-300 bg-white p-2.5 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            >
                                <option value="semua_undangan" selected>Seluruh Undangan Rapat</option>
                                <option value="hadir">Peserta yang Hadir Saja</option>
                            </select>
                        </div>

                        <div class="space-y-1.5">
                            <label for="modal_expires_at" class="block text-xs font-bold text-slate-700">Masa Berlaku Tautan <span class="text-rose-500">*</span></label>
                            <input
                                type="datetime-local"
                                id="modal_expires_at"
                                name="expires_at"
                                required
                                min="{{ now()->addHour()->format('Y-m-d\TH:i') }}"
                                value="{{ old('expires_at', $meeting->share_expires_at ? \Carbon\Carbon::parse($meeting->share_expires_at)->format('Y-m-d\TH:i') : now()->addDays(7)->format('Y-m-d\TH:i')) }}"
                                class="block w-full rounded-xl border border-slate-300 p-2.5 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            />
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2">
                            <button
                                type="button"
                                @click="shareModalOpen = false"
                                class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                            >
                                Batal
                            </button>
                            <button
                                type="submit"
                                class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700"
                            >
                                Simpan & Buat Tautan
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Revision Modal -->
        <div
            x-show="reviseModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
            style="display: none;"
        >
            <div
                @click.away="reviseModalOpen = false"
                class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl space-y-4"
            >
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-base font-bold text-slate-900">Buat Revisi Notulen</h3>
                    <button type="button" @click="reviseModalOpen = false" class="text-slate-400 hover:text-slate-600 text-lg">✕</button>
                </div>

                <p class="text-xs text-slate-500 leading-relaxed">
                    Revisi akan membuat draf baru (Versi {{ $minute->version + 1 }}) yang disalin dari versi final saat ini.
                    Versi final sebelumnya (Versi {{ $minute->version }}) tetap aktif dibaca oleh pimpinan dan peserta sampai revisi baru difinalkan.
                </p>

                <form method="POST" action="{{ route('meetings.minutes.revise', $meeting) }}" class="space-y-4">
                    @csrf
                    <div class="space-y-1.5">
                        <label for="revision_reason" class="block text-xs font-bold text-slate-700">Alasan Revisi <span class="text-rose-500">*</span></label>
                        <textarea
                            id="revision_reason"
                            name="revision_reason"
                            rows="3"
                            required
                            placeholder="Contoh: Koreksi penanggung jawab butir keputusan 2 dan penambahan tenggat waktu tindak lanjut."
                            class="block w-full rounded-xl border border-slate-300 p-3 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                        ></textarea>
                        <p class="text-[11px] text-slate-400">Jelaskan alasan dilakukannya revisi (minimal 5 karakter).</p>
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            @click="reviseModalOpen = false"
                            class="rounded-xl border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            Batal
                        </button>
                        <button
                            type="submit"
                            class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700"
                        >
                            Buat Draf Revisi
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-layouts.app>
