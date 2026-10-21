@extends('platform.layouts.app')

@section('title', 'Manajemen Tarif & Kurs')

@section('content')
<div class="space-y-8">
    <!-- Header Section -->
    <div>
        <h1 class="text-xl font-bold tracking-tight text-white">Manajemen Tarif Layanan & Kurs Mata Uang</h1>
        <p class="text-xs text-slate-400 mt-1">
            Tarif dan kurs berlaku secara historis. Perubahan tarif baru tidak akan mengubah snapshot biaya percobaan masa lalu.
        </p>
    </div>

    <!-- Section 1: Price Rates Management -->
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-base font-bold text-white">1. Master Tarif Layanan (Price Rates)</h2>
                <p class="text-xs text-slate-400">Daftar tarif per unit yang digunakan sistem untuk menghitung estimasi biaya riil.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add New Price Rate Form -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-4 h-fit">
                <h3 class="text-xs font-bold uppercase tracking-wider text-indigo-400">Tambah Tarif Baru</h3>

                <form method="POST" action="{{ route('platform.rates.price.store') }}" class="space-y-3.5">
                    @csrf

                    <div class="space-y-1">
                        <label for="provider" class="block text-xs font-semibold text-slate-300">Penyedia (Provider) <span class="text-rose-400">*</span></label>
                        <input
                            type="text"
                            id="provider"
                            name="provider"
                            required
                            placeholder="Contoh: groq, openai, local_disk"
                            value="{{ old('provider') }}"
                            class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="space-y-1">
                        <label for="model" class="block text-xs font-semibold text-slate-300">Nama Model / Sumber Daya <span class="text-rose-400">*</span></label>
                        <input
                            type="text"
                            id="model"
                            name="model"
                            required
                            placeholder="Contoh: whisper-large-v3-turbo, audio_storage"
                            value="{{ old('model') }}"
                            class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="space-y-1">
                        <label for="unit" class="block text-xs font-semibold text-slate-300">Satuan Unit <span class="text-rose-400">*</span></label>
                        <select
                            id="unit"
                            name="unit"
                            required
                            class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="detik_audio">detik_audio (STT Speech-to-Text)</option>
                            <option value="token">token (LLM Notulen & Summarizer)</option>
                            <option value="gb_bulan">gb_bulan (Penyimpanan Audio Bulanan)</option>
                            <option value="byte_hari">byte_hari (Penyimpanan Audio Harian)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label for="price_usd" class="block text-xs font-semibold text-slate-300">Tarif USD per Unit <span class="text-rose-400">*</span></label>
                        <input
                            type="number"
                            step="0.00000001"
                            min="0"
                            id="price_usd"
                            name="price_usd"
                            required
                            placeholder="Contoh: 0.00005000"
                            value="{{ old('price_usd') }}"
                            class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="space-y-1">
                        <label for="effective_from" class="block text-xs font-semibold text-slate-300">Berlaku Sejak <span class="text-rose-400">*</span></label>
                        <input
                            type="datetime-local"
                            id="effective_from"
                            name="effective_from"
                            required
                            value="{{ old('effective_from', now()->format('Y-m-d\TH:i')) }}"
                            class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-indigo-500 transition-colors cursor-pointer"
                    >
                        Simpan Tarif Baru
                    </button>
                </form>
            </div>

            <!-- Price Rates History Table -->
            <div class="lg:col-span-2 rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-800">
                    <h3 class="text-xs font-bold text-white">Riwayat Tarif Aktif & Historis</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="px-4 py-3">Penyedia & Model</th>
                                <th class="px-4 py-3">Satuan</th>
                                <th class="px-4 py-3 text-right">Tarif (USD)</th>
                                <th class="px-4 py-3">Mulai Berlaku</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-medium">
                            @forelse ($priceRates as $rate)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="px-4 py-3">
                                        <div class="font-bold text-white">{{ $rate->provider }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $rate->model }}</div>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="inline-flex items-center rounded-md bg-slate-800 px-2 py-0.5 text-[10px] text-indigo-300 font-mono">
                                            {{ $rate->unit }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-right font-mono text-emerald-400 font-bold">
                                        ${{ number_format($rate->price_usd, 8) }}
                                    </td>
                                    <td class="px-4 py-3 whitespace-nowrap text-slate-300 text-[11px]">
                                        {{ $rate->effective_from->format('d M Y, H:i') }} WIB
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-8 text-center text-xs text-slate-400">
                                        Belum ada data tarif di database (menggunakan konfigurasi bawaan config/pricing.php).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Section 2: FX Rates Management -->
    <div class="space-y-4 pt-4 border-t border-slate-800">
        <div>
            <h2 class="text-base font-bold text-white">2. Kurs Harian USD ke IDR (Foreign Exchange)</h2>
            <p class="text-xs text-slate-400">Digunakan untuk mengonversi estimasi biaya USD penyedia ke mata uang Rupiah.</p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Add New FX Rate Form -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xs space-y-4 h-fit">
                <h3 class="text-xs font-bold uppercase tracking-wider text-emerald-400">Input Kurs Harian</h3>

                <form method="POST" action="{{ route('platform.rates.fx.store') }}" class="space-y-3.5">
                    @csrf

                    <div class="space-y-1">
                        <label for="date" class="block text-xs font-semibold text-slate-300">Tanggal Kurs <span class="text-rose-400">*</span></label>
                        <input
                            type="date"
                            id="date"
                            name="date"
                            required
                            value="{{ old('date', now()->format('Y-m-d')) }}"
                            class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <div class="space-y-1">
                        <label for="usd_idr" class="block text-xs font-semibold text-slate-300">Nilai Kurs 1 USD (Rupiah) <span class="text-rose-400">*</span></label>
                        <input
                            type="number"
                            step="0.01"
                            min="1"
                            id="usd_idr"
                            name="usd_idr"
                            required
                            placeholder="Contoh: 16250.00"
                            value="{{ old('usd_idr', 16250) }}"
                            class="block w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs text-white placeholder-slate-500 shadow-xs focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-xs hover:bg-emerald-500 transition-colors cursor-pointer"
                    >
                        Simpan Nilai Kurs
                    </button>
                </form>
            </div>

            <!-- FX Rates History Table -->
            <div class="lg:col-span-2 rounded-2xl border border-slate-800 bg-slate-900/80 shadow-xs overflow-hidden">
                <div class="p-4 border-b border-slate-800">
                    <h3 class="text-xs font-bold text-white">Riwayat Kurs 30 Hari Terakhir</h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-950/70 border-b border-slate-800 text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <tr>
                                <th class="px-5 py-3">Tanggal</th>
                                <th class="px-4 py-3 text-right">Nilai Kurs (USD ke IDR)</th>
                                <th class="px-4 py-3 text-right">Waktu Input</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 font-medium">
                            @forelse ($fxRates as $fx)
                                <tr class="hover:bg-slate-800/40 transition-colors">
                                    <td class="px-5 py-3 font-bold text-slate-200">
                                        {{ $fx->date->translatedFormat('l, d F Y') }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-bold text-emerald-400">
                                        Rp {{ number_format($fx->usd_idr, 2, ',', '.') }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-400 text-[11px]">
                                        {{ $fx->updated_at->format('d M Y, H:i') }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-5 py-8 text-center text-xs text-slate-400">
                                        Belum ada data kurs di database (menggunakan kurs bawaan config Rp 16.000).
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
