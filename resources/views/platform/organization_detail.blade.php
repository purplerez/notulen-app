@extends('platform.layouts.app')

@section('title', 'Rincian Biaya - ' . $organization->name)

@section('content')
<div class="space-y-6" x-data="{ tab: 'meetings' }">
    <!-- Header & Navigation -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('platform.dashboard') }}" class="text-xs text-indigo-400 hover:text-indigo-300">
                    ← Kembali ke Dashboard
                </a>
                <span class="text-slate-600">•</span>
                <span class="text-xs text-slate-400 font-mono">{{ $organization->slug }}</span>
            </div>
            <h1 class="text-xl font-bold tracking-tight text-white">{{ $organization->name }}</h1>
            <p class="text-xs text-slate-400 mt-0.5">
                Rincian alokasi biaya operasional dan konsumsi sumber daya lembaga.
            </p>
        </div>

        <!-- Period Filter & CSV Export -->
        <div class="flex items-center gap-3">
            <form method="GET" action="{{ route('platform.organizations.show', $organization) }}" class="flex items-center gap-2">
                <input
                    type="month"
                    name="month"
                    value="{{ $month }}"
                    class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs text-slate-200 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 shadow-xs"
                />
                <button
                    type="submit"
                    class="rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 border border-slate-700 hover:bg-slate-700"
                >
                    Filter
                </button>
            </form>

            <a
                href="{{ route('platform.organizations.export_csv', [$organization, 'month' => $month]) }}"
                class="inline-flex items-center gap-1.5 rounded-xl bg-indigo-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-indigo-500 transition-colors"
            >
                <span>📥</span> Ekspor CSV
            </a>
        </div>
    </div>

    <!-- Privacy Notice Banner -->
    <div class="rounded-xl border border-indigo-900/60 bg-indigo-950/20 p-3.5 text-xs text-indigo-300 flex items-start gap-3">
        <span class="text-base leading-none">🔒</span>
        <div class="space-y-0.5">
            <strong class="font-bold text-indigo-200">Garansi Privasi Konten Rapat Lembaga</strong>
            <p class="text-[11px] text-slate-300 leading-relaxed">
                Super Admin tidak memiliki akses terhadap judul rapat, daftar peserta, notulen, rekaman audio, maupun transkrip mentah. Rincian disajikan murni berdasarkan nomor identifikasi (ID), waktu operasional, nama notulis, dan pemakaian unit komputasi.
            </p>
        </div>
    </div>

    <!-- Period Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-xs space-y-1">
            <span class="text-[11px] font-semibold text-slate-400 block">Total Biaya Periode</span>
            <div class="text-xl font-black text-white">
                Rp {{ number_format($costPeriodIdr, 0, ',', '.') }}
            </div>
            <span class="text-[10px] text-slate-400 block">≈ ${{ number_format($costPeriodUsd, 2) }} USD (Estimasi)</span>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-xs space-y-1">
            <span class="text-[11px] font-semibold text-indigo-400 block">Biaya STT (Transkripsi)</span>
            <div class="text-xl font-black text-slate-100">
                Rp {{ number_format($sttCost, 0, ',', '.') }}
            </div>
            <span class="text-[10px] text-slate-400 block">Berdasarkan detik audio</span>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-xs space-y-1">
            <span class="text-[11px] font-semibold text-purple-400 block">Biaya LLM (Notulen AI)</span>
            <div class="text-xl font-black text-slate-100">
                Rp {{ number_format($llmCost, 0, ',', '.') }}
            </div>
            <span class="text-[10px] text-slate-400 block">Berdasarkan token API</span>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-xs space-y-1">
            <span class="text-[11px] font-semibold text-cyan-400 block">Biaya Penyimpanan Audio</span>
            <div class="text-xl font-black text-slate-100">
                Rp {{ number_format($storageCost, 0, ',', '.') }}
            </div>
            <span class="text-[10px] text-slate-400 block">Berdasarkan byte-hari aktif</span>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-4 shadow-xs space-y-1">
            <span class="text-[11px] font-semibold text-emerald-400 block">Total Durasi Audio</span>
            <div class="text-xl font-black text-slate-100">
                {{ $totalAudioHours }} Jam
            </div>
            <span class="text-[10px] text-slate-400 block">Periode terpilih</span>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="border-b border-slate-800 flex items-center gap-4 text-xs font-semibold">
        <button
            type="button"
            @click="tab = 'meetings'"
            :class="tab === 'meetings' ? 'border-indigo-500 text-indigo-400 border-b-2 py-3' : 'text-slate-400 hover:text-slate-200 py-3'"
        >
            📋 Rincian per Rapat ({{ count($meetingBreakdown) }})
        </button>
        <button
            type="button"
            @click="tab = 'daily'"
            :class="tab === 'daily' ? 'border-indigo-500 text-indigo-400 border-b-2 py-3' : 'text-slate-400 hover:text-slate-200 py-3'"
        >
            📅 Rincian Harian ({{ count($dailyBreakdown) }})
        </button>
        <button
            type="button"
            @click="tab = 'notulis'"
            :class="tab === 'notulis' ? 'border-indigo-500 text-indigo-400 border-b-2 py-3' : 'text-slate-400 hover:text-slate-200 py-3'"
        >
            ✍️ Rincian per Notulis ({{ count($notulisBreakdown) }})
        </button>
    </div>

    <!-- Tab 1: Meetings Breakdown (No titles or contents) -->
    <div x-show="tab === 'meetings'" class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">ID Rapat</th>
                        <th class="px-4 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5">Notulis Pembuat</th>
                        <th class="px-4 py-3.5">Durasi Audio</th>
                        <th class="px-4 py-3.5 text-right">Biaya STT</th>
                        <th class="px-4 py-3.5 text-right">Biaya LLM</th>
                        <th class="px-4 py-3.5 text-right">Biaya Storage</th>
                        <th class="px-4 py-3.5 text-center">Gagal/Retry</th>
                        <th class="px-5 py-3.5 text-right">Total Biaya</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse ($meetingBreakdown as $m)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3.5 whitespace-nowrap font-mono font-bold text-indigo-400">
                                #{{ $m['id'] }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-slate-300">
                                {{ $m['date'] }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-slate-200">
                                {{ $m['creator_name'] }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-slate-300 font-mono">
                                {{ $m['duration_minutes'] }} mnt
                                <span class="text-[10px] text-slate-500 font-normal">({{ $m['duration_sec'] }} dtk)</span>
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono text-slate-300">
                                Rp {{ number_format($m['stt_cost_idr'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono text-slate-300">
                                Rp {{ number_format($m['llm_cost_idr'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-right font-mono text-slate-300">
                                Rp {{ number_format($m['storage_cost_idr'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 whitespace-nowrap text-center">
                                @if ($m['failed_attempts'] > 0)
                                    <span class="inline-flex items-center rounded-md bg-amber-950/60 px-2 py-0.5 text-[10px] font-bold text-amber-400 border border-amber-800">
                                        {{ $m['failed_attempts'] }}x gagal
                                    </span>
                                @else
                                    <span class="text-[10px] text-slate-500">0</span>
                                @endif
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-right font-bold text-emerald-400 font-mono">
                                Rp {{ number_format($m['total_cost_idr'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-5 py-8 text-center text-xs text-slate-400">
                                Tidak ada data rapat pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 2: Daily Breakdown -->
    <div x-show="tab === 'daily'" class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xs overflow-hidden" style="display: none;">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Tanggal</th>
                        <th class="px-4 py-3.5 text-right">Biaya STT</th>
                        <th class="px-4 py-3.5 text-right">Biaya LLM</th>
                        <th class="px-4 py-3.5 text-right">Biaya Penyimpanan</th>
                        <th class="px-5 py-3.5 text-right">Total Biaya (IDR)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse ($dailyBreakdown as $day)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3.5 font-bold text-slate-200">
                                {{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('l, d F Y') }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono text-slate-300">
                                Rp {{ number_format($day['stt_idr'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono text-slate-300">
                                Rp {{ number_format($day['llm_idr'], 0, ',', '.') }}
                            </td>
                            <td class="px-4 py-3.5 text-right font-mono text-slate-300">
                                Rp {{ number_format($day['storage_idr'], 0, ',', '.') }}
                            </td>
                            <td class="px-5 py-3.5 text-right font-bold text-emerald-400 font-mono">
                                Rp {{ number_format($day['total_idr'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-xs text-slate-400">
                                Belum ada aktivitas harian pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tab 3: Notulis Breakdown -->
    <div x-show="tab === 'notulis'" class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xs overflow-hidden" style="display: none;">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Nama Notulis</th>
                        <th class="px-4 py-3.5">Email</th>
                        <th class="px-4 py-3.5 text-center">Jumlah Rapat</th>
                        <th class="px-4 py-3.5 text-center">Durasi Audio</th>
                        <th class="px-5 py-3.5 text-right">Total Biaya Dihasilkan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse ($notulisBreakdown as $n)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-3.5 font-bold text-slate-200">
                                {{ $n['user']->name }}
                            </td>
                            <td class="px-4 py-3.5 text-slate-400 font-mono">
                                {{ $n['user']->email }}
                            </td>
                            <td class="px-4 py-3.5 text-center text-slate-200">
                                {{ $n['meetings_count'] }} rapat
                            </td>
                            <td class="px-4 py-3.5 text-center font-mono text-slate-300">
                                {{ $n['audio_hours'] }} jam
                            </td>
                            <td class="px-5 py-3.5 text-right font-bold text-emerald-400 font-mono">
                                Rp {{ number_format($n['cost_idr'], 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-8 text-center text-xs text-slate-400">
                                Tidak ada data notulis pada lembaga ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
