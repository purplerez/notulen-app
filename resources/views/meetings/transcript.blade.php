<x-layouts.app :title="'Transkrip & Notulensi - ' . $meeting->title . ' - ' . config('app.name', 'Notulensi Rapat')">
    <div x-data="{
        tab: 'editor', // 'editor' (Versi Lengkap), 'summary' (Versi Ringkas), 'diff' (Komparasi)
        searchQuery: '',
        showRawText: false,
        copiedAll: false,
        editingId: null,
        editText: '',
        seekAudio(sec) {
            const player = document.getElementById('meeting-audio-player');
            if (player) {
                player.currentTime = sec;
                player.play();
            }
        },
        startEdit(id, text) {
            this.editingId = id;
            this.editText = text;
            this.$nextTick(() => {
                const el = document.getElementById('edit-textarea-' + id);
                if (el) el.focus();
            });
        },
        cancelEdit() {
            this.editingId = null;
            this.editText = '';
        },
        copyAllTranscript() {
            const elements = document.querySelectorAll('[data-segment-block]');
            const lines = [];
            elements.forEach(el => {
                const time = el.getAttribute('data-time') || '';
                const textEl = el.querySelector('[data-clean-text]');
                const text = textEl ? textEl.innerText.trim() : '';
                if (text) {
                    lines.push(time ? `[${time}] ${text}` : text);
                }
            });
            const fullText = lines.join('\n\n');
            navigator.clipboard.writeText(fullText).then(() => {
                this.copiedAll = true;
                setTimeout(() => this.copiedAll = false, 2500);
            });
        }
    }" class="space-y-6">

        <!-- Top Navigation & Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
            <div>
                <a href="{{ route('meetings.show', $meeting) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 transition flex items-center gap-1">
                    <span>←</span>
                    <span>Kembali ke Detail Rapat</span>
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                    Transkripsi & Notulensi: {{ $meeting->title }}
                </h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 mt-1">
                    <span>📅 {{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('l, d F Y') }}</span>
                    @if($meeting->start_time)
                        <span>⏰ {{ substr($meeting->start_time, 0, 5) }} WIB</span>
                    @endif
                    <span>👥 Pembuat: {{ $meeting->creator?->name ?? 'Notulis' }}</span>
                    <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-semibold text-indigo-700">
                        {{ $segments->count() }} Segmen Ucapan
                    </span>
                </div>
            </div>

            <!-- Tab Switcher Utama (Versi Lengkap vs Versi Ringkas vs Diff) -->
            <div class="inline-flex rounded-2xl bg-slate-100 p-1.5 text-xs font-bold shadow-2xs self-start sm:self-auto">
                <button
                    type="button"
                    @click="tab = 'editor'"
                    :class="tab === 'editor' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                    class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 cursor-pointer"
                >
                    <span>🎙️</span>
                    <span>Versi Lengkap (Ucapan Asli)</span>
                </button>
                <button
                    type="button"
                    @click="tab = 'summary'"
                    :class="tab === 'summary' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                    class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 cursor-pointer"
                >
                    <span>📋</span>
                    <span>Versi Ringkas (Notulen)</span>
                </button>
                <button
                    type="button"
                    @click="tab = 'diff'"
                    :class="tab === 'diff' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-600 hover:text-slate-900'"
                    class="rounded-xl px-3.5 py-2 transition flex items-center gap-1.5 cursor-pointer"
                >
                    <span>⚖️</span>
                    <span>Perbandingan Asli vs Edit</span>
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800 flex items-center justify-between">
                <span>✓ {{ session('success') }}</span>
            </div>
        @endif

        <!-- Banner Wajib: Label Transkrip Mentah -->
        <div class="rounded-2xl bg-amber-50/90 border border-amber-200 p-4 text-xs text-amber-900 flex flex-wrap items-center justify-between gap-3 shadow-2xs">
            <div class="flex items-center gap-2.5">
                <span class="inline-flex h-7 w-7 items-center justify-center rounded-lg bg-amber-200/80 text-base shrink-0">
                    ⚠️
                </span>
                <div>
                    <span class="font-bold uppercase tracking-wider block text-[11px] text-amber-800">Status Bahan Mentah</span>
                    <span class="font-semibold text-sm text-amber-950">Transkrip otomatis, belum diperiksa</span>
                </div>
            </div>
            <div class="text-[11px] text-amber-700 max-w-sm">
                Transkrip mentah disimpan tanpa batas waktu. Notulis dapat melakukan perbaikan teks manual tanpa menghapus teks asli rekaman.
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 1: VERSI LENGKAP (Transkrip Alur Percakapan Bersih) -->
        <!-- ========================================================================= -->
        <div x-show="tab === 'editor'" class="space-y-5">
            <!-- Banner Penjelasan Versi Lengkap -->
            <div class="rounded-2xl bg-indigo-50/70 border border-indigo-200/80 p-4 text-xs text-indigo-950 flex flex-wrap items-center justify-between gap-3 shadow-2xs">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-white font-bold text-sm shrink-0 shadow-xs">
                        🎙️
                    </span>
                    <div class="space-y-0.5">
                        <span class="font-bold text-sm text-indigo-900 block">Versi Lengkap: Alur Ucapan Percakapan Rapat</span>
                        <p class="text-indigo-800 leading-relaxed text-[11px] max-w-2xl">
                            Menampilkan seluruh isi pembicaraan peserta rapat secara lengkap per stempel waktu. Teks telah otomatis dibersihkan dari suara tawa (<em>hahahaha/hehe</em>) dan kata filler tidak bermakna (<em>hmmmm/ehm/anu</em>), dengan <strong>tetap mempertahankan keaslian ungkapan bahasa daerah (Jawa/Sunda) serta istilah bahasa Inggris</strong>.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="copyAllTranscript()"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-300 bg-white px-3 py-2 text-xs font-bold text-indigo-700 shadow-2xs hover:bg-indigo-50 transition cursor-pointer"
                    >
                        <span x-show="!copiedAll">📋 Salin Transkrip Lengkap</span>
                        <span x-show="copiedAll" x-cloak class="text-emerald-600 font-bold">✓ Tersalin ke Clipboard!</span>
                    </button>
                </div>
            </div>

            <!-- Audio Player Section (Bila Audio Masih Ada) -->
            @if($audioAvailable && $audioStreamUrl)
                <div class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                                🎧 Pemutar Audio Rapat
                            </h2>
                            <p class="text-[11px] text-slate-400 mt-0.5">
                                Klik tombol stempel waktu pada segmen untuk melompat langsung ke rekaman audio terkait.
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-600/20">
                            Sinkronisasi Audio Aktif
                        </span>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                        <audio
                            id="meeting-audio-player"
                            controls
                            controlsList="nodownload"
                            oncontextmenu="return false;"
                            class="w-full h-10 rounded-lg outline-hidden"
                            preload="metadata"
                        >
                            <source src="{{ $audioStreamUrl }}" type="audio/mp4">
                            Browser Anda tidak mendukung pemutar audio ini.
                        </audio>
                    </div>
                </div>
            @elseif($meeting->audio_deleted_at !== null)
                <div class="rounded-xl bg-slate-100 p-3 text-xs text-slate-600 border border-slate-200 flex items-center justify-between">
                    <span>🔇 Audio rekaman telah dihapus sesuai retensi 1 bulan. Menampilkan teks transkrip saja.</span>
                    <span class="text-[11px] text-slate-500">Transkrip teks tetap tersedia</span>
                </div>
            @elseif($meeting->status === 'transcribing')
                <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800 border border-amber-200 flex items-center justify-between">
                    <span class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full bg-amber-500 animate-pulse"></span>
                        <span>⏳ Audio rapat sedang dalam antrean transkripsi & pemrosesan.</span>
                    </span>
                    <span class="text-[11px] text-amber-700">Teks akan muncul setelah worker antrean berjalan</span>
                </div>
            @endif

            <!-- Toolbar Pencarian & Toggle Teks Mentah -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3 rounded-2xl border border-slate-200/80 shadow-2xs">
                <div class="relative flex-1 max-w-md">
                    <input
                        type="text"
                        x-model="searchQuery"
                        placeholder="🔍 Cari kata atau nama dalam transkrip lengkap..."
                        class="block w-full rounded-xl border border-slate-300 bg-slate-50/50 px-3.5 py-2 text-xs text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 outline-none transition"
                    />
                    <button
                        x-show="searchQuery"
                        @click="searchQuery = ''"
                        type="button"
                        class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600"
                    >
                        ✕
                    </button>
                </div>

                <div class="flex items-center gap-4 text-xs">
                    <label class="flex items-center gap-2 cursor-pointer text-slate-600 hover:text-slate-900 select-none">
                        <input
                            type="checkbox"
                            x-model="showRawText"
                            class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                        />
                        <span>Tampilkan teks mentah asli STT</span>
                    </label>

                    @if(!$canEdit)
                        <span class="text-xs text-slate-400 font-medium">Mode Baca Saja (Pimpinan)</span>
                    @endif
                </div>
            </div>

            <!-- Daftar Segmen Transkrip Lengkap -->
            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xs divide-y divide-slate-100">
                @forelse($segments as $segment)
                    @php
                        $cleanText = $segment->getEffectiveText();
                        $rawText = $segment->text;
                    @endphp
                    <div
                        data-segment-block
                        data-time="{{ $segment->getFormattedStartTime() }}"
                        x-show="!searchQuery || '{{ addslashes(mb_strtolower($cleanText . ' ' . $rawText)) }}'.includes(searchQuery.toLowerCase())"
                        class="p-4 sm:p-5 transition hover:bg-slate-50/60 space-y-2.5"
                        id="segment-row-{{ $segment->id }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                @if($audioAvailable && $audioStreamUrl)
                                    <button
                                        type="button"
                                        @click="seekAudio({{ $segment->start_sec }})"
                                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100 active:scale-95 transition cursor-pointer"
                                        title="Lompat ke stempel waktu audio ini"
                                    >
                                        ▶ {{ $segment->getFormattedStartTime() }} - {{ $segment->getFormattedEndTime() }}
                                    </button>
                                @else
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 px-2 py-1 text-xs font-mono font-semibold text-slate-600">
                                        ⏱ {{ $segment->getFormattedStartTime() }} - {{ $segment->getFormattedEndTime() }}
                                    </span>
                                @endif

                                @if($segment->isEdited())
                                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-600/20">
                                        Diedit @if($segment->editor) oleh {{ $segment->editor->name }} @else (AI) @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">
                                        Bersih
                                    </span>
                                @endif
                            </div>

                            @if($canEdit)
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        x-show="editingId !== {{ $segment->id }}"
                                        @click="startEdit({{ $segment->id }}, '{{ addslashes($segment->getEffectiveText()) }}')"
                                        class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs transition cursor-pointer"
                                    >
                                        ✏️ Sunting
                                    </button>

                                    @if($segment->isEdited())
                                        <form method="POST" action="{{ route('meetings.transcript.reset', [$meeting, $segment]) }}" onsubmit="return confirm('Kembalikan segmen ini ke teks asli hasil transkripsi?')">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100 transition cursor-pointer"
                                                title="Reset ke teks asli"
                                            >
                                                ↺ Reset
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Teks Utama Bersih (Speech Asli Lengkap Bebas Filler & Tawa) -->
                        <div x-show="editingId !== {{ $segment->id }}" class="text-sm text-slate-900 leading-relaxed">
                            <p data-clean-text class="{{ $segment->isEdited() ? 'font-medium text-slate-900' : 'text-slate-800' }}">
                                {{ $cleanText }}
                            </p>

                            <!-- Sub-teks Mentah STT (Muncul jika toggle aktif atau teks diedit) -->
                            <div x-show="showRawText || {{ $segment->isEdited() ? 'true' : 'false' }}" class="mt-2 pt-1.5 border-t border-slate-100 text-xs text-slate-400 font-mono space-y-0.5">
                                <span class="text-[10px] uppercase font-bold text-slate-400">Teks STT Mentah Asli:</span>
                                <p class="text-slate-500 italic">
                                    "{{ $rawText }}"
                                </p>
                            </div>
                        </div>

                        <!-- Mode Penyuntingan Inline -->
                        @if($canEdit)
                            <div x-show="editingId === {{ $segment->id }}" style="display: none;" class="space-y-3 pt-1">
                                <form method="POST" action="{{ route('meetings.transcript.update', [$meeting, $segment]) }}" class="space-y-3">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label for="edit-textarea-{{ $segment->id }}" class="block text-xs font-semibold text-slate-700 mb-1">
                                            Koreksi Transkrip Segmen (Teks mentah asli tetap aman tersimpan)
                                        </label>
                                        <textarea
                                            id="edit-textarea-{{ $segment->id }}"
                                            name="edited_text"
                                            rows="3"
                                            x-model="editText"
                                            required
                                            class="block w-full rounded-xl border border-slate-300 bg-white p-3 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600 focus:border-indigo-600"
                                        ></textarea>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="submit"
                                            class="inline-flex min-h-[36px] items-center rounded-xl bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-indigo-700 active:scale-95 transition cursor-pointer"
                                        >
                                            💾 Simpan Perubahan
                                        </button>
                                        <button
                                            type="button"
                                            @click="cancelEdit()"
                                            class="inline-flex min-h-[36px] items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 cursor-pointer"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-12 text-center text-xs text-slate-500 space-y-2">
                        <span class="text-3xl block">🎙️</span>
                        <p class="font-bold text-slate-700">Belum ada transkrip ucapan yang tersimpan.</p>
                        <p class="text-slate-400">Transkrip akan muncul secara otomatis setelah rekaman audio selesai diproses.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 2: VERSI RINGKAS (Notulen & Rangkuman Rapat) -->
        <!-- ========================================================================= -->
        <div x-show="tab === 'summary'" style="display: none;" class="space-y-6">
            @if($latestMinute)
                @php
                    $content = $latestMinute->content_json ?? [];
                    $summary = $content['ringkasan'] ?? '';
                    $agenda = $content['agenda'] ?? [];
                    $discussion = $content['pembahasan'] ?? '';
                    $decisions = $content['keputusan'] ?? [];
                    $followups = $latestMinute->actionItems->isNotEmpty()
                        ? $latestMinute->actionItems
                        : ($content['tindak_lanjut'] ?? []);
                @endphp

                <!-- Header Notulen Ringkas Banner -->
                <div class="rounded-2xl bg-emerald-50/70 border border-emerald-200/80 p-5 text-xs text-emerald-950 flex flex-wrap items-center justify-between gap-4 shadow-2xs">
                    <div class="flex items-start gap-3">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-600 text-white font-bold text-base shrink-0 shadow-xs">
                            📋
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-emerald-900">Versi Ringkas: Notulen & Keputusan Rapat</span>
                                @if($latestMinute->isFinal())
                                    <span class="inline-flex items-center rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800">
                                        Versi {{ $latestMinute->version }} (Final)
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">
                                        Versi {{ $latestMinute->version }} (Draf Notulen)
                                    </span>
                                @endif
                            </div>
                            <p class="text-emerald-800 text-xs mt-0.5">
                                Intisari rapat terstruktur berstandar dinas: ringkasan eksekutif, butir keputusan, dan penanggung jawab tindak lanjut.
                            </p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <a
                            href="{{ route('meetings.minutes.version.pdf', ['meeting' => $meeting, 'minute' => $latestMinute]) }}"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3.5 py-2 text-xs font-bold text-indigo-700 shadow-2xs hover:bg-indigo-100 transition"
                        >
                            <span>📄</span>
                            <span>Cetak PDF Resmi</span>
                        </a>

                        <a
                            href="{{ route('meetings.minutes.show', $meeting) }}"
                            class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700 transition"
                        >
                            <span>📋</span>
                            <span>Buka Halaman Notulen</span>
                        </a>
                    </div>
                </div>

                <!-- Konten Notulen Ringkas -->
                <div class="grid grid-cols-1 gap-6">
                    <!-- Ringkasan Eksekutif -->
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-3">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-indigo-900 flex items-center gap-2">
                            <span>📌</span>
                            <span>Ringkasan Eksekutif</span>
                        </h3>
                        <p class="text-sm text-slate-800 leading-relaxed font-sans">
                            {{ $summary ?: 'Belum ada ringkasan yang dihasilkan.' }}
                        </p>
                    </div>

                    <!-- Agenda Rapat (Bila Ada) -->
                    @if(!empty($agenda))
                        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-3">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2">
                                <span>📅</span>
                                <span>Agenda Rapat</span>
                            </h3>
                            <ul class="list-disc list-inside space-y-1.5 text-sm text-slate-700">
                                @foreach($agenda as $item)
                                    <li>{{ is_array($item) ? json_encode($item) : $item }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Butir Keputusan Rapat -->
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-3">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-emerald-900 flex items-center gap-2">
                            <span>✅</span>
                            <span>Butir Keputusan Rapat</span>
                        </h3>
                        @if(!empty($decisions))
                            <div class="space-y-2">
                                @foreach($decisions as $dec)
                                    <div class="flex items-start gap-2.5 rounded-xl bg-emerald-50/50 p-3 border border-emerald-100 text-sm text-emerald-950">
                                        <span class="text-emerald-600 font-bold">✓</span>
                                        <p class="leading-relaxed">{{ is_array($dec) ? json_encode($dec) : $dec }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-slate-400 italic">Tidak ada butir keputusan khusus yang tercatat.</p>
                        @endif
                    </div>

                    <!-- Tindak Lanjut & Penanggung Jawab -->
                    <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-3">
                        <h3 class="text-sm font-bold uppercase tracking-wider text-amber-900 flex items-center gap-2">
                            <span>🎯</span>
                            <span>Tindak Lanjut & Penanggung Jawab</span>
                        </h3>
                        @if(!empty($followups) && count($followups) > 0)
                            <div class="overflow-x-auto">
                                <table class="w-full text-left text-xs text-slate-700 border-collapse">
                                    <thead>
                                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[11px]">
                                            <th class="py-2.5 px-3">Uraian Tugas / Tindak Lanjut</th>
                                            <th class="py-2.5 px-3 w-44">Penanggung Jawab</th>
                                            <th class="py-2.5 px-3 w-32">Tenggat Waktu</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100">
                                        @foreach($followups as $item)
                                            @php
                                                $desc = is_object($item) ? $item->description : ($item['description'] ?? '');
                                                $assignee = is_object($item) ? ($item->assignee?->name ?? $item->assignee_name) : ($item['assignee_name'] ?? '-');
                                                $dueDate = is_object($item) ? ($item->due_date?->format('d/m/Y') ?? '-') : ($item['due_date'] ?? '-');
                                            @endphp
                                            <tr>
                                                <td class="py-3 px-3 font-medium text-slate-900">{{ $desc }}</td>
                                                <td class="py-3 px-3 text-slate-600 font-semibold">{{ $assignee ?: '-' }}</td>
                                                <td class="py-3 px-3 text-slate-500 font-mono">{{ $dueDate ?: '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <p class="text-xs text-slate-400 italic">Tidak ada tindak lanjut khusus yang ditetapkan.</p>
                        @endif
                    </div>

                    <!-- Pembahasan Rinci (Bila Ada) -->
                    @if(!empty($discussion))
                        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 sm:p-6 shadow-2xs space-y-3">
                            <h3 class="text-sm font-bold uppercase tracking-wider text-slate-800 flex items-center gap-2">
                                <span>💬</span>
                                <span>Pembahasan Rapat</span>
                            </h3>
                            <div class="text-sm text-slate-700 leading-relaxed whitespace-pre-line">
                                {{ $discussion }}
                            </div>
                        </div>
                    @endif
                </div>
            @else
                <!-- State: Notulen Belum Ada / Dalam Proses -->
                <div class="rounded-2xl border border-slate-200 bg-white p-12 text-center space-y-3">
                    <span class="text-4xl block">📋</span>
                    <h3 class="text-base font-bold text-slate-800">Versi Ringkas (Notulen) Belum Tersedia</h3>
                    <p class="text-xs text-slate-500 max-w-md mx-auto">
                        Notulen rapat sedang dalam proses transkripsi atau antrean pemrosesan kecerdasan buatan. Begitu selesai, intisari rapat siap cetak akan tampil di tab ini.
                    </p>
                </div>
            @endif
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 3: EVALUASI PERBEDAAN (Diff View) -->
        <!-- ========================================================================= -->
        <div x-show="tab === 'diff'" style="display: none;" class="space-y-5">
            <!-- Panel Statistik Evaluasi Pilot & Persentase Selisih -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-4">
                <div class="border-b border-slate-100 pb-3">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500">Evaluasi Koreksi Manual (Kriteria Pilot)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Ukuran tingkat perbedaan antara hasil STT otomatis dengan penyuntingan notulis.</p>
                </div>

                <!-- Kartu Metrik Selisih -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                    <div class="rounded-xl bg-indigo-50/60 p-3 border border-indigo-100/80">
                        <span class="text-slate-500 block">Persentase Selisih</span>
                        <span class="text-xl font-extrabold text-indigo-700 block mt-1">
                            {{ $stats['difference_percent'] }}%
                        </span>
                        <span class="text-[10px] text-indigo-500">Tingkat koreksi teks</span>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                        <span class="text-slate-500 block">Segmen Diubah</span>
                        <span class="text-xl font-bold text-slate-800 block mt-1">
                            {{ $stats['edited_segments'] }} <span class="text-xs font-normal text-slate-500">/ {{ $stats['total_segments'] }}</span>
                        </span>
                        <span class="text-[10px] text-slate-400">({{ $stats['edited_segments_percent'] }}% segmen disunting)</span>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                        <span class="text-slate-500 block">Kata Asli (STT)</span>
                        <span class="text-xl font-bold text-slate-800 block mt-1">
                            {{ $stats['total_words_original'] }}
                        </span>
                        <span class="text-[10px] text-slate-400">Total kata mentah</span>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                        <span class="text-slate-500 block">Kata Hasil Edit</span>
                        <span class="text-xl font-bold text-slate-800 block mt-1">
                            {{ $stats['total_words_edited'] }}
                        </span>
                        <span class="text-[10px] text-slate-400">Teks setelah diperbaiki</span>
                    </div>
                </div>
            </div>

            <!-- Header Kolom Komparasi -->
            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xs">
                <div class="grid grid-cols-1 md:grid-cols-2 bg-slate-50 border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600">
                    <div class="p-3.5 border-b md:border-b-0 md:border-r border-slate-200 flex items-center justify-between">
                        <span>🤖 Teks Asli (AI STT Mentah)</span>
                        <span class="text-[10px] font-normal lowercase text-slate-400">tidak berubah</span>
                    </div>
                    <div class="p-3.5 flex items-center justify-between">
                        <span>✍️ Teks Hasil Edit / Bersih</span>
                        <span class="text-[10px] font-normal lowercase text-slate-400">aktif digunakan</span>
                    </div>
                </div>

                <!-- Baris Perbandingan -->
                <div class="divide-y divide-slate-100">
                    @forelse($segments as $segment)
                        <div class="grid grid-cols-1 md:grid-cols-2 text-xs sm:text-sm {{ $segment->isEdited() ? 'bg-amber-50/20' : '' }}">
                            <!-- Kolom Teks Asli -->
                            <div class="p-4 border-b md:border-b-0 md:border-r border-slate-100 space-y-1.5">
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span class="font-mono">⏱ {{ $segment->getFormattedStartTime() }} - {{ $segment->getFormattedEndTime() }}</span>
                                    <span>Segmen #{{ $loop->iteration }}</span>
                                </div>
                                <p class="text-slate-700 leading-relaxed font-mono text-xs">
                                    {{ $segment->text }}
                                </p>
                            </div>

                            <!-- Kolom Teks Hasil Edit -->
                            <div class="p-4 space-y-1.5 {{ $segment->isEdited() ? 'bg-emerald-50/30' : '' }}">
                                <div class="flex items-center justify-between text-[11px]">
                                    @if($segment->isEdited())
                                        <span class="inline-flex items-center rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                            ✓ Telah Diperbaiki
                                        </span>
                                    @else
                                        <span class="text-slate-400 text-[10px] italic">Sama dengan asli</span>
                                    @endif

                                    @if($segment->editor)
                                        <span class="text-[10px] text-slate-400">{{ $segment->editor->name }}</span>
                                    @elseif($segment->isEdited())
                                        <span class="text-[10px] text-slate-400">Penyuntingan Otomatis (AI)</span>
                                    @endif
                                </div>
                                <p class="text-slate-900 leading-relaxed {{ $segment->isEdited() ? 'font-medium' : 'text-slate-600' }}">
                                    {{ $segment->getEffectiveText() }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-xs text-slate-500">
                            Belum ada transkrip yang dapat dibandingkan.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</x-layouts.app>
