<x-layouts.app :title="'Transkrip - ' . $meeting->title . ' - ' . config('app.name', 'Notulensi Rapat')">
    <div x-data="{
        tab: 'editor',
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
        }
    }" class="space-y-6">

        <!-- Top Navigation -->
        <div class="flex items-start justify-between gap-4">
            <div>
                <a href="{{ route('meetings.show', $meeting) }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Detail Rapat
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                    Transkrip Mentah: {{ $meeting->title }}
                </h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 mt-1">
                    <span>📅 {{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('l, d F Y') }}</span>
                    @if($meeting->start_time)
                        <span>⏰ {{ substr($meeting->start_time, 0, 5) }} WIB</span>
                    @endif
                    <span>👥 Pembuat: {{ $meeting->creator?->name ?? 'Notulis' }}</span>
                </div>
            </div>

            <div class="flex items-center gap-2">
                @can('viewAudio', $meeting)
                    <a
                        href="{{ route('meetings.show', $meeting) }}"
                        class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
                    >
                        🎙️ Ke Pemutar Rapat
                    </a>
                @endcan
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

        <!-- Audio Player Section (Hanya bila audio masih ada & belum dihapus) -->
        @if($audioAvailable && $audioStreamUrl)
            <div class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-2xs space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 flex items-center gap-1.5">
                            🎧 Audio Rapat Terhubung
                        </h2>
                        <p class="text-[11px] text-slate-400 mt-0.5">
                            Klik stempel waktu pada kalimat untuk melompat langsung ke audio bagian tersebut.
                        </p>
                    </div>
                    <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-600/20">
                        Sinkronisasi Aktif
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
        @else
            <div class="rounded-xl bg-slate-50 p-3 text-xs text-slate-600 border border-slate-200 flex items-center justify-between">
                <span>🎧 Pemutar audio gabungan belum aktif (memerlukan ffmpeg di server). Transkrip teks hasil rekaman tetap tersimpan lengkap di bawah.</span>
                <span class="text-[11px] text-slate-500">Transkrip Lengkap</span>
            </div>
        @endif

        <!-- Panel Statistik Evaluasi Pilot & Persentase Selisih -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div>
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Evaluasi Koreksi Manual (Kriteria Pilot)</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Ukuran tingkat perbedaan antara hasil STT otomatis dengan penyuntingan notulis.</p>
                </div>

                <!-- Tab Switcher -->
                <div class="inline-flex rounded-xl bg-slate-100 p-1 text-xs font-medium">
                    <button
                        type="button"
                        @click="tab = 'editor'"
                        :class="tab === 'editor' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
                        class="rounded-lg px-3 py-1.5 transition"
                    >
                        📝 Transkrip & Editor
                    </button>
                    <button
                        type="button"
                        @click="tab = 'diff'"
                        :class="tab === 'diff' ? 'bg-white text-slate-900 shadow-2xs font-bold' : 'text-slate-500 hover:text-slate-900'"
                        class="rounded-lg px-3 py-1.5 transition"
                    >
                        ⚖️ Perbandingan Asli vs Edit
                    </button>
                </div>
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

        <!-- TAB 1: Transkrip & Editor -->
        <div x-show="tab === 'editor'" class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-bold text-slate-900">
                    Daftar Segmen Kalimat ({{ $segments->count() }})
                </h3>
                @if(!$canEdit)
                    <span class="text-xs text-slate-400 font-medium">Mode Baca Saja (Pimpinan)</span>
                @endif
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xs divide-y divide-slate-100">
                @forelse($segments as $segment)
                    <div class="p-4 sm:p-5 transition hover:bg-slate-50/50 space-y-2.5" id="segment-row-{{ $segment->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-2">
                                @if($audioAvailable && $audioStreamUrl)
                                    <button
                                        type="button"
                                        @click="seekAudio({{ $segment->start_sec }})"
                                        class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2 py-1 text-xs font-bold text-indigo-700 hover:bg-indigo-100 active:scale-95 transition"
                                        title="Lompat ke menit ini pada audio"
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
                                        Diedit @if($segment->editor) oleh {{ $segment->editor->name }} @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">
                                        Asli
                                    </span>
                                @endif
                            </div>

                            @if($canEdit)
                                <div class="flex items-center gap-2 shrink-0">
                                    <button
                                        type="button"
                                        x-show="editingId !== {{ $segment->id }}"
                                        @click="startEdit({{ $segment->id }}, '{{ addslashes($segment->getEffectiveText()) }}')"
                                        class="inline-flex items-center rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs"
                                    >
                                        ✏️ Sunting
                                    </button>

                                    @if($segment->isEdited())
                                        <form method="POST" action="{{ route('meetings.transcript.reset', [$meeting, $segment]) }}" onsubmit="return confirm('Kembalikan segmen ini ke teks asli hasil transkripsi?')">
                                            @csrf
                                            <button
                                                type="submit"
                                                class="inline-flex items-center rounded-lg border border-rose-200 bg-rose-50 px-2 py-1 text-xs font-medium text-rose-700 hover:bg-rose-100"
                                                title="Reset ke teks asli"
                                            >
                                                ↺ Reset
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <!-- Mode Tampilan Normal -->
                        <div x-show="editingId !== {{ $segment->id }}" class="text-sm text-slate-800 leading-relaxed">
                            <p class="{{ $segment->isEdited() ? 'font-medium text-slate-900' : 'text-slate-800' }}">
                                {{ $segment->getEffectiveText() }}
                            </p>
                            @if($segment->isEdited())
                                <p class="text-xs text-slate-400 mt-1 line-through">
                                    {{ $segment->text }}
                                </p>
                            @endif
                        </div>

                        <!-- Mode Penyuntingan Inline (Notulis Pembuat Saja) -->
                        @if($canEdit)
                            <div x-show="editingId === {{ $segment->id }}" style="display: none;" class="space-y-3 pt-1">
                                <form method="POST" action="{{ route('meetings.transcript.update', [$meeting, $segment]) }}" class="space-y-3">
                                    @csrf
                                    @method('PUT')
                                    <div>
                                        <label for="edit-textarea-{{ $segment->id }}" class="block text-xs font-semibold text-slate-700 mb-1">
                                            Perbaiki Teks Transkrip (Teks asli tetap aman tersimpan)
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
                                            class="inline-flex min-h-[36px] items-center rounded-xl bg-indigo-600 px-4 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-indigo-700 active:scale-95 transition"
                                        >
                                            💾 Simpan Perubahan
                                        </button>
                                        <button
                                            type="button"
                                            @click="cancelEdit()"
                                            class="inline-flex min-h-[36px] items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50"
                                        >
                                            Batal
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="p-8 text-center text-xs text-slate-500">
                        Belum ada segmen transkrip yang tersimpan untuk rapat ini.
                    </div>
                @endforelse
            </div>
        </div>

        <!-- TAB 2: Perbandingan Asli vs Hasil Edit (Diff View) -->
        <div x-show="tab === 'diff'" style="display: none;" class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-base font-bold text-slate-900">Perbandingan Teks Asli vs Hasil Koreksi</h3>
                    <p class="text-xs text-slate-500">Kolom kiri menampilkan transkrip mentah dari AI Whisper, kolom kanan menampilkan teks setelah disunting notulis.</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xs">
                <!-- Header Kolom -->
                <div class="grid grid-cols-1 md:grid-cols-2 bg-slate-50 border-b border-slate-200 text-xs font-bold uppercase tracking-wider text-slate-600">
                    <div class="p-3.5 border-b md:border-b-0 md:border-r border-slate-200 flex items-center justify-between">
                        <span>🤖 Teks Asli (AI STT Mentah)</span>
                        <span class="text-[10px] font-normal lowercase text-slate-400">tidak berubah</span>
                    </div>
                    <div class="p-3.5 flex items-center justify-between">
                        <span>✍️ Teks Hasil Edit (Notulis)</span>
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
