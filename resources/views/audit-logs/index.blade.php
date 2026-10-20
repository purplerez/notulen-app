<x-layouts.app :title="'Log Akses - ' . config('app.name', 'Notulensi Rapat')">
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200/80 pb-4">
            <div>
                <a href="{{ route('dashboard') }}" class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                    ← Kembali ke Dashboard
                </a>
                <h1 class="text-xl font-bold tracking-tight text-slate-900 sm:text-2xl mt-1">
                    📜 Log Akses & Audit Trail
                </h1>
                <p class="text-xs text-slate-500 mt-0.5">
                    Histori aktivitas hanya-tambah (append-only) untuk pemantauan keamanan dan kepatuhan privasi
                </p>
            </div>

            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                    <span>🔒 Hanya Baca</span>
                </span>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="rounded-2xl border border-slate-200/90 bg-white p-4 sm:p-5 shadow-2xs space-y-4">
            <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Filter Log Akses</h2>

            <form method="GET" action="{{ route('audit_logs.index') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Filter: Rapat -->
                <div class="space-y-1">
                    <label for="filter_meeting" class="block text-xs font-semibold text-slate-700">Rapat</label>
                    <select
                        id="filter_meeting"
                        name="meeting_id"
                        class="block w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                    >
                        <option value="">Semua Rapat</option>
                        @foreach($availableMeetings as $meeting)
                            <option value="{{ $meeting->id }}" @selected((string)$filters['meeting_id'] === (string)$meeting->id)>
                                {{ Str::limit($meeting->title, 35) }} ({{ \Carbon\Carbon::parse($meeting->meeting_date)->format('d/m/y') }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter: Aksi -->
                <div class="space-y-1">
                    <label for="filter_action" class="block text-xs font-semibold text-slate-700">Jenis Aksi</label>
                    <select
                        id="filter_action"
                        name="action"
                        class="block w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                    >
                        <option value="">Semua Aksi</option>
                        @foreach($availableActions as $key => $label)
                            <option value="{{ $key }}" @selected($filters['action'] === $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Filter: Peran -->
                <div class="space-y-1">
                    <label for="filter_role" class="block text-xs font-semibold text-slate-700">Peran Pelaku</label>
                    <select
                        id="filter_role"
                        name="role"
                        class="block w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                    >
                        <option value="">Semua Peran</option>
                        <option value="pimpinan" @selected($filters['role'] === 'pimpinan')>Pimpinan</option>
                        <option value="admin" @selected($filters['role'] === 'admin')>Admin</option>
                        <option value="notulis" @selected($filters['role'] === 'notulis')>Notulis</option>
                        <option value="attendee" @selected($filters['role'] === 'attendee')>Peserta / Tamu</option>
                    </select>
                </div>

                <!-- Filter: Tanggal -->
                <div class="space-y-1">
                    <label for="filter_date" class="block text-xs font-semibold text-slate-700">Tanggal</label>
                    <input
                        type="date"
                        id="filter_date"
                        name="date"
                        value="{{ $filters['date'] }}"
                        class="block w-full min-h-[40px] rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs shadow-2xs focus:ring-2 focus:ring-indigo-600"
                    />
                </div>

                <!-- Filter Actions -->
                <div class="sm:col-span-2 lg:col-span-4 flex items-center justify-end gap-2 pt-1">
                    @if(array_filter($filters))
                        <a
                            href="{{ route('audit_logs.index') }}"
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

        <!-- Logs Table / Feed -->
        <div class="rounded-2xl border border-slate-200/90 bg-white shadow-2xs overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700">
                    <thead class="bg-slate-50/80 text-[11px] font-bold uppercase text-slate-500 border-b border-slate-200">
                        <tr>
                            <th class="px-4 py-3.5">Waktu</th>
                            <th class="px-4 py-3.5">Pelaku (Siapa)</th>
                            <th class="px-4 py-3.5">Aksi</th>
                            <th class="px-4 py-3.5">Rapat</th>
                            <th class="px-4 py-3.5">Detail & Meta</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($logs as $log)
                            <tr class="hover:bg-slate-50/60 transition">
                                <!-- Waktu -->
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="font-medium text-slate-900">
                                        {{ $log->created_at?->translatedFormat('d M Y, H:i') ?? '-' }}
                                    </div>
                                    <div class="text-[10px] text-slate-400">
                                        {{ $log->created_at?->diffForHumans() ?? '' }}
                                    </div>
                                </td>

                                <!-- Pelaku -->
                                <td class="px-4 py-3.5">
                                    @if($log->actorUser)
                                        <div class="font-semibold text-slate-900">{{ $log->actorUser->name }}</div>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="inline-flex items-center rounded-md px-1.5 py-0.2 text-[10px] font-bold uppercase
                                                @if($log->actorUser->role === 'pimpinan') bg-amber-50 text-amber-700
                                                @elseif($log->actorUser->role === 'admin') bg-purple-50 text-purple-700
                                                @else bg-blue-50 text-blue-700 @endif">
                                                {{ $log->actorUser->role }}
                                            </span>
                                            <span class="text-[10px] text-slate-400 truncate max-w-[140px]">{{ $log->actorUser->email }}</span>
                                        </div>
                                    @elseif($log->actorAttendee)
                                        <div class="font-semibold text-slate-900">{{ $log->actorAttendee->name }}</div>
                                        <span class="inline-flex items-center rounded-md bg-emerald-50 px-1.5 py-0.2 text-[10px] font-bold text-emerald-700">
                                            Peserta Publik
                                        </span>
                                    @else
                                        <span class="text-slate-400 italic">Sistem / Publik</span>
                                    @endif
                                </td>

                                <!-- Aksi -->
                                <td class="px-4 py-3.5">
                                    <span class="inline-flex items-center rounded-lg px-2.5 py-1 text-xs font-semibold
                                        @if(str_contains($log->action, 'audio')) bg-rose-50 text-rose-700 ring-1 ring-rose-600/20
                                        @elseif(str_contains($log->action, 'transcript')) bg-indigo-50 text-indigo-700 ring-1 ring-indigo-600/20
                                        @elseif(str_contains($log->action, 'minute')) bg-emerald-50 text-emerald-700 ring-1 ring-emerald-600/20
                                        @elseif(str_contains($log->action, 'attendance') || str_contains($log->action, 'attendee')) bg-blue-50 text-blue-700 ring-1 ring-blue-600/20
                                        @elseif(str_contains($log->action, 'login') || str_contains($log->action, 'logout')) bg-slate-100 text-slate-700
                                        @else bg-purple-50 text-purple-700 ring-1 ring-purple-600/20 @endif">
                                        {{ $log->action_label }}
                                    </span>
                                </td>

                                <!-- Rapat -->
                                <td class="px-4 py-3.5">
                                    @if($log->meeting)
                                        <div class="font-medium text-slate-900 line-clamp-1 max-w-[200px]" title="{{ $log->meeting->title }}">
                                            {{ $log->meeting->title }}
                                        </div>
                                        <div class="text-[10px] text-slate-400">
                                            #{{ $log->meeting_id }} • {{ \Carbon\Carbon::parse($log->meeting->meeting_date)->translatedFormat('d M Y') }}
                                        </div>
                                    @elseif($log->meeting_id)
                                        <div class="text-slate-400 italic">Rapat #{{ $log->meeting_id }} (Telah Dihapus)</div>
                                    @else
                                        <span class="text-slate-400">-</span>
                                    @endif
                                </td>

                                <!-- Detail / Meta -->
                                <td class="px-4 py-3.5 text-[11px] text-slate-600">
                                    @if(!empty($log->meta))
                                        <div class="space-y-0.5 max-w-[250px]">
                                            @foreach($log->meta as $k => $v)
                                                @if(is_scalar($v))
                                                    <div class="truncate">
                                                        <strong class="text-slate-500">{{ $k }}:</strong>
                                                        <span>{{ (string)$v }}</span>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-slate-300">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-8 text-center text-slate-400">
                                    Tidak ada data log akses yang sesuai dengan filter.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($logs->hasPages())
                <div class="p-4 border-t border-slate-100">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.app>
