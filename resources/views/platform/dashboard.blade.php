@extends('platform.layouts.app')

@section('title', 'Dashboard Lembaga')

@section('content')
<div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-white">Ringkasan Operasional & Biaya Platform</h1>
            <p class="text-xs text-slate-400 mt-1">
                Pemantauan konsumsi sumber daya dan estimasi biaya riil seluruh lembaga (Periode: <strong class="text-slate-200">{{ \Illuminate\Support\Carbon::parse($currentMonth)->translatedFormat('F Y') }}</strong>).
            </p>
        </div>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 bg-slate-800/80 px-3 py-1.5 text-xs text-slate-300">
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse"></span>
                Sistem Aktif
            </span>
        </div>
    </div>

    <!-- Global Platform Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xs space-y-2">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-400">
                <span>Biaya Platform Bulan Ini</span>
                <span class="text-indigo-400">IDR</span>
            </div>
            <div class="text-2xl font-black text-white">
                Rp {{ number_format($summary['total_cost_month_idr'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-slate-400">
                ≈ ${{ number_format($summary['total_cost_month_usd'], 2) }} USD (Estimasi)
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xs space-y-2">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-400">
                <span>Biaya Bulan Lalu</span>
                <span class="text-slate-400">IDR</span>
            </div>
            <div class="text-2xl font-black text-slate-200">
                Rp {{ number_format($summary['total_cost_prev_month_idr'], 0, ',', '.') }}
            </div>
            <p class="text-[11px] text-slate-400">
                Bulan sebelumnya
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xs space-y-2">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-400">
                <span>Total Audio Diproses</span>
                <span class="text-indigo-400">Jam</span>
            </div>
            <div class="text-2xl font-black text-white">
                {{ number_format($summary['total_audio_hours'], 1) }} Jam
            </div>
            <p class="text-[11px] text-slate-400">
                Akumulasi seluruh rapat platform
            </p>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xs space-y-2">
            <div class="flex items-center justify-between text-xs font-semibold text-slate-400">
                <span>Lembaga Terdaftar</span>
                <span class="text-emerald-400">Aktif</span>
            </div>
            <div class="text-2xl font-black text-white">
                {{ $summary['total_orgs'] }} Lembaga
            </div>
            <p class="text-[11px] text-slate-400">
                {{ $summary['total_meetings'] }} total rapat terselenggara
            </p>
        </div>
    </div>

    <!-- Organizations Cost Table -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-white">Daftar Lembaga & Alokasi Biaya</h2>
                <p class="text-xs text-slate-400 mt-0.5">Rincian estimasi biaya riil (STT, LLM, Penyimpanan) per lembaga.</p>
            </div>
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <span class="h-2 w-2 rounded-full bg-indigo-500"></span> STT
                <span class="h-2 w-2 rounded-full bg-purple-500 ml-2"></span> LLM
                <span class="h-2 w-2 rounded-full bg-cyan-500 ml-2"></span> Storage
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Lembaga & Paket</th>
                        <th class="px-4 py-3.5">Biaya Bulan Ini</th>
                        <th class="px-4 py-3.5">Rincian Jenis</th>
                        <th class="px-4 py-3.5 text-center">Audio</th>
                        <th class="px-4 py-3.5">Rata-rata/Rapat</th>
                        <th class="px-4 py-3.5">Kuota Menit</th>
                        <th class="px-4 py-3.5 text-center">Tren</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse ($orgMetrics as $item)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-4">
                                <div class="font-bold text-white text-xs">{{ $item['org']->name }}</div>
                                <div class="flex items-center gap-2 mt-1">
                                    <span class="text-[10px] text-slate-400 font-mono">{{ $item['org']->slug }}</span>
                                    <span class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $item['org']->plan === 'paid' ? 'bg-indigo-950 text-indigo-300 border border-indigo-800' : 'bg-slate-800 text-slate-300' }}">
                                        {{ $item['org']->plan }}
                                    </span>
                                </div>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-100">
                                    Rp {{ number_format($item['cost_month_idr'], 0, ',', '.') }}
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5">
                                    ≈ ${{ number_format($item['cost_month_usd'], 2) }} USD
                                </div>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="space-y-1 text-[11px]">
                                    <div class="flex items-center justify-between gap-4 text-slate-300">
                                        <span class="text-slate-400">STT:</span>
                                        <span class="font-mono">Rp {{ number_format($item['stt_cost_month'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4 text-slate-300">
                                        <span class="text-slate-400">LLM:</span>
                                        <span class="font-mono">Rp {{ number_format($item['llm_cost_month'], 0, ',', '.') }}</span>
                                    </div>
                                    <div class="flex items-center justify-between gap-4 text-slate-300">
                                        <span class="text-slate-400">Storage:</span>
                                        <span class="font-mono">Rp {{ number_format($item['storage_cost_month'], 0, ',', '.') }}</span>
                                    </div>
                                </div>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                <span class="font-bold text-slate-200">{{ $item['audio_hours_total'] }}</span>
                                <span class="text-[10px] text-slate-400 block">Jam</span>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="font-bold text-slate-200">
                                    Rp {{ number_format($item['avg_cost_per_meeting'], 0, ',', '.') }}
                                </div>
                                <span class="text-[10px] text-slate-400 block">
                                    dari {{ $item['meetings_count'] }} rapat
                                </span>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap">
                                <div class="flex items-center justify-between text-[11px] mb-1">
                                    <span class="text-slate-300 font-semibold">{{ $item['used_minutes'] }} / {{ $item['quota_minutes'] }} mnt</span>
                                    <span class="text-[10px] {{ $item['quota_pct'] >= 90 ? 'text-rose-400 font-bold' : 'text-slate-400' }}">{{ $item['quota_pct'] }}%</span>
                                </div>
                                <div class="w-28 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                    <div
                                        class="h-1.5 rounded-full {{ $item['quota_pct'] >= 90 ? 'bg-rose-500' : ($item['quota_pct'] >= 75 ? 'bg-amber-400' : 'bg-indigo-500') }}"
                                        style="width: {{ min(100, $item['quota_pct']) }}%"
                                    ></div>
                                </div>
                            </td>

                            <td class="px-4 py-4 whitespace-nowrap text-center">
                                @if ($item['trend_pct'] > 0)
                                    <span class="inline-flex items-center gap-0.5 rounded-md bg-rose-950/60 px-2 py-0.5 text-[10px] font-bold text-rose-400 border border-rose-900/50">
                                        ↑ +{{ $item['trend_pct'] }}%
                                    </span>
                                @elseif ($item['trend_pct'] < 0)
                                    <span class="inline-flex items-center gap-0.5 rounded-md bg-emerald-950/60 px-2 py-0.5 text-[10px] font-bold text-emerald-400 border border-emerald-900/50">
                                        ↓ {{ $item['trend_pct'] }}%
                                    </span>
                                @else
                                    <span class="text-[10px] text-slate-400 font-bold">0%</span>
                                @endif
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                <a
                                    href="{{ route('platform.organizations.show', $item['org']) }}"
                                    class="inline-flex items-center rounded-xl bg-slate-800 px-3 py-1.5 text-xs font-semibold text-indigo-400 hover:bg-slate-700 hover:text-indigo-300 border border-slate-700 transition-colors"
                                >
                                    Rincian →
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-xs text-slate-400">
                                Belum ada lembaga yang terdaftar.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
