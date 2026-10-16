<x-layouts.app :title="'Presensi Rapat - ' . $meeting->title">
    <div class="space-y-6 pt-2">
        <!-- Meeting Header -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-2">
            <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                Presensi Kehadiran
            </span>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl">
                {{ $meeting->title }}
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

            @if($meeting->show_recorded_notice)
                <div class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-600/20">
                    <span>🎙️ Rapat direkam untuk notulensi</span>
                </div>
            @endif
        </div>

        @if ($isClosed)
            <div class="rounded-2xl border border-rose-200 bg-rose-50/70 p-6 text-center space-y-2">
                <span class="text-3xl">🔒</span>
                <h2 class="text-base font-bold text-rose-900">Absensi Telah Ditutup</h2>
                <p class="text-xs text-rose-700 leading-relaxed">
                    Sesi presensi untuk rapat ini telah resmi ditutup oleh pengelola rapat. Hubungi notulis atau administrator jika Anda memerlukan bantuan.
                </p>
            </div>
        @elseif (! $isCodeValid)
            <div class="rounded-2xl border border-amber-200 bg-amber-50/70 p-6 text-center space-y-3">
                <span class="text-3xl">⏱️</span>
                <h2 class="text-base font-bold text-amber-900">Kode QR Kedaluwarsa</h2>
                <p class="text-xs text-amber-700 leading-relaxed">
                    Kode QR pada tautan ini sudah tidak berlaku (berganti tiap 30 detik untuk keamanan). Silakan scan ulang kode QR terbaru yang ditampilkan di layar host.
                </p>
            </div>
        @else
            <!-- Check-in Form -->
            <div x-data="{ mode: 'invited', selectedAttendeeId: '' }" class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-5">
                <div class="text-center space-y-1 border-b border-slate-100 pb-3">
                    <h2 class="text-base font-bold text-slate-900">Formulir Presensi</h2>
                    <p class="text-xs text-slate-500">Pilih nama Anda atau isi data untuk mencatat kehadiran</p>
                </div>

                @if ($errors->any())
                    <div class="rounded-xl bg-rose-50 p-4 border border-rose-200 text-xs text-rose-800 space-y-1">
                        @foreach ($errors->all() as $error)
                            <p>⚠️ {{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('checkin.store', $meeting->id) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="code" value="{{ $code }}" />

                    @if($unattended->isNotEmpty())
                        <!-- Mode Tabs -->
                        <div class="flex items-center justify-center p-1 rounded-xl bg-slate-100 text-xs font-semibold">
                            <button
                                type="button"
                                @click="mode = 'invited'"
                                :class="mode === 'invited' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-500'"
                                class="flex-1 py-2 rounded-lg transition"
                            >
                                Dari Daftar Undangan
                            </button>
                            <button
                                type="button"
                                @click="mode = 'guest'"
                                :class="mode === 'guest' ? 'bg-white text-indigo-700 shadow-2xs' : 'text-slate-500'"
                                class="flex-1 py-2 rounded-lg transition"
                            >
                                Tamu / Luar Daftar
                            </button>
                        </div>

                        <!-- Option A: Select Invited Attendee -->
                        <div x-show="mode === 'invited'" class="space-y-1.5">
                            <label for="attendee_id" class="block text-xs font-semibold text-slate-700">Pilih Nama Anda <span class="text-rose-500">*</span></label>
                            <select
                                id="attendee_id"
                                name="attendee_id"
                                x-model="selectedAttendeeId"
                                class="block w-full min-h-[48px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3.5 py-2.5 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            >
                                <option value="">-- Cari atau pilih nama Anda --</option>
                                @foreach($unattended as $person)
                                    <option value="{{ $person->id }}">
                                        {{ $person->name }} @if($person->position) ({{ $person->position }}) @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <!-- Option B: Manual Guest Input -->
                    <div x-show="mode === 'guest' || {{ $unattended->isEmpty() ? 'true' : 'false' }}" class="space-y-3" style="{{ $unattended->isNotEmpty() ? 'display: none;' : '' }}">
                        <div class="space-y-1.5">
                            <label for="name" class="block text-xs font-semibold text-slate-700">Nama Lengkap <span class="text-rose-500">*</span></label>
                            <input
                                type="text"
                                id="name"
                                name="name"
                                value="{{ old('name') }}"
                                placeholder="Contoh: Dra. Sri Wahyuni"
                                class="block w-full min-h-[48px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3.5 py-2.5 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            />
                        </div>

                        <div class="space-y-1.5">
                            <label for="position" class="block text-xs font-semibold text-slate-700">Jabatan / Instansi (Opsional)</label>
                            <input
                                type="text"
                                id="position"
                                name="position"
                                value="{{ old('position') }}"
                                placeholder="Contoh: Komite Sekolah / Narasumber"
                                class="block w-full min-h-[48px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3.5 py-2.5 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600"
                            />
                        </div>
                    </div>

                    <!-- Phone Number Input (Always required) -->
                    <div class="space-y-1.5 pt-1">
                        <label for="phone" class="block text-xs font-semibold text-slate-700">Nomor WhatsApp / HP <span class="text-rose-500">*</span></label>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            value="{{ old('phone') }}"
                            required
                            placeholder="Contoh: 081234567890"
                            class="block w-full min-h-[48px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3.5 py-2.5 text-base shadow-2xs focus:ring-2 focus:ring-indigo-600"
                        />
                        <p class="text-[11px] text-slate-500">
                            Masukkan nomor HP Anda (bisa diawali 08, 628, atau +628).
                        </p>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-3">
                        <button
                            type="submit"
                            class="flex w-full min-h-[50px] items-center justify-center rounded-xl bg-indigo-600 px-4 py-3.5 text-base font-bold text-white shadow-sm hover:bg-indigo-700 active:scale-[0.98] transition cursor-pointer"
                        >
                            Hadir
                        </button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-layouts.app>
