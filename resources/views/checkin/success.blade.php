<x-layouts.app :title="'Presensi Berhasil - ' . $meeting->title">
    <div class="space-y-6 pt-4 text-center">
        <!-- Success Icon -->
        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 ring-8 ring-emerald-500/10">
            <svg class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
            </svg>
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900">
                Presensi Tercatat!
            </h1>
            <p class="text-xs text-slate-500">
                Terima kasih, kehadiran Anda pada rapat ini telah berhasil direkam ke sistem berita acara.
            </p>
        </div>

        <!-- Receipt Card -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-5 shadow-2xs text-left space-y-3.5">
            <div>
                <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider">Nama Peserta</span>
                <span class="block text-base font-bold text-slate-900">{{ $attendee->name }}</span>
                @if($attendee->position)
                    <span class="block text-xs text-slate-500">{{ $attendee->position }}</span>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-100">
                <div>
                    <span class="block text-xs text-slate-400">Status Kehadiran</span>
                    @if($attendee->is_guest && $attendee->guest_status === 'pending')
                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-800 ring-1 ring-amber-600/20">
                            ⏳ Menunggu Host
                        </span>
                    @elseif($attendee->status === 'terlambat')
                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-bold text-amber-800 ring-1 ring-amber-600/20">
                            ⏱️ Terlambat
                        </span>
                    @else
                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-bold text-emerald-800 ring-1 ring-emerald-600/20">
                            ✅ Hadir
                        </span>
                    @endif
                </div>

                <div>
                    <span class="block text-xs text-slate-400">Waktu Scan</span>
                    <span class="block text-sm font-semibold text-slate-800">
                        {{ $attendee->checked_in_at ? \Carbon\Carbon::parse($attendee->checked_in_at)->translatedFormat('H:i:s') . ' WIB' : '-' }}
                    </span>
                </div>
            </div>

            <div class="pt-2 border-t border-slate-100">
                <span class="block text-xs text-slate-400">Rapat</span>
                <span class="block text-xs font-semibold text-slate-700">{{ $meeting->title }}</span>
            </div>

            @if($attendee->is_guest && $attendee->guest_status === 'pending')
                <div class="rounded-xl bg-amber-50 p-3 text-xs text-amber-800 border border-amber-200/80 leading-relaxed">
                    ℹ️ Anda terdaftar sebagai <strong>tamu</strong> di luar daftar undangan. Kehadiran Anda menunggu konfirmasi oleh administrator atau notulis rapat.
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
