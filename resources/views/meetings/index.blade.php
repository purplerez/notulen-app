<x-layouts.app :title="'Daftar Rapat - ' . config('app.name', 'Notulensi Rapat')">
    <div class="space-y-6">
        <!-- Header & Action Button -->
        <div class="flex items-center justify-between gap-4">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Dashboard
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                    Daftar Rapat
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">Semua agenda dan room rapat organisasi Anda</p>
            </div>

            @can('create', App\Models\Meeting::class)
                <a
                    href="{{ route('meetings.create') }}"
                    class="inline-flex min-h-[44px] items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700 active:scale-[0.98] transition"
                >
                    + Buat Room
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div class="rounded-xl bg-emerald-50 p-4 border border-emerald-200 text-sm text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <!-- Meetings List -->
        <div class="space-y-3">
            @forelse($meetings as $meeting)
                <a
                    href="{{ route('meetings.show', $meeting) }}"
                    class="block rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-2xs hover:border-indigo-300 hover:shadow-xs transition"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold uppercase tracking-wider
                                    @if($meeting->status === 'draft') bg-slate-100 text-slate-700
                                    @elseif($meeting->status === 'recording') bg-rose-50 text-rose-700 ring-1 ring-rose-600/20 animate-pulse
                                    @elseif($meeting->status === 'transcribing') bg-amber-50 text-amber-700 ring-1 ring-amber-600/20
                                    @elseif($meeting->status === 'ready') bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                                    @else bg-blue-50 text-blue-700 @endif">
                                    {{ $meeting->status }}
                                </span>
                                <span class="text-xs text-slate-400 capitalize">Gaya {{ $meeting->style }}</span>
                            </div>

                            <h2 class="text-base font-bold text-slate-900 truncate">
                                {{ $meeting->title }}
                            </h2>

                            <div class="flex flex-wrap items-center gap-y-1 gap-x-3 text-xs text-slate-500 pt-0.5">
                                <span class="flex items-center gap-1">
                                    📅 {{ \Carbon\Carbon::parse($meeting->meeting_date)->translatedFormat('d M Y') }}
                                    @if($meeting->start_time)
                                        pukul {{ substr($meeting->start_time, 0, 5) }}
                                    @endif
                                </span>
                                @if($meeting->location)
                                    <span class="flex items-center gap-1 truncate">
                                        📍 {{ $meeting->location }}
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700">
                                👥 {{ $meeting->attendees_count }} Peserta
                            </span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-200 bg-white p-8 text-center space-y-3">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-50 text-2xl">
                        📋
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-slate-900">Belum Ada Rapat</h3>
                        <p class="text-xs text-slate-500 mt-1">Belum ada room rapat yang dibuat untuk organisasi ini.</p>
                    </div>
                    @can('create', App\Models\Meeting::class)
                        <div class="pt-2">
                            <a
                                href="{{ route('meetings.create') }}"
                                class="inline-flex min-h-[44px] items-center rounded-xl bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-indigo-700"
                            >
                                + Buat Room Rapat Pertama
                            </a>
                        </div>
                    @endcan
                </div>
            @endforelse
        </div>
    </div>
</x-layouts.app>
