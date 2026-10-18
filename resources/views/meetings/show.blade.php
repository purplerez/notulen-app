<x-layouts.app :title="$meeting->title . ' - ' . config('app.name', 'Notulensi Rapat')">
    <div class="space-y-6">
        <!-- Top Navigation & Room Actions -->
        <div class="flex items-start justify-between gap-4">
            <div>
                <a href="{{ route('meetings.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Daftar Rapat
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                    {{ $meeting->title }}
                </h1>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500 mt-1">
                    <span>📅 {{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('l, d F Y') }}</span>
                    @if($meeting->start_time)
                        <span>⏰ {{ substr($meeting->start_time, 0, 5) }} WIB</span>
                    @endif
                    @if($meeting->location)
                        <span>📍 {{ $meeting->location }}</span>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @can('record', $meeting)
                    <a
                        href="{{ route('meetings.record', $meeting) }}"
                        class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl bg-rose-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-rose-700 active:scale-[0.98] transition"
                    >
                        🎙️ Rekam Rapat
                    </a>
                @endcan

                <a
                    href="{{ route('meetings.attendance.host', $meeting) }}"
                    class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-2 text-xs font-bold text-white shadow-xs hover:bg-indigo-700 active:scale-[0.98] transition"
                >
                    📱 Layar QR Absensi
                </a>

                <a
                    href="{{ route('meetings.attendance.recap', $meeting) }}"
                    class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
                >
                    📊 Rekap
                </a>

                @can('viewTranscript', $meeting)
                    <a
                        href="{{ route('meetings.transcript.show', $meeting) }}"
                        class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
                    >
                        📄 Transkrip Mentah
                    </a>
                @endcan


                @can('update', $meeting)
                    <a
                        href="{{ route('meetings.edit', $meeting) }}"
                        class="inline-flex min-h-[40px] items-center rounded-xl border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50"
                    >
                        ✏️ Ubah
                    </a>
                @endcan

                @can('delete', $meeting)
                    <form method="POST" action="{{ route('meetings.destroy', $meeting) }}" onsubmit="return confirm('Hapus room rapat ini? Seluruh data peserta terkait akan dihapus.')">
                        @csrf
                        @method('DELETE')
                        <button
                            type="submit"
                            class="inline-flex min-h-[40px] items-center rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100"
                        >
                            Hapus
                        </button>
                    </form>
                @endcan
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-sm text-rose-800 space-y-1">
                @foreach ($errors->all() as $error)
                    <p>⚠️ {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Card: Status & Metadata Room -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Status Room & Absensi</span>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-md px-2.5 py-1 text-xs font-bold uppercase tracking-wider
                        @if($meeting->status === 'draft') bg-slate-100 text-slate-700
                        @elseif($meeting->status === 'recording') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20 animate-pulse
                        @elseif($meeting->status === 'ready') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                        @else bg-blue-50 text-blue-700 @endif">
                        {{ $meeting->status }}
                    </span>

                    @if($meeting->absensi_closed_at)
                        <span class="inline-flex items-center rounded-md bg-rose-50 px-2 py-0.5 text-xs font-bold text-rose-700 ring-1 ring-rose-600/20">
                            Absensi Ditutup
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-600/20">
                            Absensi Terbuka
                        </span>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                    <span class="text-slate-400 block">Gaya Notulen</span>
                    <span class="font-semibold text-slate-800 capitalize">{{ $meeting->style }}</span>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                    <span class="text-slate-400 block">Batas Terlambat</span>
                    <span class="font-semibold text-slate-800">+{{ $meeting->late_after_minutes }} Menit</span>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                    <span class="text-slate-400 block">Kop & Tindak Lanjut</span>
                    <span class="font-semibold text-slate-800">{{ $meeting->use_kop ? 'Kop Aktif' : 'Tanpa Kop' }} • {{ $meeting->use_followup ? 'Tindak Lanjut' : 'Ringkas' }}</span>
                </div>
                <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                    <span class="text-slate-400 block">Pembuat Room</span>
                    <span class="font-semibold text-slate-800 truncate block">{{ $meeting->creator?->name ?? '-' }}</span>
                </div>
            </div>

            @if($meeting->show_recorded_notice)
                <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800 border border-amber-200/80 font-medium">
                    🎙️ Label resmi: <em>"Rapat direkam untuk notulensi"</em> aktif pada dokumen rapat.
                </div>
            @endif

            @if($meeting->signatories->isNotEmpty())
                <div class="pt-2">
                    <span class="text-xs font-semibold text-slate-500 block mb-1.5">Penandatangan Dokumen:</span>
                    <div class="flex flex-wrap gap-2">
                        @foreach($meeting->signatories as $sig)
                            <span class="inline-flex items-center rounded-lg bg-indigo-50/80 px-2.5 py-1 text-xs font-medium text-indigo-700">
                                ✍️ {{ $sig->name }} ({{ $sig->role_label }})
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Audio Retention Notice -->
            @if($meeting->audio_deleted_at)
                <div class="rounded-xl bg-slate-100 p-3 text-xs text-slate-600 border border-slate-200 font-medium flex flex-wrap items-center justify-between gap-1">
                    <span>🗑️ Audio dihapus pada {{ \Carbon\Carbon::parse($meeting->audio_deleted_at)->translatedFormat('d F Y, H:i') }} WIB</span>
                    <span class="text-[11px] text-slate-500">Transkrip mentah tetap tersimpan</span>
                </div>
            @elseif($meeting->audio_expires_at)
                <div class="rounded-xl bg-indigo-50/70 p-3 text-xs text-indigo-800 border border-indigo-200/60 font-medium flex flex-wrap items-center justify-between gap-1">
                    <span>⏱️ Audio tersedia sampai {{ \Carbon\Carbon::parse($meeting->audio_expires_at)->translatedFormat('d F Y, H:i') }} WIB</span>
                    <span class="text-[11px] text-indigo-600 font-normal">Retensi 1 bulan</span>
                </div>
            @endif


            <!-- Close Attendance Control for Host -->
            @can('update', $meeting)
                @if(!$meeting->absensi_closed_at)
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                        <div class="text-xs text-slate-500">
                            Sesi absensi masih terbuka. Tutup sesi untuk menetapkan status peserta yang belum hadir.
                        </div>
                        <form method="POST" action="{{ route('meetings.attendance.close', $meeting) }}" onsubmit="return confirm('Tutup absensi rapat ini sekarang? Peserta yang belum hadir akan otomatis diberi status Tanpa Keterangan.')">
                            @csrf
                            <button
                                type="submit"
                                class="inline-flex min-h-[38px] items-center rounded-xl bg-slate-800 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-slate-700 shadow-2xs"
                            >
                                🔒 Tutup Sesi Absensi
                            </button>
                        </form>
                    </div>
                @else
                    <div class="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                        <span>Absensi resmi ditutup pada {{ \Carbon\Carbon::parse($meeting->absensi_closed_at)->translatedFormat('d M Y, H:i') }} WIB.</span>
                        <a href="{{ route('meetings.attendance.recap', $meeting) }}" class="font-bold text-indigo-600 hover:text-indigo-800">
                            Koreksi Kehadiran di Rekap →
                        </a>
                    </div>
                @endif
            @endcan
        </div>

        <!-- Audio Playback Player (Authorized Creator Notulis & Pimpinan Only, No Download) -->
        @can('viewAudio', $meeting)
            @if(!empty($audioStreamUrl))
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                                🎧 Pemutar Audio Rekaman Rapat
                            </h2>
                            <p class="text-[11px] text-slate-500 mt-0.5">
                                Akses terbatas untuk notulis pembuat dan pimpinan. Unduhan dinonaktifkan sesuai kebijakan privasi.
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 ring-1 ring-emerald-600/20">
                            🔒 Streaming Privat
                        </span>
                    </div>

                    <div class="rounded-xl bg-slate-50 p-3 border border-slate-100">
                        <audio
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
            @endif
        @endcan

        <!-- Section: Peserta Rapat & Kelola Peserta -->
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-slate-900">Peserta & Presensi</h2>
                    <p class="text-xs text-slate-500">
                        {{ $meeting->attendees->whereNotNull('status')->count() }} hadir dari {{ $meeting->attendees->count() }} orang
                    </p>
                </div>
            </div>

            @can('manageAttendees', $meeting)
                <!-- Form Tambah Peserta (Accordion / Tab Alpine.js) -->
                <div x-data="{ tab: 'contact' }" class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-500">Tambah Peserta Rapat</span>

                        <div class="inline-flex rounded-lg bg-slate-100 p-0.5 text-xs font-medium">
                            <button
                                type="button"
                                @click="tab = 'contact'"
                                :class="tab === 'contact' ? 'bg-white text-slate-900 shadow-2xs font-semibold' : 'text-slate-500 hover:text-slate-900'"
                                class="rounded-md px-2.5 py-1 transition"
                            >
                                Dari Kontak
                            </button>
                            <button
                                type="button"
                                @click="tab = 'new'"
                                :class="tab === 'new' ? 'bg-white text-slate-900 shadow-2xs font-semibold' : 'text-slate-500 hover:text-slate-900'"
                                class="rounded-md px-2.5 py-1 transition"
                            >
                                Peserta Baru
                            </button>
                        </div>
                    </div>

                    <!-- Tab 1: Pilih dari Kontak Organisasi -->
                    <div x-show="tab === 'contact'" class="space-y-3">
                        @if($contacts->isNotEmpty())
                            <form method="POST" action="{{ route('meetings.attendees.store', $meeting) }}" class="space-y-3">
                                @csrf
                                <div class="space-y-1.5">
                                    <label for="contact_id" class="block text-xs font-medium text-slate-700">Pilih Kontak Organisasi</label>
                                    <select
                                        id="contact_id"
                                        name="contact_id"
                                        required
                                        class="block w-full min-h-[46px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3.5 py-2.5 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                    >
                                        <option value="">-- Pilih salah satu kontak --</option>
                                        @foreach($contacts as $contact)
                                            <option value="{{ $contact->id }}">
                                                {{ $contact->name }} @if($contact->position) ({{ $contact->position }}) @endif • {{ $contact->phone_e164 }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <button
                                    type="submit"
                                    class="inline-flex min-h-[44px] items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700"
                                >
                                    + Tambah ke Daftar Hadir
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-slate-500 py-2">
                                Belum ada buku kontak organisasi. Gunakan tab <strong>Peserta Baru</strong> untuk menambahkan peserta (otomatis tersimpan ke buku kontak).
                            </p>
                        @endif
                    </div>

                    <!-- Tab 2: Tambah Peserta Baru (Otomatis Simpan Kontak) -->
                    <div x-show="tab === 'new'" class="space-y-3" style="display: none;">
                        <form method="POST" action="{{ route('meetings.attendees.store', $meeting) }}" class="space-y-3">
                            @csrf
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div class="space-y-1.5">
                                    <label for="new_name" class="block text-xs font-medium text-slate-700">Nama Lengkap <span class="text-rose-500">*</span></label>
                                    <input
                                        type="text"
                                        id="new_name"
                                        name="name"
                                        required
                                        placeholder="Contoh: Dra. Sri Wahyuni"
                                        class="block w-full min-h-[44px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                    />
                                </div>

                                <div class="space-y-1.5">
                                    <label for="new_position" class="block text-xs font-medium text-slate-700">Jabatan / Instansi</label>
                                    <input
                                        type="text"
                                        id="new_position"
                                        name="position"
                                        placeholder="Contoh: Guru Produktif / Komite"
                                        class="block w-full min-h-[44px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                    />
                                </div>
                            </div>

                            <div class="space-y-1.5">
                                <label for="new_phone" class="block text-xs font-medium text-slate-700">Nomor Telepon / WhatsApp <span class="text-rose-500">*</span></label>
                                <input
                                    type="text"
                                    id="new_phone"
                                    name="phone"
                                    required
                                    placeholder="Contoh: 081234567890 atau +6281234567890"
                                    class="block w-full min-h-[44px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600"
                                />
                                <p class="text-[11px] text-slate-500">
                                    Nomor akan dinormalisasi ke format E.164 (628...) dan kontak otomatis tersimpan ke buku kontak organisasi.
                                </p>
                            </div>

                            <div class="pt-1">
                                <button
                                    type="submit"
                                    class="inline-flex min-h-[44px] items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700 active:scale-[0.98] transition"
                                >
                                    + Tambah Peserta & Simpan Kontak
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            <!-- Daftar Peserta Terdaftar -->
            <div class="overflow-hidden rounded-2xl border border-slate-200/90 bg-white shadow-2xs">
                <ul role="list" class="divide-y divide-slate-100">
                    @forelse($meeting->attendees as $attendee)
                        <li class="p-4 sm:px-5 space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0 pr-2">
                                    <div class="flex items-center gap-2">
                                        <span class="font-semibold text-sm text-slate-900 truncate">{{ $attendee->name }}</span>
                                        @if($attendee->is_guest)
                                            <span class="inline-flex items-center rounded-md bg-amber-50 px-1.5 py-0.5 text-[10px] font-bold text-amber-700">
                                                Tamu {{ $attendee->guest_status ? '(' . $attendee->guest_status . ')' : '' }}
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-500 truncate mt-0.5">
                                        {{ $attendee->position ?? 'Peserta' }} • +{{ $attendee->phone_e164 }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    @if($attendee->status)
                                        <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold capitalize
                                            @if($attendee->status === 'hadir') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                                            @elseif($attendee->status === 'terlambat') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                                            @else bg-slate-100 text-slate-700 @endif">
                                            {{ str_replace('_', ' ', $attendee->status) }}
                                        </span>
                                    @else
                                        <span class="text-xs text-slate-400 font-medium">Belum Absen</span>
                                    @endif

                                    @can('delete', $attendee)
                                        <form method="POST" action="{{ route('meetings.attendees.destroy', [$meeting, $attendee]) }}" onsubmit="return confirm('Hapus peserta {{ $attendee->name }} dari rapat ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button
                                                type="submit"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition"
                                                title="Hapus peserta"
                                            >
                                                ✕
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>

                            <!-- Host Quick Actions (Manual Mark & Guest Approval) -->
                            @can('manageAttendees', $meeting)
                                <div class="flex flex-wrap items-center justify-between gap-2 pt-1.5 border-t border-slate-50 text-xs">
                                    <div class="text-[11px] text-slate-400">
                                        @if($attendee->checked_in_at)
                                            Waktu presensi: {{ \Carbon\Carbon::parse($attendee->checked_in_at)->translatedFormat('H:i') }} ({{ strtoupper($attendee->method ?? 'QR') }})
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <!-- Guest Approval Buttons -->
                                        @if($attendee->is_guest && $attendee->guest_status === 'pending')
                                            <form method="POST" action="{{ route('meetings.attendance.approve_guest', [$meeting, $attendee]) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-700">
                                                    ✓ Setujui Tamu
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('meetings.attendance.reject_guest', [$meeting, $attendee]) }}">
                                                @csrf
                                                <button type="submit" class="rounded-lg bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-300">
                                                    Tolak
                                                </button>
                                            </form>
                                        @endif

                                        <!-- Manual Check-in for attendees without phone -->
                                        @if(!$attendee->status)
                                            <form method="POST" action="{{ route('meetings.attendance.manual', [$meeting, $attendee]) }}" class="inline-flex items-center gap-1.5">
                                                @csrf
                                                <input type="hidden" name="status" value="hadir" />
                                                <button type="submit" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                    ✓ Tandai Hadir Manual
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endcan
                        </li>
                    @empty
                        <li class="p-6 text-center text-xs text-slate-500">
                            Belum ada peserta yang ditambahkan ke room rapat ini.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</x-layouts.app>
