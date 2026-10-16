<x-layouts.app :title="'Layar QR Presensi - ' . $meeting->title">
    <div
        x-data="{
            meetingId: {{ $meeting->id }},
            code: '{{ $initialCode }}',
            url: '{{ $initialUrl }}',
            secondsLeft: {{ $secondsRemaining }},
            totalInvited: {{ $attendees->where('is_guest', false)->count() }},
            totalPresent: {{ $attendees->whereIn('status', ['hadir', 'terlambat'])->count() }},
            pendingCount: {{ $attendees->where('is_guest', true)->where('guest_status', 'pending')->count() }},
            attendees: @js($attendees->whereNotNull('checked_in_at')->values()),
            timerInterval: null,
            pollInterval: null,
            copied: false,

            init() {
                this.renderQr(this.url);

                // Local countdown timer
                this.timerInterval = setInterval(() => {
                    if (this.secondsLeft > 1) {
                        this.secondsLeft--;
                    } else {
                        this.pollData(); // immediately fetch new window code
                    }
                }, 1000);

                // Light polling every 5s for attendee counter & live scan list
                this.pollInterval = setInterval(() => {
                    this.pollData();
                }, 5000);
            },

            renderQr(text) {
                this.$nextTick(() => {
                    const canvas = document.getElementById('qr-canvas');
                    if (canvas && window.QRCode) {
                        window.QRCode.toCanvas(canvas, text, {
                            width: 260,
                            margin: 2,
                            color: {
                                dark: '#0f172a',
                                light: '#ffffff'
                            }
                        });
                    }
                });
            },

            async pollData() {
                try {
                    const res = await fetch('{{ route('meetings.attendance.qr_data', $meeting) }}', {
                        headers: { 'Accept': 'application/json' }
                    });
                    if (res.ok) {
                        const data = await res.json();
                        if (this.code !== data.code) {
                            this.code = data.code;
                            this.url = data.url;
                            this.renderQr(data.url);
                        }
                        this.secondsLeft = data.seconds_remaining;
                        this.totalInvited = data.total_invited;
                        this.totalPresent = data.total_present;
                        this.pendingCount = data.pending_guests_count;
                        this.attendees = data.attendees;
                    }
                } catch (e) {
                    console.error('Polling error:', e);
                }
            },

            copyUrl() {
                navigator.clipboard.writeText(this.url).then(() => {
                    this.copied = true;
                    setTimeout(() => { this.copied = false; }, 2000);
                });
            }
        }"
        class="space-y-6"
    >
        <!-- Top Bar Navigation -->
        <div class="flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('meetings.show', $meeting) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Detail Rapat
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-0.5">
                    Layar QR Presensi
                </h1>
                <p class="text-xs text-slate-500">{{ $meeting->title }}</p>
            </div>

            <div class="flex items-center gap-2">
                <a
                    href="{{ route('meetings.attendance.recap', $meeting) }}"
                    class="inline-flex min-h-[40px] items-center rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
                >
                    📊 Rekap Kehadiran
                </a>
            </div>
        </div>

        @if($meeting->show_recorded_notice)
            <div class="rounded-xl bg-amber-50 p-3 border border-amber-200 text-xs text-amber-800 font-medium flex items-center justify-between">
                <span>🎙️ <strong>Pemberitahuan:</strong> Rapat direkam untuk notulensi.</span>
                <span class="text-[11px] text-amber-600">Label resmi aktif</span>
            </div>
        @endif

        @if($meeting->absensi_closed_at)
            <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-xs text-rose-800">
                🔒 <strong>Absensi Sudah Ditutup</strong> pada {{ \Carbon\Carbon::parse($meeting->absensi_closed_at)->translatedFormat('d M Y, H:i') }} WIB.
            </div>
        @endif

        <!-- Main QR Display Card (Projector / Screen Centered) -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-6 shadow-xs text-center space-y-4">
            <div class="space-y-1">
                <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-600/20">
                    <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse mr-1.5"></span> Sesi Presensi Aktif
                </span>
                <h2 class="text-lg font-bold text-slate-900">Scan QR untuk Melakukan Presensi</h2>
                <p class="text-xs text-slate-500">Arahkan kamera ponsel Anda ke kode QR di bawah ini</p>
            </div>

            <!-- Canvas Container -->
            <div class="relative inline-block mx-auto p-4 rounded-2xl bg-slate-50 border border-slate-200 shadow-inner">
                <canvas id="qr-canvas" class="mx-auto rounded-xl"></canvas>
            </div>

            <!-- Rotating Timer Badge -->
            <div class="flex items-center justify-center gap-2 text-xs font-medium text-slate-600">
                <svg class="h-4 w-4 text-indigo-600 animate-spin" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>QR berganti dalam <strong class="text-indigo-600 font-bold" x-text="secondsLeft"></strong> detik</span>
            </div>

            <!-- Copy Link Button for participants without camera or for host sharing -->
            <div class="pt-2">
                <button
                    type="button"
                    @click="copyUrl()"
                    class="inline-flex min-h-[42px] items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer shadow-2xs"
                >
                    <span x-text="copied ? '✅ Tautan Tersalin!' : '🔗 Salin Tautan Presensi'"></span>
                </button>
            </div>
        </div>

        <!-- Live Counters Grid -->
        <div class="grid grid-cols-3 gap-3">
            <div class="rounded-xl border border-slate-200 bg-white p-3.5 text-center">
                <span class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Undangan</span>
                <span class="text-xl font-bold text-slate-800" x-text="totalInvited"></span>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-emerald-50/50 p-3.5 text-center">
                <span class="block text-[11px] font-semibold text-emerald-600 uppercase tracking-wider">Hadir</span>
                <span class="text-xl font-bold text-emerald-700" x-text="totalPresent"></span>
            </div>

            <div class="rounded-xl border border-amber-100 bg-amber-50/50 p-3.5 text-center">
                <span class="block text-[11px] font-semibold text-amber-600 uppercase tracking-wider">Tamu Pending</span>
                <span class="text-xl font-bold text-amber-700" x-text="pendingCount"></span>
            </div>
        </div>

        <!-- Live Scan Feed -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-3">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <h3 class="text-sm font-bold text-slate-900">Daftar Terkini yang Sudah Scan</h3>
                <span class="text-xs text-slate-400">Pembaruan tiap 5 detik</span>
            </div>

            <div class="divide-y divide-slate-100 max-h-72 overflow-y-auto pr-1">
                <template x-for="person in attendees" :key="person.id">
                    <div class="flex items-center justify-between py-2.5">
                        <div class="min-w-0 pr-3">
                            <span class="font-semibold text-xs text-slate-900 truncate block" x-text="person.name"></span>
                            <span class="text-[11px] text-slate-400 truncate block" x-text="person.position || 'Peserta'"></span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <template x-if="person.status === 'terlambat'">
                                <span class="rounded-md bg-amber-50 px-2 py-0.5 text-[10px] font-bold text-amber-700">Terlambat</span>
                            </template>
                            <template x-if="person.status === 'hadir'">
                                <span class="rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Hadir</span>
                            </template>
                            <template x-if="person.is_guest && person.guest_status === 'pending'">
                                <span class="rounded-md bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-800">Pending</span>
                            </template>
                            <span class="text-[11px] text-slate-400" x-text="person.method === 'manual' ? 'Manual' : 'QR'"></span>
                        </div>
                    </div>
                </template>

                <div x-show="attendees.length === 0" class="py-6 text-center text-xs text-slate-400">
                    Belum ada peserta yang melakukan presensi pada sesi ini.
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
