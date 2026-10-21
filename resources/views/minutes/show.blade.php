<x-layouts.app :title="'Notulen - ' . $meeting->title">
    <div x-data="{ reviseModalOpen: false }" class="space-y-6">
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
