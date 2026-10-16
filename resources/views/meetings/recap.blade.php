<x-layouts.app :title="'Rekapitulasi Kehadiran - ' . $meeting->title">
    <div class="space-y-6">
        <!-- Action Buttons (Hidden on Print) -->
        <div class="print:hidden flex items-center justify-between gap-3">
            <div>
                <a href="{{ route('meetings.show', $meeting) }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Detail Rapat
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-0.5">
                    Rekapitulasi Presensi Berita Acara
                </h1>
                <p class="text-xs text-slate-500">Daftar hadir resmi peserta rapat</p>
            </div>

            <div class="flex items-center gap-2">
                <button
                    type="button"
                    onclick="window.print()"
                    class="inline-flex min-h-[40px] items-center gap-1.5 rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-indigo-700 active:scale-[0.98] transition cursor-pointer"
                >
                    🖨️ Cetak Berita Acara
                </button>
            </div>
        </div>

        @if (session('success'))
            <div class="print:hidden rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="print:hidden rounded-xl bg-rose-50 p-4 border border-rose-200 text-xs text-rose-800 space-y-1">
                @foreach ($errors->all() as $error)
                    <p>⚠️ {{ $error }}</p>
                @endforeach
            </div>
        @endif

        <!-- Summary Statistics Cards (Hidden on Print if desired, or compact) -->
        <div class="print:hidden grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
            <div class="rounded-xl border border-slate-200 bg-white p-3.5 shadow-2xs">
                <span class="block text-slate-400 font-semibold uppercase tracking-wider text-[10px]">Total Peserta</span>
                <span class="text-xl font-bold text-slate-900">{{ $summary['total'] }}</span>
                <span class="block text-[11px] text-slate-500 mt-0.5">{{ $attendanceRate }}% Kehadiran</span>
            </div>

            <div class="rounded-xl border border-emerald-100 bg-emerald-50/60 p-3.5 shadow-2xs">
                <span class="block text-emerald-700 font-semibold uppercase tracking-wider text-[10px]">Hadir & Terlambat</span>
                <span class="text-xl font-bold text-emerald-800">{{ $summary['hadir'] + $summary['terlambat'] }}</span>
                <span class="block text-[11px] text-emerald-600 mt-0.5">{{ $summary['hadir'] }} tepat, {{ $summary['terlambat'] }} lambat</span>
            </div>

            <div class="rounded-xl border border-amber-100 bg-amber-50/60 p-3.5 shadow-2xs">
                <span class="block text-amber-700 font-semibold uppercase tracking-wider text-[10px]">Izin & Sakit</span>
                <span class="text-xl font-bold text-amber-800">{{ $summary['izin'] + $summary['sakit'] }}</span>
                <span class="block text-[11px] text-amber-600 mt-0.5">{{ $summary['izin'] }} izin, {{ $summary['sakit'] }} sakit</span>
            </div>

            <div class="rounded-xl border border-rose-100 bg-rose-50/60 p-3.5 shadow-2xs">
                <span class="block text-rose-700 font-semibold uppercase tracking-wider text-[10px]">Dinas Luar & Alpa</span>
                <span class="text-xl font-bold text-rose-800">{{ $summary['dinas_luar'] + $summary['tanpa_keterangan'] }}</span>
                <span class="block text-[11px] text-rose-600 mt-0.5">{{ $summary['dinas_luar'] }} dinas, {{ $summary['tanpa_keterangan'] }} alpa</span>
            </div>
        </div>

        <!-- Printable Document Container -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-6 sm:p-8 shadow-xs print:border-none print:shadow-none print:p-0 space-y-6">
            <!-- Kop Organisasi (jika use_kop aktif) -->
            @if($meeting->use_kop)
                <div class="text-center border-b-2 border-slate-900 pb-4 space-y-1">
                    <h2 class="text-lg font-bold uppercase tracking-wider text-slate-900">
                        {{ $meeting->organization->name }}
                    </h2>
                    @if($meeting->organization->address_text)
                        <p class="text-xs text-slate-600">{{ $meeting->organization->address_text }}</p>
                    @endif
                </div>
            @endif

            <!-- Title & Metadata -->
            <div class="text-center space-y-1">
                <h3 class="text-base font-bold uppercase tracking-wide text-slate-900">
                    Daftar Hadir Peserta Rapat
                </h3>
                <p class="text-sm font-semibold text-slate-800">{{ $meeting->title }}</p>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs text-slate-700 border border-slate-200 rounded-xl p-3 bg-slate-50/50 print:bg-transparent">
                <div>
                    <span class="text-slate-400">Hari, Tanggal:</span>
                    <strong>{{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('l, d F Y') }}</strong>
                </div>
                <div>
                    <span class="text-slate-400">Waktu:</span>
                    <strong>{{ $meeting->start_time ? substr($meeting->start_time, 0, 5) . ' WIB' : 'Sesuai Jadwal' }}</strong>
                </div>
                <div>
                    <span class="text-slate-400">Tempat:</span>
                    <strong>{{ $meeting->location ?? 'Ruang Rapat' }}</strong>
                </div>
                <div>
                    <span class="text-slate-400">Status Absensi:</span>
                    <strong>{{ $meeting->absensi_closed_at ? 'Telah Ditutup' : 'Terbuka' }}</strong>
                </div>
            </div>

            <!-- Attendance Table -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse border border-slate-300 text-xs">
                    <thead>
                        <tr class="bg-slate-100 text-slate-800 print:bg-slate-200 font-bold">
                            <th class="border border-slate-300 px-3 py-2 text-center w-10">No</th>
                            <th class="border border-slate-300 px-3 py-2">Nama Peserta</th>
                            <th class="border border-slate-300 px-3 py-2">Jabatan</th>
                            <th class="border border-slate-300 px-3 py-2 text-center">Status</th>
                            <th class="border border-slate-300 px-3 py-2 text-center">Jam Hadir</th>
                            <th class="border border-slate-300 px-3 py-2 text-center">Metode</th>
                            <th class="border border-slate-300 px-3 py-2 text-center w-28">Paraf</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        @forelse($attendees as $idx => $person)
                            <tr class="hover:bg-slate-50/50">
                                <td class="border border-slate-300 px-3 py-2 text-center">{{ $idx + 1 }}</td>
                                <td class="border border-slate-300 px-3 py-2 font-semibold text-slate-900">
                                    {{ $person->name }}
                                    @if($person->is_guest)
                                        <span class="inline-block text-[10px] text-amber-700 font-normal">(Tamu)</span>
                                    @endif
                                    @if($person->note)
                                        <span class="block text-[10px] text-slate-400 font-normal italic">Ket: {{ $person->note }}</span>
                                    @endif
                                </td>
                                <td class="border border-slate-300 px-3 py-2 text-slate-600">{{ $person->position ?? '-' }}</td>
                                <td class="border border-slate-300 px-3 py-2 text-center capitalize font-medium">
                                    @if($person->status)
                                        <span class="
                                            @if($person->status === 'hadir') text-emerald-700
                                            @elseif($person->status === 'terlambat') text-amber-700
                                            @else text-slate-700 @endif
                                        ">
                                            {{ str_replace('_', ' ', $person->status) }}
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic">Belum Hadir</span>
                                    @endif
                                </td>
                                <td class="border border-slate-300 px-3 py-2 text-center text-slate-600">
                                    {{ $person->checked_in_at ? \Carbon\Carbon::parse($person->checked_in_at)->translatedFormat('H:i') : '-' }}
                                </td>
                                <td class="border border-slate-300 px-3 py-2 text-center uppercase text-[11px] text-slate-500">
                                    {{ $person->method ?? '-' }}
                                </td>
                                <td class="border border-slate-300 px-3 py-2 text-center h-10 align-middle">
                                    <span class="inline-block text-[10px] text-slate-300 select-none">
                                        {{ $idx % 2 === 0 ? ($idx + 1) . '. .........' : '......... ' . ($idx + 1) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="border border-slate-300 p-4 text-center text-slate-400">
                                    Tidak ada data peserta terdaftar.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Signatures Section (if use_signature) -->
            @if($meeting->use_signature && $meeting->signatories->isNotEmpty())
                <div class="pt-6 grid grid-cols-2 sm:grid-cols-3 gap-6 text-center text-xs">
                    @foreach($meeting->signatories as $sig)
                        <div class="space-y-14">
                            <span class="block text-slate-600 font-medium">{{ $sig->role_label }},</span>
                            <div class="border-t border-slate-400 pt-1">
                                <span class="block font-bold text-slate-900">{{ $sig->name }}</span>
                                @if($sig->position)
                                    <span class="block text-[11px] text-slate-500">{{ $sig->position }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Koreksi Status Setelah Ditutup (Hanya Ditampilkan Pada Layar) -->
        @can('update', $meeting)
            <div class="print:hidden rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900">Koreksi Kehadiran Peserta</h3>
                    <span class="text-xs text-slate-400">
                        {{ $meeting->absensi_closed_at ? 'Wajib mengisi alasan (dicatat di audit log)' : 'Mode terbuka' }}
                    </span>
                </div>

                <div class="space-y-3">
                    @foreach($attendees as $att)
                        <form method="POST" action="{{ route('meetings.attendance.update_status', [$meeting, $att]) }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3 rounded-xl border border-slate-100 hover:bg-slate-50/50">
                            @csrf
                            @method('PUT')

                            <div class="min-w-0 pr-2">
                                <span class="font-semibold text-xs text-slate-900">{{ $att->name }}</span>
                                <span class="text-[11px] text-slate-400 block">Status saat ini: <strong>{{ $att->status ?? 'Belum Hadir' }}</strong></span>
                            </div>

                            <div class="flex flex-wrap items-center gap-2">
                                <select name="status" class="min-h-[36px] rounded-lg border border-slate-300 text-xs px-2.5 py-1">
                                    <option value="hadir" {{ $att->status === 'hadir' ? 'selected' : '' }}>Hadir</option>
                                    <option value="terlambat" {{ $att->status === 'terlambat' ? 'selected' : '' }}>Terlambat</option>
                                    <option value="izin" {{ $att->status === 'izin' ? 'selected' : '' }}>Izin</option>
                                    <option value="sakit" {{ $att->status === 'sakit' ? 'selected' : '' }}>Sakit</option>
                                    <option value="dinas_luar" {{ $att->status === 'dinas_luar' ? 'selected' : '' }}>Dinas Luar</option>
                                    <option value="tanpa_keterangan" {{ $att->status === 'tanpa_keterangan' ? 'selected' : '' }}>Tanpa Keterangan</option>
                                </select>

                                @if($meeting->absensi_closed_at)
                                    <input
                                        type="text"
                                        name="reason"
                                        required
                                        placeholder="Alasan koreksi (wajib)..."
                                        class="min-h-[36px] rounded-lg border border-slate-300 text-xs px-2.5 py-1 w-44"
                                    />
                                @endif

                                <button
                                    type="submit"
                                    class="inline-flex min-h-[36px] items-center rounded-lg bg-slate-800 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-700"
                                >
                                    Ubah
                                </button>
                            </div>
                        </form>
                    @endforeach
                </div>
            </div>
        @endcan
    </div>
</x-layouts.app>
