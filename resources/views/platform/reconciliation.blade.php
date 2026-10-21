@extends('platform.layouts.app')

@section('title', 'Rekonsiliasi Biaya Penyedia')

@section('content')
<div class="space-y-6" x-data="{ modalOpen: false, activeProvider: '', activeUsd: '', activeIdr: '', activeNotes: '' }">
    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-xl font-bold tracking-tight text-white">Rekonsiliasi Biaya Penyedia Layanan</h1>
            <p class="text-xs text-slate-400 mt-1">
                Perbandingan antara estimasi sistem berbasis perhitungan riil pemakaian versus tagihan faktual penyedia (Groq, OpenAI, Storage).
            </p>
        </div>

        <form method="GET" action="{{ route('platform.reconciliation.index') }}" class="flex items-center gap-2">
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
                Filter Bulan
            </button>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xs space-y-1.5">
            <div class="flex items-center justify-between text-xs text-slate-400 font-semibold">
                <span>Total Estimasi Sistem <strong class="text-indigo-400">(Estimasi)</strong></span>
            </div>
            <div class="text-2xl font-black text-white">
                Rp {{ number_format($totals['est_idr'], 0, ',', '.') }}
            </div>
            <span class="text-[11px] text-slate-400 block font-mono">
                ≈ ${{ number_format($totals['est_usd'], 2) }} USD
            </span>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xs space-y-1.5">
            <div class="flex items-center justify-between text-xs text-slate-400 font-semibold">
                <span>Total Tagihan Aktual Provider</span>
            </div>
            <div class="text-2xl font-black text-slate-200">
                Rp {{ number_format($totals['actual_idr'], 0, ',', '.') }}
            </div>
            <span class="text-[11px] text-slate-400 block font-mono">
                ≈ ${{ number_format($totals['actual_usd'], 2) }} USD (Input Manual)
            </span>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/80 p-5 shadow-xs space-y-1.5">
            <div class="flex items-center justify-between text-xs text-slate-400 font-semibold">
                <span>Selisih / Variansi Biaya</span>
            </div>
            <div class="text-2xl font-black {{ ($totals['diff_idr'] ?? 0) > 0 ? 'text-amber-400' : (($totals['diff_idr'] ?? 0) < 0 ? 'text-rose-400' : 'text-slate-200') }}">
                @if ($totals['diff_idr'] !== null)
                    Rp {{ number_format($totals['diff_idr'], 0, ',', '.') }}
                @else
                    <span class="text-sm text-slate-500 font-normal">Belum ada input aktual</span>
                @endif
            </div>
            <span class="text-[11px] text-slate-400 block">
                Estimasi Sistem vs Invoice Riil
            </span>
        </div>
    </div>

    <!-- Comparison Table -->
    <div class="rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xs overflow-hidden">
        <div class="p-5 border-b border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-white">Komparasi Estimasi vs Invoice Aktual ({{ \Illuminate\Support\Carbon::parse($month)->translatedFormat('F Y') }})</h2>
                <p class="text-xs text-slate-400 mt-0.5">Semua kalkulasi sistem dilabeli secara eksplisit sebagai nilai estimasi.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    <tr>
                        <th class="px-5 py-3.5">Penyedia Layanan</th>
                        <th class="px-4 py-3.5 text-right">Estimasi Sistem (USD)</th>
                        <th class="px-4 py-3.5 text-right">Estimasi Sistem (IDR)</th>
                        <th class="px-4 py-3.5 text-right">Invoice Aktual (USD)</th>
                        <th class="px-4 py-3.5 text-right">Invoice Aktual (IDR)</th>
                        <th class="px-4 py-3.5 text-right">Selisih & Variansi</th>
                        <th class="px-4 py-3.5">Catatan</th>
                        <th class="px-5 py-3.5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800/60 font-medium">
                    @forelse ($reconciliations as $rec)
                        <tr class="hover:bg-slate-800/40 transition-colors">
                            <td class="px-5 py-4 whitespace-nowrap">
                                <span class="font-bold text-white uppercase text-xs tracking-wider block">{{ $rec['provider'] }}</span>
                                <span class="text-[10px] text-slate-400">
                                    @if ($rec['provider'] === 'groq')
                                        STT Whisper Transkripsi
                                    @elseif ($rec['provider'] === 'openai')
                                        LLM Notulen & Summarizer
                                    @elseif ($rec['provider'] === 'local_disk' || $rec['provider'] === 's3')
                                        Penyimpanan Audio Rapat
                                    @else
                                        Layanan Eksternal
                                    @endif
                                </span>
                            </td>

                            <td class="px-4 py-4 text-right whitespace-nowrap font-mono text-slate-300">
                                ${{ number_format($rec['est_usd'], 4) }}
                                <span class="text-[10px] text-indigo-400 block font-sans">(Estimasi)</span>
                            </td>

                            <td class="px-4 py-4 text-right whitespace-nowrap font-mono font-bold text-slate-200">
                                Rp {{ number_format($rec['est_idr'], 0, ',', '.') }}
                                <span class="text-[10px] text-indigo-400 block font-sans font-normal">(Estimasi)</span>
                            </td>

                            <td class="px-4 py-4 text-right whitespace-nowrap font-mono text-slate-300">
                                @if ($rec['actual_usd'] !== null)
                                    ${{ number_format($rec['actual_usd'], 4) }}
                                @else
                                    <span class="text-slate-600">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-4 text-right whitespace-nowrap font-mono font-bold text-slate-300">
                                @if ($rec['actual_idr'] !== null)
                                    Rp {{ number_format($rec['actual_idr'], 0, ',', '.') }}
                                @else
                                    <span class="text-slate-600">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-4 text-right whitespace-nowrap">
                                @if ($rec['diff_pct'] !== null)
                                    <div class="font-mono font-bold {{ abs($rec['diff_pct']) > 10 ? 'text-rose-400' : 'text-emerald-400' }}">
                                        {{ $rec['diff_pct'] > 0 ? '+' : '' }}{{ $rec['diff_pct'] }}%
                                    </div>
                                    @if ($rec['diff_idr'] !== null)
                                        <span class="text-[10px] text-slate-400 block font-mono">
                                            {{ $rec['diff_idr'] > 0 ? '+' : '' }}Rp {{ number_format($rec['diff_idr'], 0, ',', '.') }}
                                        </span>
                                    @endif
                                @else
                                    <span class="text-slate-600 text-[11px]">-</span>
                                @endif
                            </td>

                            <td class="px-4 py-4 max-w-xs truncate text-[11px] text-slate-400">
                                {{ $rec['notes'] ?? '-' }}
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap text-right">
                                <button
                                    type="button"
                                    @click="
                                        modalOpen = true;
                                        activeProvider = '{{ $rec['provider'] }}';
                                        activeUsd = '{{ $rec['actual_usd'] ?? '' }}';
                                        activeIdr = '{{ $rec['actual_idr'] ?? '' }}';
                                        activeNotes = '{{ addslashes($rec['notes'] ?? '') }}';
                                    "
                                    class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-1 text-xs font-semibold text-indigo-400 hover:bg-slate-700 hover:text-indigo-300 cursor-pointer"
                                >
                                    Input Aktual
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-5 py-8 text-center text-xs text-slate-400">
                                Belum ada data transaksi atau provider untuk bulan ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Input Manual Tagihan Aktual -->
    <div
        x-show="modalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-xs"
        style="display: none;"
    >
        <div
            @click.away="modalOpen = false"
            class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6 shadow-2xl space-y-4"
        >
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <h3 class="text-sm font-bold text-white">Input Tagihan Aktual Provider</h3>
                <button type="button" @click="modalOpen = false" class="text-slate-400 hover:text-slate-200">✕</button>
            </div>

            <form method="POST" action="{{ route('platform.reconciliation.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="provider" :value="activeProvider">

                <div>
                    <span class="block text-xs font-semibold text-slate-400 mb-1">Provider:</span>
                    <span class="block text-sm font-bold text-indigo-400 uppercase font-mono" x-text="activeProvider"></span>
                </div>

                <div class="space-y-1.5">
                    <label for="actual_cost_usd" class="block text-xs font-semibold text-slate-300">Biaya Invoice Aktual (USD)</label>
                    <input
                        type="number"
                        step="0.0001"
                        min="0"
                        id="actual_cost_usd"
                        name="actual_cost_usd"
                        x-model="activeUsd"
                        class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="Contoh: 14.50"
                    />
                </div>

                <div class="space-y-1.5">
                    <label for="actual_cost_idr" class="block text-xs font-semibold text-slate-300">Biaya Invoice Aktual (IDR - Opsional)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="actual_cost_idr"
                        name="actual_cost_idr"
                        x-model="activeIdr"
                        class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2.5 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="Contoh: 235000"
                    />
                </div>

                <div class="space-y-1.5">
                    <label for="notes" class="block text-xs font-semibold text-slate-300">Catatan Rekonsiliasi</label>
                    <textarea
                        id="notes"
                        name="notes"
                        rows="2"
                        x-model="activeNotes"
                        class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3.5 py-2 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        placeholder="Catatan perbedaan tarif, diskon paket, atau kredit promosi."
                    ></textarea>
                </div>

                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        @click="modalOpen = false"
                        class="rounded-xl border border-slate-700 px-4 py-2 text-xs font-semibold text-slate-300 hover:bg-slate-800"
                    >
                        Batal
                    </button>
                    <button
                        type="submit"
                        class="rounded-xl bg-indigo-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-indigo-500"
                    >
                        Simpan Rekonsiliasi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
