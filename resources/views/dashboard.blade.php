<x-layouts.app :title="'Dashboard - ' . config('app.name', 'Notulensi Rapat')">
    <x-slot:headerActions>
        <div class="flex items-center gap-2">
            <a
                href="{{ route('audit_logs.index') }}"
                class="inline-flex min-h-[38px] items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
            >
                <span>📜 Log Akses</span>
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="inline-flex min-h-[38px] items-center rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition cursor-pointer"
                >
                    Keluar
                </button>
            </form>
        </div>
    </x-slot:headerActions>

    <div class="space-y-6">
        <!-- Top Profile & Organization Welcome Card -->
        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-2xs sm:p-6 space-y-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-400">Organisasi</p>
                    <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-0.5">
                        {{ $organization?->name ?? 'Organisasi' }}
                    </h1>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider
                        @if($user->role === 'admin') bg-purple-50 text-purple-700 ring-1 ring-purple-600/20
                        @elseif($user->role === 'pimpinan') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                        @else bg-blue-50 text-blue-700 ring-1 ring-blue-600/20 @endif">
                        Peran: {{ $user->role }}
                    </span>
                </div>
            </div>

            <!-- Quick Action Links / Shortcuts -->
            <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 text-xs">
                @can('create', App\Models\Meeting::class)
                    <a
                        href="{{ route('meetings.create') }}"
                        class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 font-bold text-white shadow-2xs hover:bg-indigo-700 transition"
                    >
                        <span>+ Buat Room Rapat</span>
                    </a>
                @endcan

                @can('create', App\Models\User::class)
                    <a
                        href="{{ route('users.index') }}"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                    >
                        <span>👥 Kelola Pengguna</span>
                    </a>
                @endcan

                <a
                    href="{{ route('audit_logs.index') }}"
                    class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 font-semibold text-slate-700 shadow-2xs hover:bg-slate-50 transition"
                >
                    <span>📜 Lihat Log Akses</span>
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs space-y-1">
                <span class="text-xs text-slate-500 font-medium">Total Rapat</span>
                <p class="text-2xl font-bold text-slate-900">{{ $stats['total_meetings'] }}</p>
                <p class="text-[11px] text-slate-400">Seluruh agenda tercatat</p>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-2xs space-y-1">
                <span class="text-xs text-emerald-600 font-medium">Notulen Final</span>
                <p class="text-2xl font-bold text-emerald-700">{{ $stats['final_minutes_count'] }}</p>
                <p class="text-[11px] text-slate-400">Siap dibaca & disahkan</p>
            </div>

            @if($user->isPimpinan())
                <div class="rounded-2xl border border-amber-200/90 bg-amber-50/50 p-4 shadow-2xs space-y-1 col-span-2 sm:col-span-1">
                    <span class="text-xs text-amber-800 font-medium">Perlu Diketahui</span>
                    <p class="text-2xl font-bold text-amber-900">{{ $stats['unacknowledged_count'] }}</p>
                    <p class="text-[11px] text-amber-700">Notulen final belum ditandai</p>
                </div>
            @endif
        </div>

        <!-- Filter & Search Section -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-2xs space-y-3">
            <form method="GET" action="{{ route('dashboard') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <!-- Search Title -->
                <div class="space-y-1">
                    <label for="search_q" class="block text-xs font-semibold text-slate-700">Cari Judul Rapat</label>
                    <input
                        type="text"
                        id="search_q"
                        name="q"
                        value="{{ $filters['q'] }}"
                        placeholder="Ketik kata kunci judul..."
                        class="block w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                    />
                </div>

                <!-- Filter Month -->
                <div class="space-y-1">
                    <label for="filter_month" class="block text-xs font-semibold text-slate-700">Bulan Rapat</label>
                    <input
                        type="month"
                        id="filter_month"
                        name="month"
                        value="{{ $filters['month'] }}"
                        class="block w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                    />
                </div>

                <!-- Filter Status -->
                <div class="space-y-1">
                    <label for="filter_status" class="block text-xs font-semibold text-slate-700">Status Rapat / Notulen</label>
                    <select
                        id="filter_status"
                        name="status"
                        class="block w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                    >
                        <option value="">Semua Status</option>
                        <option value="final_minutes" @selected($filters['status'] === 'final_minutes')>Notulen Final</option>
                        <option value="draft_minutes" @selected($filters['status'] === 'draft_minutes')>Notulen Masih Draf</option>
                        @if($user->isPimpinan())
                            <option value="unacknowledged" @selected($filters['status'] === 'unacknowledged')>Belum Anda Ketahui</option>
                        @endif
                        <option value="ready" @selected($filters['status'] === 'ready')>Rapat Siap (Ready)</option>
                        <option value="recording" @selected($filters['status'] === 'recording')>Sedang Rekam</option>
                        <option value="draft" @selected($filters['status'] === 'draft')>Room Baru (Draft)</option>
                    </select>
                </div>

                <!-- Submit / Reset Buttons -->
                <div class="sm:col-span-3 flex items-center justify-end gap-2 pt-1 border-t border-slate-100">
                    @if(array_filter($filters))
                        <a
                            href="{{ route('dashboard') }}"
                            class="rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition"
                        >
                            Reset
                        </a>
                    @endif
                    <button
                        type="submit"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-2xs hover:bg-indigo-700 transition"
                    >
                        Terapkan Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Meeting Rooms Listing Card -->
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-500">
                    Daftar Rapat Organisasi ({{ $meetings->total() }})
                </h2>
                @can('create', App\Models\Meeting::class)
                    <a href="{{ route('meetings.create') }}" class="text-xs font-semibold text-indigo-600 hover:text-indigo-800">
                        + Buat Room Rapat
                    </a>
                @endcan
            </div>

            <div class="space-y-3.5">
                @forelse($meetings as $meeting)
                    @php
                        $audioInfo = $meeting->getAudioRetentionInfo();
                        $latestFinal = $meeting->latestFinalMinute;
                        $hasFinalMinute = $latestFinal !== null;
                        $isAcknowledged = $hasFinalMinute && $latestFinal->isAcknowledgedBy($user);
                        $canViewAudio = isset($audioUrls[$meeting->id]);
                    @endphp

                    <div class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-2xs space-y-4 hover:border-slate-300 transition">
                        <!-- Top Meta Header -->
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1 min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <!-- Meeting Status Badge -->
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-bold uppercase tracking-wider
                                        @if($meeting->status === 'draft') bg-slate-100 text-slate-700
                                        @elseif($meeting->status === 'recording') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20 animate-pulse
                                        @elseif($meeting->status === 'transcribing') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                                        @elseif($meeting->status === 'ready') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                                        @else bg-blue-50 text-blue-700 @endif">
                                        {{ $meeting->status }}
                                    </span>

                                    <!-- Minutes Status Badge -->
                                    @if($hasFinalMinute)
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-[11px] font-bold text-emerald-800 ring-1 ring-emerald-600/20">
                                            ✅ Notulen Final (v{{ $latestFinal->version }})
                                        </span>
                                    @elseif($meeting->currentMinute && $meeting->currentMinute->isDraft())
                                        <span class="inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-[11px] font-bold text-amber-800 ring-1 ring-amber-600/20">
                                            ⏳ Draf Notulen
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-md bg-slate-50 px-2 py-0.5 text-[11px] font-semibold text-slate-500 border border-slate-200">
                                            Belum Ada Notulen
                                        </span>
                                    @endif

                                    <!-- Pimpinan Acknowledgement Status -->
                                    @if($user->isPimpinan())
                                        @if($hasFinalMinute)
                                            @if($isAcknowledged)
                                                <span class="inline-flex items-center rounded-md bg-indigo-50 px-2 py-0.5 text-[11px] font-bold text-indigo-700">
                                                    👍 Telah Anda Ketahui
                                                </span>
                                            @else
                                                <span class="inline-flex items-center rounded-md bg-amber-100 px-2 py-0.5 text-[11px] font-bold text-amber-900 animate-pulse">
                                                    ✍️ Belum Diketahui
                                                </span>
                                            @endif
                                        @endif
                                    @endif
                                </div>

                                <h3 class="text-base font-bold text-slate-900 pt-0.5">
                                    <a href="{{ route('meetings.show', $meeting) }}" class="hover:text-indigo-600 transition">
                                        {{ $meeting->title }}
                                    </a>
                                </h3>
                            </div>

                            <!-- Presensi Count & Notulis Info -->
                            <div class="text-right shrink-0">
                                <span class="inline-flex items-center rounded-xl bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700">
                                    👥 {{ $meeting->present_attendees_count }} / {{ $meeting->total_attendees_count }} Hadir
                                </span>
                            </div>
                        </div>

                        <!-- Date, Time, Location & Notulis -->
                        <div class="grid grid-cols-1 gap-2 text-xs text-slate-500 sm:grid-cols-2 pt-1 border-t border-slate-100">
                            <div class="flex items-center gap-1.5">
                                <span>📅</span>
                                <span>{{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('l, d F Y') }}</span>
                                @if($meeting->start_time)
                                    <span>• {{ substr($meeting->start_time, 0, 5) }} WIB</span>
                                @endif
                            </div>

                            <div class="flex items-center gap-1.5">
                                <span>👤</span>
                                <span>Notulis: <strong class="text-slate-700">{{ $meeting->creator?->name ?? 'Tidak ada' }}</strong></span>
                            </div>

                            @if($meeting->location)
                                <div class="flex items-center gap-1.5 truncate sm:col-span-2">
                                    <span>📍</span>
                                    <span class="truncate">{{ $meeting->location }}</span>
                                </div>
                            @endif

                            <!-- Audio Retention Indicator -->
                            <div class="flex items-center gap-1.5 sm:col-span-2 pt-0.5">
                                <span>🎧</span>
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold {{ $audioInfo['class'] }}">
                                    {{ $audioInfo['label'] }}
                                </span>
                            </div>
                        </div>

                        <!-- Embedded Mini Audio Player for Authorized Users (Pimpinan / Creator Notulis Only) -->
                        @if($canViewAudio)
                            <div class="rounded-xl border border-slate-200/80 bg-slate-50/70 p-3 space-y-1.5">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span class="font-bold text-slate-700 flex items-center gap-1">
                                        <span>🎧</span> Pemutar Audio Streaming
                                    </span>
                                    <span class="text-slate-400">Privat (nodownload)</span>
                                </div>
                                <audio
                                    controls
                                    controlsList="nodownload"
                                    oncontextmenu="return false;"
                                    class="w-full h-8 outline-hidden"
                                    preload="none"
                                >
                                    <source src="{{ $audioUrls[$meeting->id] }}" type="audio/mp4">
                                    Browser tidak mendukung pemutar audio ini.
                                </audio>
                            </div>
                        @endif

                        <!-- Action Bar Buttons tailored strictly by Role -->
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100">
                            <!-- Left Group: Materials Links -->
                            <div class="flex flex-wrap items-center gap-2">
                                <!-- Notulen Final Link (accessible if final minute exists) -->
                                @if($hasFinalMinute)
                                    <a
                                        href="{{ route('meetings.minutes.show', $meeting) }}"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-bold text-indigo-700 hover:bg-indigo-100 transition"
                                    >
                                        <span>📋 Baca Notulen Final</span>
                                    </a>
                                @elseif($user->isNotulis() && $meeting->currentMinute && $meeting->currentMinute->isDraft() && (int)$meeting->created_by === (int)$user->id)
                                    <!-- Only Creator Notulis sees draft edit link -->
                                    <a
                                        href="{{ route('meetings.minutes.edit', ['meeting' => $meeting, 'v' => $meeting->currentMinute->version]) }}"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-bold text-amber-800 hover:bg-amber-100 transition"
                                    >
                                        <span>✏️ Sunting Draf Notulen</span>
                                    </a>
                                @endif

                                <!-- Transkrip Mentah Link (Pimpinan and Creator Notulis Only; Admin NEVER) -->
                                @can('viewTranscript', $meeting)
                                    <a
                                        href="{{ route('meetings.transcript.show', $meeting) }}"
                                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition"
                                    >
                                        <span>📄 Transkrip Mentah</span>
                                    </a>
                                @endcan
                            </div>

                            <!-- Right Group: Detail / Room Action -->
                            <div class="flex items-center gap-2">
                                <a
                                    href="{{ route('meetings.show', $meeting) }}"
                                    class="inline-flex items-center gap-1 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition"
                                >
                                    <span>Detail Rapat →</span>
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center space-y-3">
                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-2xl">
                            🔍
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-slate-900">Tidak Ada Rapat Ditemukan</h3>
                            <p class="text-xs text-slate-500 mt-1">
                                Tidak ada agenda rapat yang cocok dengan filter atau kata kunci pencarian Anda.
                            </p>
                        </div>
                        @if(array_filter($filters))
                            <div class="pt-1">
                                <a
                                    href="{{ route('dashboard') }}"
                                    class="inline-flex items-center rounded-xl border border-slate-200 px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Hapus Semua Filter
                                </a>
                            </div>
                        @endif
                    </div>
                @endforelse
            </div>

            @if($meetings->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $meetings->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
