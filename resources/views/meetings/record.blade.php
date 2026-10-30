<x-layouts.app :title="'Rekam Audio - ' . $meeting->title">
    <div
        x-data="meetingRecorder({
            meetingId: {{ $meeting->id }},
            csrfToken: '{{ csrf_token() }}',
            endpoints: {
                start: '{{ route('meetings.record.start', $meeting) }}',
                upload: '{{ route('meetings.record.segments', $meeting) }}',
                finish: '{{ route('meetings.record.finish', $meeting) }}'
            },
            maxDurationMinutes: {{ $maxDurationMinutes }},
            initialAvailableQuotaMinutes: {{ $availableQuotaMinutes }},
            gracePeriodMinutes: {{ $gracePeriodMinutes }},
            quotaStatusUrl: '{{ route('quota.status') }}',
            quotaBuyUrl: '{{ route('quota.index') }}'
        })"
        class="space-y-6 max-w-lg mx-auto relative"
    >
        <!-- Header & Nav -->
        <div class="flex items-center justify-between gap-2">
            <div>
                <a href="{{ route('meetings.show', $meeting) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800 flex items-center gap-1">
                    <span>←</span> Kembali ke Rapat
                </a>
                <h1 class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl mt-1">
                    Perekaman Audio Rapat
                </h1>
                <p class="text-xs text-slate-500 truncate max-w-[200px] sm:max-w-xs">
                    {{ $meeting->title }}
                </p>
            </div>

            <!-- Badges: Quota & Online/Offline -->
            <div class="flex flex-col items-end gap-1.5">
                <!-- Quota Pill -->
                <div class="flex items-center gap-1.5">
                    <button
                        @click="showTopUpModal = true"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[11px] font-semibold transition"
                        :class="inGracePeriod ? 'bg-rose-100 text-rose-800 border border-rose-300' : (availableQuotaMinutes <= 15 ? 'bg-amber-100 text-amber-800 border border-amber-300' : 'bg-slate-100 text-slate-700 border border-slate-200 hover:bg-slate-200')"
                    >
                        <span>⏱️</span>
                        <span x-text="inGracePeriod ? 'Tenggang: ' + formattedGraceLeft : 'Sisa: ' + availableQuotaMinutes + ' m'"></span>
                        <span class="text-indigo-600 underline text-[10px] ml-0.5">+Top-up</span>
                    </button>
                </div>

                <!-- Online / Offline Badge -->
                <div>
                    <span
                        x-show="isOnline"
                        class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Online
                    </span>
                    <span
                        x-show="!isOnline"
                        x-cloak
                        class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 ring-1 ring-rose-600/20"
                    >
                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                        Offline
                    </span>
                </div>
            </div>
        </div>

        <!-- Early Quota Warning Banner (<= 15 mins) -->
        <div
            x-show="!inGracePeriod && availableQuotaMinutes > 0 && availableQuotaMinutes <= 15"
            x-cloak
            class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 flex items-center justify-between gap-3 shadow-2xs"
        >
            <div class="space-y-0.5">
                <div class="font-bold flex items-center gap-1.5 text-amber-900">
                    <span>⚠️</span> Peringatan: Kuota Rekam Tersisa <span x-text="availableQuotaMinutes"></span> Menit
                </div>
                <p class="text-amber-800">
                    Rekaman tetap berjalan normal. Anda disarankan menambah kuota sekarang agar rapat dapat berlanjut tanpa jeda.
                </p>
            </div>
            <button
                @click="showTopUpModal = true"
                type="button"
                class="shrink-0 rounded-xl bg-amber-600 px-3 py-1.5 font-bold text-white shadow-2xs hover:bg-amber-700 transition"
            >
                + Tambah Kuota
            </button>
        </div>

        <!-- Grace Period Urgent Banner (Quota 0, 15m Buffer Active) -->
        <div
            x-show="inGracePeriod"
            x-cloak
            class="rounded-2xl border-2 border-rose-400 bg-rose-50 p-4 text-xs text-rose-950 space-y-2 shadow-sm animate-pulse"
        >
            <div class="flex items-center justify-between">
                <div class="font-bold flex items-center gap-1.5 text-rose-900 text-sm">
                    <span>⏰</span> Masa Toleransi Darurat Aktif!
                </div>
                <span class="font-mono font-bold text-base text-rose-700 bg-white px-2.5 py-0.5 rounded-lg border border-rose-200" x-text="formattedGraceLeft"></span>
            </div>
            <p class="text-rose-800 leading-relaxed">
                Kuota resmi organisasi telah habis, namun audio <strong>TETAP MEREKAM NORMAL</strong> selama masa tenggang 15 menit agar pembicaraan penting tidak terputus. Silakan selesaikan perpanjangan kuota sebelum waktu tenggang habis.
            </p>
            <div class="flex items-center gap-2 pt-1">
                <button
                    @click="showTopUpModal = true"
                    type="button"
                    class="rounded-xl bg-rose-600 px-3.5 py-1.5 font-bold text-white shadow-2xs hover:bg-rose-700 transition text-xs"
                >
                    ⚡ Beli Kuota Sekarang
                </button>
                <button
                    @click="checkQuota()"
                    type="button"
                    class="rounded-xl border border-rose-300 bg-white px-3 py-1.5 font-semibold text-rose-700 hover:bg-rose-50 transition text-xs"
                >
                    🔄 Cek Pembayaran
                </button>
            </div>
        </div>

        <!-- Insecure Context Warning -->
        <div x-show="!isSecure" x-cloak class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-900 space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-amber-800">
                <span>⚠️</span> Akses Non-HTTPS Terdeteksi
            </div>
            <p>
                Browser memerlukan koneksi aman (HTTPS) untuk mengizinkan mikrofon dan Screen Wake Lock. Jalankan aplikasi menggunakan HTTPS atau akses melalui localhost.
            </p>
        </div>

        <!-- Error Notification -->
        <div x-show="errorMessage" x-cloak class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 space-y-1">
            <div class="font-bold flex items-center gap-1.5 text-rose-900">
                <span>⚠️</span> Terjadi Kendala
            </div>
            <p x-text="errorMessage"></p>
        </div>

        <!-- Main Studio Card -->
        <div class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-6">
            <!-- Recording State Badge & Wake Lock Pill -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span
                        x-show="state === 'idle'"
                        class="inline-flex items-center rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-slate-700"
                    >
                        Siap Merekam
                    </span>
                    <span
                        x-show="state === 'recording'"
                        x-cloak
                        class="inline-flex items-center gap-2 rounded-lg bg-rose-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-rose-700 ring-1 ring-rose-600/20"
                    >
                        <span class="h-2 w-2 rounded-full bg-rose-600 animate-ping"></span>
                        Sedang Merekam
                    </span>
                    <span
                        x-show="state === 'paused'"
                        x-cloak
                        class="inline-flex items-center rounded-lg bg-amber-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-amber-700 ring-1 ring-amber-600/20"
                    >
                        ⏸ Dijeda
                    </span>
                    <span
                        x-show="state === 'finishing'"
                        x-cloak
                        class="inline-flex items-center gap-2 rounded-lg bg-indigo-50 px-3 py-1 text-xs font-bold uppercase tracking-wider text-indigo-700 ring-1 ring-indigo-600/20"
                    >
                        <svg class="animate-spin h-3 w-3 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        Menyelesaikan & Mengunggah...
                    </span>
                </div>

                <div class="text-right">
                    <span class="text-[11px] font-medium text-slate-400">
                        Format: <span class="text-slate-600 font-mono" x-text="supportedMime ? supportedMime.split(';')[0] : 'default'"></span>
                    </span>
                </div>
            </div>

            <!-- Big Digital Timer Display -->
            <div class="text-center py-4">
                <div class="font-mono text-5xl sm:text-6xl font-extrabold tracking-tight text-slate-900" x-text="formattedDuration">
                    00:00:00
                </div>
                <p class="text-xs text-slate-400 mt-2">
                    Batas durasi maksimum: <span class="font-medium text-slate-600">{{ $maxDurationMinutes }} menit</span>
                </p>
            </div>

            <!-- Live Sound Level Meter (AnalyserNode) -->
            <div class="space-y-1.5">
                <div class="flex items-center justify-between text-xs text-slate-500 font-medium">
                    <span class="flex items-center gap-1">
                        <span>🎙️</span> Level Mikrofon
                    </span>
                    <span class="font-mono text-[11px]" x-text="soundLevel + '%'"></span>
                </div>
                <div class="h-3 w-full bg-slate-100 rounded-full overflow-hidden p-0.5 border border-slate-200">
                    <div
                        class="h-full rounded-full transition-all duration-75 ease-out"
                        :style="'width: ' + soundLevel + '%; background-color: ' + (soundLevel > 75 ? '#ef4444' : (soundLevel > 30 ? '#10b981' : '#6366f1'))"
                    ></div>
                </div>
                <p class="text-[10px] text-slate-400 text-center">
                    Pastikan indikator bergerak hijau saat peserta rapat berbicara.
                </p>
            </div>

            <!-- Upload Queue & Segments Status Card -->
            <div class="rounded-2xl bg-slate-50 p-4 border border-slate-200/80 space-y-2">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-600 font-medium">Segmen Aktif:</span>
                    <span class="font-bold text-slate-800" x-text="'Segmen #' + currentSeq"></span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-600 font-medium">Tersimpan di Server:</span>
                    <span class="font-bold text-emerald-700" x-text="confirmedUploads + ' segmen'"></span>
                </div>
                <div class="flex items-center justify-between text-xs">
                    <span class="text-slate-600 font-medium">Antrean Lokal (IndexedDB):</span>
                    <span class="font-bold" :class="pendingUploads > 0 ? 'text-amber-700' : 'text-slate-500'" x-text="pendingUploads + ' segmen menunggu'"></span>
                </div>
            </div>

            <!-- Action Buttons Controls (Large Touch Targets) -->
            <div class="space-y-3 pt-2">
                <!-- Initial Start Button -->
                <button
                    x-show="state === 'idle'"
                    @click="startRecording()"
                    type="button"
                    class="w-full flex items-center justify-center gap-2 rounded-2xl bg-rose-600 px-6 py-4 text-base font-bold text-white shadow-md hover:bg-rose-700 active:scale-[0.99] transition"
                >
                    <span class="h-3.5 w-3.5 rounded-full bg-white animate-pulse"></span>
                    Mulai Rekam Rapat
                </button>

                <!-- Recording Controls: Pause & Finish -->
                <div x-show="state === 'recording'" x-cloak class="grid grid-cols-2 gap-3">
                    <button
                        @click="pauseRecording()"
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-2xl border border-slate-300 bg-white px-4 py-3.5 text-sm font-bold text-slate-700 shadow-2xs hover:bg-slate-50 active:scale-[0.99] transition"
                    >
                        <span>⏸</span> Jeda
                    </button>
                    <button
                        @click="finishRecording()"
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-3.5 text-sm font-bold text-white shadow-2xs hover:bg-indigo-700 active:scale-[0.99] transition"
                    >
                        <span>⏹</span> Selesai Rekam
                    </button>
                </div>

                <!-- Paused Controls: Resume & Finish -->
                <div x-show="state === 'paused'" x-cloak class="grid grid-cols-2 gap-3">
                    <button
                        @click="resumeRecording()"
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-600 px-4 py-3.5 text-sm font-bold text-white shadow-2xs hover:bg-emerald-700 active:scale-[0.99] transition"
                    >
                        <span>▶️</span> Lanjutkan
                    </button>
                    <button
                        @click="finishRecording()"
                        type="button"
                        class="flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-3.5 text-sm font-bold text-white shadow-2xs hover:bg-indigo-700 active:scale-[0.99] transition"
                    >
                        <span>⏹</span> Selesai Rekam
                    </button>
                </div>

                <!-- Finishing Spinner Button -->
                <button
                    x-show="state === 'finishing'"
                    x-cloak
                    disabled
                    type="button"
                    class="w-full flex items-center justify-center gap-2 rounded-2xl bg-indigo-400 px-6 py-4 text-base font-bold text-white cursor-not-allowed"
                >
                    <svg class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                    </svg>
                    Menyelesaikan Perekaman...
                </button>
            </div>
        </div>

        <!-- Critical Mobile Backgrounding Warning -->
        <div class="rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-900 space-y-2">
            <div class="flex items-center gap-1.5 font-bold text-amber-900">
                <span>⚠️</span> Peringatan Penting Pengoperasian
            </div>
            <p class="leading-relaxed">
                <strong>Jangan matikan layar secara manual atau berpindah aplikasi</strong> selama proses perekaman. Pada iPhone (Safari iOS) dan beberapa ponsel Android, sistem operasi dapat memutus rekaman mikrofon saat browser berjalan di latar belakang.
            </p>
            <p class="text-[11px] text-amber-800">
                Aplikasi ini otomatis mengaktifkan <em>Screen Wake Lock</em> agar layar tetap menyala.
            </p>
        </div>

        <!-- Microphone Placement Guide -->
        <div class="rounded-2xl border border-slate-200 bg-white p-4 text-xs text-slate-700 space-y-2">
            <div class="flex items-center gap-1.5 font-bold text-slate-900">
                <span>💡</span> Panduan Penempatan Ponsel di Ruang Rapat
            </div>
            <ul class="list-disc list-inside space-y-1 text-slate-600">
                <li>Letakkan ponsel di <strong>tengah meja rapat</strong> dengan jarak yang wajar ke semua pembicara.</li>
                <li>Arahkan mikrofon ponsel ke tengah ruangan dan hindari meletakkan dokumen di atas ponsel.</li>
                <li>Jauhkan dari sumber bising konstan seperti kipas angin, pendingin ruangan (AC), atau laptop berisik.</li>
                <li>Sangat disarankan mengaktifkan mode <strong>Jangan Ganggu (Do Not Disturb / DND)</strong> agar panggilan telepon masuk tidak menginterupsi rekaman.</li>
            </ul>
        </div>

        <!-- Quick Top-Up In-Session Modal (Non-Blocking) -->
        <div
            x-show="showTopUpModal"
            x-cloak
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs"
            @keydown.escape.window="showTopUpModal = false"
        >
            <div
                @click.away="showTopUpModal = false"
                class="w-full max-w-md bg-white rounded-3xl p-6 shadow-2xl border border-slate-200 space-y-5"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-wider text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-md">Top-Up Cepat</span>
                        <h3 class="text-lg font-bold text-slate-900 mt-1">Perpanjang Kuota Rekam</h3>
                        <p class="text-xs text-slate-500">Audio Anda tetap merekam di latar belakang saat jendela ini terbuka.</p>
                    </div>
                    <button
                        @click="showTopUpModal = false"
                        type="button"
                        class="text-slate-400 hover:text-slate-600 text-lg leading-none"
                    >
                        ✕
                    </button>
                </div>

                <div class="rounded-2xl bg-slate-50 p-4 border border-slate-200/80 space-y-2 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Sisa Kuota Saat Ini:</span>
                        <span class="font-bold text-slate-900" x-text="availableQuotaMinutes + ' Menit'"></span>
                    </div>
                    <div x-show="inGracePeriod" class="flex justify-between text-rose-600 font-semibold">
                        <span>Masa Toleransi Tersisa:</span>
                        <span class="font-mono font-bold" x-text="formattedGraceLeft"></span>
                    </div>
                </div>

                <div class="space-y-2">
                    <p class="text-xs font-semibold text-slate-700">Pilih Opsi Pembelian:</p>
                    <a
                        :href="quotaBuyUrl"
                        target="_blank"
                        class="w-full flex items-center justify-between p-3.5 rounded-2xl border border-indigo-200 bg-indigo-50/50 hover:bg-indigo-50 transition text-xs group"
                    >
                        <div class="text-left">
                            <span class="font-bold text-indigo-900 block text-sm">Buka Halaman Beli Kuota</span>
                            <span class="text-indigo-600 text-[11px]">Buka tab baru untuk memilih paket jam & bayar &rarr;</span>
                        </div>
                        <span class="text-xl">💳</span>
                    </a>
                </div>

                <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button
                        @click="checkQuota(); alert('Status kuota berhasil disinkronkan!')"
                        type="button"
                        class="flex-1 py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition"
                    >
                        🔄 Sinkronkan Kuota
                    </button>
                    <button
                        @click="showTopUpModal = false"
                        type="button"
                        class="flex-1 py-2.5 px-3 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-xs font-bold text-white transition"
                    >
                        Tutup & Kembali
                    </button>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
