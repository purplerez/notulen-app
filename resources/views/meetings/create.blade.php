<x-layouts.app :title="'Buat Room Rapat - ' . config('app.name', 'Notulensi Rapat')">
    <div class="space-y-6">
        <div>
            <a href="{{ route('meetings.index') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                ← Kembali ke Daftar Rapat
            </a>
            <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                Buat Room Rapat
            </h1>
            <p class="text-xs text-slate-500 mt-0.5">Siapkan agenda, preferensi notulensi, dan daftar peserta awal</p>
        </div>

        <form method="POST" action="{{ route('meetings.store') }}" class="space-y-6">
            @csrf

            <!-- Card 1: Informasi Utama -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">1. Informasi Rapat</h2>

                <div class="space-y-1.5">
                    <label for="title" class="block text-sm font-medium text-slate-700">Judul / Topik Rapat <span class="text-rose-500">*</span></label>
                    <input
                        type="text"
                        id="title"
                        name="title"
                        value="{{ old('title') }}"
                        required
                        autofocus
                        placeholder="Contoh: Rapat Koordinasi Kurikulum Semester Genap"
                        class="block w-full min-h-[48px] rounded-xl border @error('title') border-rose-300 bg-rose-50/20 text-rose-900 @else border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 @enderror px-3.5 py-2.5 text-base shadow-2xs focus:outline-none focus:ring-2 focus:ring-offset-1"
                    />
                    @error('title')
                        <p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div class="space-y-1.5">
                        <label for="meeting_date" class="block text-sm font-medium text-slate-700">Tanggal Rapat <span class="text-rose-500">*</span></label>
                        <input
                            type="date"
                            id="meeting_date"
                            name="meeting_date"
                            value="{{ old('meeting_date', date('Y-m-d')) }}"
                            required
                            class="block w-full min-h-[48px] rounded-xl border @error('meeting_date') border-rose-300 text-rose-900 @else border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 @enderror px-3.5 py-2.5 text-base shadow-2xs focus:outline-none focus:ring-2 focus:ring-offset-1"
                        />
                        @error('meeting_date')
                            <p class="text-xs font-medium text-rose-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="space-y-1.5">
                        <label for="start_time" class="block text-sm font-medium text-slate-700">Jam Mulai (Opsional)</label>
                        <input
                            type="time"
                            id="start_time"
                            name="start_time"
                            value="{{ old('start_time', '09:00') }}"
                            class="block w-full min-h-[48px] rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 px-3.5 py-2.5 text-base shadow-2xs focus:outline-none focus:ring-2 focus:ring-offset-1"
                        />
                    </div>
                </div>

                <div class="space-y-1.5">
                    <label for="location" class="block text-sm font-medium text-slate-700">Lokasi / Ruang Rapat</label>
                    <input
                        type="text"
                        id="location"
                        name="location"
                        value="{{ old('location') }}"
                        placeholder="Contoh: Ruang Rapat Pimpinan Lantai 2"
                        class="block w-full min-h-[48px] rounded-xl border border-slate-300 bg-white text-slate-900 focus:border-indigo-600 focus:ring-indigo-600 px-3.5 py-2.5 text-base shadow-2xs focus:outline-none focus:ring-2 focus:ring-offset-1"
                    />
                </div>
            </div>

            <!-- Card 2: Gaya & Pengaturan Notulensi -->
            <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-4">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">2. Format & Preferensi Notulensi</h2>

                <!-- Style: Ringkas vs Lengkap -->
                <div class="space-y-2">
                    <label class="block text-sm font-medium text-slate-700">Gaya Notulen</label>
                    <div class="grid grid-cols-2 gap-3">
                        <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer select-none transition has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/40 border-slate-200 bg-white">
                            <input type="radio" name="style" value="ringkas" {{ old('style', 'ringkas') === 'ringkas' ? 'checked' : '' }} class="sr-only" />
                            <span class="text-sm font-bold text-slate-900">📄 Ringkas</span>
                            <span class="text-xs text-slate-500 mt-1">Poin keputusan penting dan tindak lanjut utama</span>
                        </label>
                        <label class="relative flex flex-col p-4 rounded-xl border cursor-pointer select-none transition has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50/40 border-slate-200 bg-white">
                            <input type="radio" name="style" value="lengkap" {{ old('style') === 'lengkap' ? 'checked' : '' }} class="sr-only" />
                            <span class="text-sm font-bold text-slate-900">📑 Lengkap</span>
                            <span class="text-xs text-slate-500 mt-1">Alur pembahasan detail, dinamika diskusi & keputusan</span>
                        </label>
                    </div>
                </div>

                <!-- Toggles -->
                <div class="space-y-3 pt-2 border-t border-slate-100">
                    <label class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 cursor-pointer">
                        <div class="pr-2">
                            <span class="block text-sm font-medium text-slate-900">Daftar Tindak Lanjut (Action Items)</span>
                            <span class="block text-xs text-slate-500">Menyertakan tabel penugasan, PIC, dan tenggat waktu</span>
                        </div>
                        <input type="checkbox" name="use_followup" value="1" {{ old('use_followup', '1') ? 'checked' : '' }} class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    </label>

                    <label class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 cursor-pointer">
                        <div class="pr-2">
                            <span class="block text-sm font-medium text-slate-900">Gunakan Kop Surat Resmi</span>
                            <span class="block text-xs text-slate-500">Memasang kop surat organisasi pada ekspor PDF</span>
                        </div>
                        <input type="checkbox" name="use_kop" value="1" {{ old('use_kop', '1') ? 'checked' : '' }} class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    </label>

                    <label class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 cursor-pointer">
                        <div class="pr-2">
                            <span class="block text-sm font-medium text-slate-900">Kolom Tanda Tangan</span>
                            <span class="block text-xs text-slate-500">Menyertakan kolom tanda tangan penandatangan pada notulen</span>
                        </div>
                        <input type="checkbox" name="use_signature" value="1" {{ old('use_signature', '1') ? 'checked' : '' }} class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    </label>

                    <label class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 cursor-pointer">
                        <div class="pr-2">
                            <span class="block text-sm font-medium text-slate-900">Lampirkan Rekap Daftar Hadir</span>
                            <span class="block text-xs text-slate-500">Mencantumkan kehadiran peserta pada lembar notulen</span>
                        </div>
                        <input type="checkbox" name="show_attendance" value="1" {{ old('show_attendance', '1') ? 'checked' : '' }} class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    </label>

                    <label class="flex items-center justify-between p-3 rounded-xl hover:bg-slate-50 cursor-pointer">
                        <div class="pr-2">
                            <span class="block text-sm font-medium text-slate-900">Pemberitahuan Rekaman Suara</span>
                            <span class="block text-xs text-slate-500">Tampilkan teks 'Rapat direkam untuk notulensi' pada dokumen</span>
                        </div>
                        <input type="checkbox" name="show_recorded_notice" value="1" {{ old('show_recorded_notice') ? 'checked' : '' }} class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" />
                    </label>
                </div>

                <div class="space-y-1.5 pt-2 border-t border-slate-100">
                    <label for="late_after_minutes" class="block text-sm font-medium text-slate-700">Toleransi Keterlambatan Absensi (Menit)</label>
                    <input
                        type="number"
                        id="late_after_minutes"
                        name="late_after_minutes"
                        value="{{ old('late_after_minutes', 15) }}"
                        min="0"
                        max="180"
                        class="block w-32 min-h-[44px] rounded-xl border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm shadow-2xs focus:ring-2 focus:ring-indigo-600"
                    />
                </div>
            </div>

            <!-- Card 3: Penandatangan Dokumen (Jika Ada) -->
            @if($signatories->isNotEmpty())
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-3">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">3. Penandatangan Notulen</h2>
                    <p class="text-xs text-slate-500">Pilih penandatangan resmi untuk rapat ini:</p>

                    <div class="space-y-2">
                        @foreach($signatories as $sig)
                            <label class="flex items-center gap-3 p-3 rounded-xl border border-slate-200/80 hover:bg-slate-50 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="signatory_ids[]"
                                    value="{{ $sig->id }}"
                                    {{ in_array($sig->id, old('signatory_ids', [])) ? 'checked' : '' }}
                                    class="h-5 w-5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div>
                                    <span class="block text-sm font-semibold text-slate-900">{{ $sig->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $sig->role_label }} @if($sig->position) ({{ $sig->position }}) @endif</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Card 4: Peserta Awal dari Kontak Organisasi -->
            @if($contacts->isNotEmpty())
                <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">4. Pilih Peserta Awal (Opsional)</h2>
                        <span class="text-xs text-slate-400">Dapat ditambah lagi nanti</span>
                    </div>

                    <div class="max-h-60 overflow-y-auto space-y-2 pr-1 divide-y divide-slate-100">
                        @foreach($contacts as $contact)
                            <label class="flex items-center gap-3 p-2.5 rounded-xl hover:bg-slate-50 cursor-pointer">
                                <input
                                    type="checkbox"
                                    name="selected_contact_ids[]"
                                    value="{{ $contact->id }}"
                                    {{ in_array($contact->id, old('selected_contact_ids', [])) ? 'checked' : '' }}
                                    class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                                />
                                <div class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-900 truncate">{{ $contact->name }}</span>
                                    <span class="block text-xs text-slate-500">{{ $contact->position ?? 'Peserta' }} • +{{ $contact->phone_e164 }}</span>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Bottom Actions -->
            <div class="flex items-center justify-end gap-3 pt-2">
                <a
                    href="{{ route('meetings.index') }}"
                    class="inline-flex min-h-[48px] items-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    Batal
                </a>
                <button
                    type="submit"
                    class="inline-flex min-h-[48px] items-center rounded-xl bg-indigo-600 px-6 py-3 text-base font-semibold text-white shadow-xs hover:bg-indigo-700 active:scale-[0.98] transition"
                >
                    Simpan & Buat Room
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
